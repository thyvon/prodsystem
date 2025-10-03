<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Carbon\Carbon;
use App\Models\User;
use Illuminate\View\View;   
use App\Models\DigitalDocsApproval;
use App\Services\SharePointService;
use App\Services\ApprovalService;

class DigitalDocsApprovalController extends Controller
{
    protected ApprovalService $approvalService;

    public function __construct(ApprovalService $approvalService)
    {
        $this->approvalService = $approvalService;
    }

    private const DEFAULT_LIMIT = 10;
    private const MAX_LIMIT = 1000;

    public function index(): View
    {
        return view('approval.digital-approval-list');
    }

    public function getDigitalDocuments(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'search' => 'nullable|string|max:255',
            'sortColumn' => 'nullable|string',
            'sortDirection' => 'nullable|string|in:asc,desc',
            'limit' => 'nullable|integer|min:1|max:' . self::MAX_LIMIT,
            'page' => 'nullable|integer|min:1',
            'draw' => 'nullable|integer',
        ]);

        $query = DigitalDocsApproval::with(['approvals.responder'])
            ->whereNull('deleted_at');

        if ($search = $validated['search'] ?? null) {
            $query->where(fn($q) => $q->where('reference_no', 'like', "%$search%")
                ->orWhere('document_type', 'like', "%$search%")
                ->orWhere('description', 'like', "%$search%"));
        }

        $sortColumn = $validated['sortColumn'] ?? 'id';
        $sortDirection = $validated['sortDirection'] ?? 'desc';
        $limit = $validated['limit'] ?? self::DEFAULT_LIMIT;
        $page = $validated['page'] ?? 1;

        // Clone the query for filtered count before pagination
        $recordsFiltered = (clone $query)->count();

        $digitalDocsApprovals = $query->orderBy($sortColumn, $sortDirection)
            ->paginate($limit, ['*'], 'page', $page);

        return response()->json([
            'data' => $digitalDocsApprovals->map(fn($digitaldoc) => [
                'id' => $digitaldoc->id,
                'reference_no' => $digitaldoc->reference_no,
                'document_type' => $digitaldoc->document_type,
                'description' => $digitaldoc->description,
                'approval_status' => $digitaldoc->approval_status,
                'created_at' => $digitaldoc->created_at,
                'created_by' => $digitaldoc->creator->name ?? 'Unknown',
                'updated_at' => $digitaldoc->updated_at,
                'approvals' => $digitaldoc->approvals->map(fn($a) => [
                    'id' => $a->id,
                    'request_type' => $a->request_type,
                    'approval_status' => $a->approval_status,
                    'responder' => $a->responder ? [
                        'id' => $a->responder->id,
                        'name' => $a->responder->name,
                        'email' => $a->responder->email,
                    ] : null,
                ]),
            ]),
            'recordsTotal' => DigitalDocsApproval::whereNull('deleted_at')->count(),
            'recordsFiltered' => $recordsFiltered,
            'draw' => (int) ($validated['draw'] ?? 1),
        ]);
    }
    /**
     * Show the form for creating/editing a digital document approval
     */
    public function form(DigitalDocsApproval $digitalDocsApproval = null): View
    {
        return view('approval.digital-approval-form', compact('digitalDocsApproval'));
    }

    /**
     * Store a new digital document approval with SharePoint upload
     */
    public function store(Request $request): JsonResponse
    {
        $validated = Validator::make($request->all(), $this->digitalDocsApprovalValidationRules())->validate();

        $accessToken = auth()->user()->microsoft_token; // AD token
        if (empty($accessToken)) {
            return response()->json([
                'message' => 'Microsoft access token not found. Please re-authenticate your account.',
            ], 401);
        }

        $customDriveId = 'b!M8DPdNUo-UW5SA5DQoh6WBOHI8g_WM1GqHrcuxe8NjqK7G8JZp38SZIzeDteW3fZ'; // Digital Docs Library
        $sharePoint = new SharePointService($accessToken, $customDriveId);

        try {
            return DB::transaction(function () use ($validated, $request, $sharePoint) {

                // --- Step 1: Build dynamic folder path: document_type/year/month ---
                $folderPath = $this->getSharePointFolderPath($validated['document_type']);

                // --- Step 2: Generate reference number (used as file name) ---
                $referenceNo = $this->generateReferenceNo();

                // --- Step 3: Upload file to SharePoint with referenceNo as filename ---
                $file = $request->file('file');
                $extension = $file->getClientOriginalExtension();
                $fileData = $sharePoint->uploadFile(
                    $file,
                    $folderPath,
                    ['Title' => $validated['description']],
                    "{$referenceNo}.{$extension}" 
                );

                // --- Step 4: Create DigitalDocsApproval record ---
                $digitalDocsApproval = DigitalDocsApproval::create([
                    'reference_no' => $referenceNo,
                    'description' => $validated['description'],
                    'document_type' => $validated['document_type'],
                    'sharepoint_file_id' => $fileData['id'],
                    'sharepoint_file_name' => $fileData['name'],
                    'sharepoint_file_url' => $fileData['url'],
                    'approval_status' => 'Pending',
                    'created_by' => auth()->id(),
                ]);

                // --- Step 5: Store approvals ---
                $this->storeApprovals($digitalDocsApproval, $validated['approvals']);

                return response()->json([
                    'message' => 'Digital document approval created successfully.',
                    'data' => $digitalDocsApproval->load('approvals.responder'),
                ], 201);
            });
        } catch (\Exception $e) {
            Log::error('Failed to create digital document approval', ['error' => $e->getMessage()]);

            return response()->json([
                'message' => 'Failed to create digital document approval.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function getEdit(DigitalDocsApproval $digitalDocsApproval): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => $digitalDocsApproval->load('approvals.responder', 'approvals.requester'),
        ]);
    }

    public function update(Request $request, DigitalDocsApproval $digitalDocsApproval): JsonResponse
    {
        $validated = Validator::make($request->all(), array_merge(
            $this->digitalDocsApprovalValidationRules(),
            ['file' => 'nullable|file|max:10240'] // allow updating without changing file
        ))->validate();

        $accessToken = auth()->user()->microsoft_token;
        if (empty($accessToken)) {
            return response()->json([
                'message' => 'Microsoft access token not found. Please re-authenticate your account.',
            ], 401);
        }

        $customDriveId = 'b!M8DPdNUo-UW5SA5DQoh6WBOHI8g_WM1GqHrcuxe8NjqK7G8JZp38SZIzeDteW3fZ';
        $sharePoint = new SharePointService($accessToken, $customDriveId);

        try {
            return DB::transaction(function () use ($validated, $request, $digitalDocsApproval, $sharePoint) {

                // Update file if provided
                if ($request->hasFile('file')) {
                    $file = $request->file('file');
                    $extension = $file->getClientOriginalExtension();
                    $fileData = $sharePoint->updateFile(
                        $digitalDocsApproval->sharepoint_file_id,
                        $file,
                        ['Title' => $validated['description']]
                    );

                    $digitalDocsApproval->sharepoint_file_name = $fileData['name'];
                    $digitalDocsApproval->sharepoint_file_url = $fileData['url'];
                } else {
                    // Only update metadata
                    $sharePoint->updateFileProperties(
                        $digitalDocsApproval->sharepoint_file_id,
                        ['Title' => $validated['description']]
                    );
                }

                // Update description and document type
                $digitalDocsApproval->description = $validated['description'];
                $digitalDocsApproval->document_type = $validated['document_type'];
                $digitalDocsApproval->save();

                // Update approvals (optional: clear old and re-insert)
                $digitalDocsApproval->approvals()->delete();
                $this->storeApprovals($digitalDocsApproval, $validated['approvals']);

                return response()->json([
                    'message' => 'Digital document approval updated successfully.',
                    'data' => $digitalDocsApproval->load('approvals.responder'),
                ]);
            });
        } catch (\Exception $e) {
            Log::error('Failed to update digital document approval', ['error' => $e->getMessage()]);

            return response()->json([
                'message' => 'Failed to update digital document approval.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Destroy a digital document approval
     */
    public function destroy(DigitalDocsApproval $digitalDocsApproval): JsonResponse
    {
        $accessToken = auth()->user()->microsoft_token;
        if (empty($accessToken)) {
            return response()->json([
                'message' => 'Microsoft access token not found. Please re-authenticate your account.',
            ], 401);
        }

        $customDriveId = 'b!M8DPdNUo-UW5SA5DQoh6WBOHI8g_WM1GqHrcuxe8NjqK7G8JZp38SZIzeDteW3fZ';
        $sharePoint = new SharePointService($accessToken, $customDriveId);

        try {
            return DB::transaction(function () use ($digitalDocsApproval, $sharePoint) {
                
                // Delete file from SharePoint
                if ($digitalDocsApproval->sharepoint_file_id) {
                    $sharePoint->deleteFile($digitalDocsApproval->sharepoint_file_id);
                }

                // Delete approvals
                $digitalDocsApproval->approvals()->delete();

                // Delete the main record
                $digitalDocsApproval->delete();

                return response()->json([
                    'message' => 'Digital document approval deleted successfully.',
                ]);
            });
        } catch (\Exception $e) {
            Log::error('Failed to delete digital document approval', ['error' => $e->getMessage()]);

            return response()->json([
                'message' => 'Failed to delete digital document approval.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function uploadTemp(Request $request): JsonResponse
    {
        $request->validate([
            'file' => 'required|file|max:10240', // max 10MB
        ]);

        try {
            $file = $request->file('file');

            // Generate a unique temporary filename
            $tempFileName = Str::uuid() . '.' . $file->getClientOriginalExtension();

            // Store in temporary folder (storage/app/temp)
            $path = $file->storeAs('temp', $tempFileName);

            return response()->json([
                'message' => 'File uploaded temporarily.',
                'file_name' => $tempFileName,
                'original_name' => $file->getClientOriginalName(),
                'temp_path' => $path,
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to upload file temporarily.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Validation rules for digital document approval
     */
    private function digitalDocsApprovalValidationRules(): array
    {
        return [
            'description' => 'required|string|max:1000',
            'document_type' => 'required|string|max:255',
            'file' => 'required|file|max:10240',              // max 10MB
            'approvals' => 'required|array|min:1',
            'approvals.*.user_id' => 'required|exists:users,id',
            'approvals.*.request_type' => 'required|string|in:approve,initial,check,review,acknowledge',
        ];
    }

    /**
     * Generate a unique reference number for the document
     */
    private function generateReferenceNo(): string
    {
        return 'DOC-' . now()->format('Ym') . '-' . str_pad(
            DigitalDocsApproval::whereDate('created_at', now())->count() + 1,
            4,
            '0',
            STR_PAD_LEFT
        );
    }

    /**
     * Store approvals (you may already have this implemented)
     */
    protected function storeApprovals(DigitalDocsApproval $digitalDocsApproval, array $approvals)
    {
        foreach ($approvals as $approval) {
            $approvalData = [
                'approvable_type' => DigitalDocsApproval::class,
                'approvable_id' => $digitalDocsApproval->id,
                'document_name' => $digitalDocsApproval->document_type,
                'document_reference' => $digitalDocsApproval->reference_no,
                'request_type' => $approval['request_type'],
                'approval_status' => 'Pending',
                'ordinal' => $this->getOrdinalForRequestType($approval['request_type']),
                'requester_id' => $digitalDocsApproval->created_by,
                'responder_id' => $approval['user_id'],
                'position_id' => User::find($approval['user_id'])?->defaultPosition()?->id,
            ];
            $this->approvalService->storeApproval($approvalData);
        }
    }

    /**
     * Build dynamic SharePoint folder path: document_type/year/month
     */
    private function getSharePointFolderPath(string $documentType): string
    {
        $year = now()->format('Y');
        $monthNumber = now()->format('m');       // 01, 02, ..., 12
        $monthName = now()->format('M');         // Jan, Feb, ..., Dec (short name)
        
        return "{$documentType}/{$year}/{$monthNumber} {$monthName}";
    }

    public function getApprovalUsers(): JsonResponse
    {
        return response()->json(
            User::whereNotNull('telegram_id')
                ->where('id', '!=', auth()->id())
                ->select('id', 'name', 'telegram_id')
                ->get()
        );
    }

    protected function getOrdinalForRequestType($requestType)
    {
        $ordinals = [
        'initial' => 1,
        'check' => 2,
        'review' => 3,
        'approve' => 4,
        'acknowledge' => 5,
        ];
        return $ordinals[$requestType] ?? 1;
    }

}