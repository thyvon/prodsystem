<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;
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
                'sharepoint_file_url' => $digitaldoc->sharepoint_file_url,
                'sharepoint_file_ui_url' => $digitaldoc->sharepoint_file_ui_url,
                'approvals' => $digitaldoc->approvals->map(fn($a) => [
                    'id' => $a->id,
                    'request_type' => $a->request_type,
                    'approval_status' => $a->approval_status,
                    'response_date' => $a->responded_date?->format('Y-m-d H:i'),
                    'approver_name' => $a->responder ? $a->responder->name : 'Unknown',
                ]),
            ]),
            'recordsTotal' => DigitalDocsApproval::whereNull('deleted_at')->count(),
            'recordsFiltered' => $recordsFiltered,
            'draw' => (int) ($validated['draw'] ?? 1),
        ]);
    }

    public function form(DigitalDocsApproval $digitalDocsApproval = null): View
    {
        return view('approval.digital-approval-form', compact('digitalDocsApproval'));
    }

    public function getEditData(DigitalDocsApproval $digitalDocsApproval): JsonResponse
    {
        try {
            $digitalDocsApproval->load('approvals.responder:id,name');
            return response()->json([
                'message' => 'Digital approval retrieved successfully.',
                'data' => $digitalDocsApproval,
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to retrieve digital approval', ['id' => $digitalDocsApproval->id, 'error' => $e->getMessage()]);
            return response()->json([
                'message' => 'Failed to retrieve digital approval.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function store(Request $request): JsonResponse
    {
        $validated = Validator::make($request->all(), $this->digitalDocsApprovalValidationRules())->validate();

        $user = Auth::user();
        $sharePoint = new SharePointService($user);

        try {
            return DB::transaction(function () use ($validated, $request, $sharePoint, $user) {
                $folderPath = $this->getSharePointFolderPath($validated['document_type']);
                $referenceNo = $this->generateReferenceNo();
                $file = $request->file('file');
                $extension = $file->getClientOriginalExtension();

                $customDriveId = 'b!M8DPdNUo-UW5SA5DQoh6WBOHI8g_WM1GqHrcuxe8NjqK7G8JZp38SZIzeDteW3fZ';
                $fileData = $sharePoint->uploadFile(
                    $file,
                    $folderPath,
                    ['Title' => $validated['description']],
                    "{$referenceNo}.{$extension}",
                    $customDriveId
                );

                $digitalDocsApproval = DigitalDocsApproval::create([
                    'reference_no' => $referenceNo,
                    'description' => $validated['description'],
                    'document_type' => $validated['document_type'],
                    'sharepoint_file_id' => $fileData['id'],
                    'sharepoint_file_name' => $fileData['name'],
                    'sharepoint_file_url' => $fileData['url'],
                    'sharepoint_file_ui_url' => $fileData['ui_url'],
                    'sharepoint_drive_id' => $customDriveId,
                    'approval_status' => 'Pending',
                    'created_by' => $user->id,
                ]);

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

    public function update(Request $request, DigitalDocsApproval $digitalDocsApproval): JsonResponse
    {
        // Validation rules adjusted for update mode
        $validated = Validator::make(
            $request->all(),
            $this->digitalDocsApprovalValidationRules(true)
        )->validate();

        $user = Auth::user();
        $sharePoint = new SharePointService($user);

        try {
            return DB::transaction(function () use ($validated, $request, $digitalDocsApproval, $sharePoint, $user) {
                $customDriveId = 'b!M8DPdNUo-UW5SA5DQoh6WBOHI8g_WM1GqHrcuxe8NjqK7G8JZp38SZIzeDteW3fZ';

                // ✅ Handle file update
                if ($request->hasFile('file')) {
                    $file = $request->file('file');
                    $fileData = $sharePoint->updateFile(
                        $digitalDocsApproval->sharepoint_file_id,
                        $file,
                        ['Title' => $validated['description'] ?? $digitalDocsApproval->description],
                        $customDriveId
                    );

                    $digitalDocsApproval->sharepoint_file_name = $fileData['name'] ?? $digitalDocsApproval->sharepoint_file_name;
                    $digitalDocsApproval->sharepoint_file_url = $fileData['url'] ?? $digitalDocsApproval->sharepoint_file_url;
                    $digitalDocsApproval->sharepoint_drive_id = $customDriveId;
                } else {
                    // ✅ Only update SharePoint metadata if description changed
                    if (!empty($validated['description']) && $validated['description'] !== $digitalDocsApproval->description) {
                        $sharePoint->updateFileProperties(
                            $digitalDocsApproval->sharepoint_file_id,
                            ['Title' => $validated['description']],
                            $digitalDocsApproval->sharepoint_drive_id
                        );
                    }
                }

                // ✅ Always update local DB values (even if SharePoint skipped)
                $digitalDocsApproval->description = $validated['description'] ?? $digitalDocsApproval->description;
                $digitalDocsApproval->document_type = $validated['document_type'] ?? $digitalDocsApproval->document_type;
                $digitalDocsApproval->updated_by = $user->id;
                $digitalDocsApproval->save();

                // ✅ Replace approvals if provided
                if (!empty($validated['approvals'])) {
                    $digitalDocsApproval->approvals()->delete();
                    $this->storeApprovals($digitalDocsApproval, $validated['approvals']);
                }

                return response()->json([
                    'message' => 'Digital document approval updated successfully.',
                    'data' => $digitalDocsApproval->load('approvals.responder'),
                ]);
            });
        } catch (\Exception $e) {
            Log::error('Failed to update digital document approval', [
                'id' => $digitalDocsApproval->id,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'message' => 'Failed to update digital document approval.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function destroy(DigitalDocsApproval $digitalDocsApproval): JsonResponse
    {
        $user = Auth::user();
        $sharePoint = new SharePointService($user);

        try {
            return DB::transaction(function () use ($digitalDocsApproval, $sharePoint) {
                $customDriveId = 'b!M8DPdNUo-UW5SA5DQoh6WBOHI8g_WM1GqHrcuxe8NjqK7G8JZp38SZIzeDteW3fZ';

                if ($digitalDocsApproval->sharepoint_file_id) {
                    $sharePoint->deleteFile(
                        $digitalDocsApproval->sharepoint_file_id,
                        $customDriveId,
                        true
                    );
                }

                $digitalDocsApproval->approvals()->delete();
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

    public function viewFile(DigitalDocsApproval $digitalDocsApproval)
    {
        if (!$digitalDocsApproval->sharepoint_file_id || !$digitalDocsApproval->sharepoint_drive_id) {
            abort(404, "File not found.");
        }

        $user = Auth::user();
        $sharePoint = new SharePointService($user);

        try {
            return $sharePoint->streamFile(
                $digitalDocsApproval->sharepoint_file_id,
                $digitalDocsApproval->sharepoint_drive_id
            );
        } catch (\Exception $e) {
            abort(404, "File not found or access denied. Error: " . $e->getMessage());
        }
    }

    private function digitalDocsApprovalValidationRules(bool $isUpdate = false): array
    {
        return [
            'description' => ($isUpdate ? 'sometimes|required' : 'required') . '|string|max:1000',
            'document_type' => ($isUpdate ? 'sometimes|required' : 'required') . '|string|max:255',
            'file' => ($isUpdate ? 'nullable' : 'required') . '|file|max:10240',
            'approvals' => ($isUpdate ? 'sometimes|array|min:1' : 'required|array|min:1'),
            'approvals.*.user_id' => 'required|exists:users,id',
            'approvals.*.request_type' => 'required|string|in:approve,initial,check,review,acknowledge',
        ];
    }

    private function generateReferenceNo(): string
    {
        $prefix = 'DOC-' . now()->format('Ym') . '-';
        $count = DigitalDocsApproval::withTrashed()
            ->whereYear('created_at', now()->year)
            ->whereMonth('created_at', now()->month)
            ->count() + 1;

        do {
            $referenceNo = $prefix . str_pad($count, 4, '0', STR_PAD_LEFT);
            $exists = DigitalDocsApproval::withTrashed()
                ->where('reference_no', $referenceNo)
                ->exists();
            $count++;
        } while ($exists);

        return $referenceNo;
    }

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

    private function getSharePointFolderPath(string $documentType): string
    {
        $year = now()->format('Y');
        $monthNumber = now()->format('m');
        $monthName = now()->format('M');

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
