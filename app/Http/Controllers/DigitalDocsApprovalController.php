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

        try {
            return DB::transaction(function () use ($validated, $request) {

                // --- Step 1: Build dynamic folder path: document_type/year/month ---
                $folderPath = $this->getSharePointFolderPath($validated['document_type']);

                // --- Step 2: Set custom SharePoint drive/library ID ---
                $customDriveId = 'b!M8DPdNUo-UW5SA5DQoh6WBOHI8g_WM1GqHrcuxe8NjpEJMrAkn_RR7PJLX2Xvrtc'; // <-- Replace with your drive ID

                // --- Step 3: Upload file to SharePoint ---
                $accessToken = auth()->user()->microsoft_token; // AD token

                if (empty($accessToken)) {
                    return response()->json([
                        'message' => 'Microsoft access token not found. Please re-authenticate your account.',
                    ], 401);
                }

                $sharePoint = new SharePointService($accessToken, $customDriveId);

                $fileData = $sharePoint->uploadFile(
                    $request->file('file'),
                    $folderPath,
                    ['Title' => $validated['description']] // optional metadata
                );

                // --- Step 4: Create DigitalDocsApproval record ---
                $referenceNo = $this->generateReferenceNo();
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
        return 'DOC-' . now()->format('Ymd') . '-' . str_pad(
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
        $month = now()->format('m');
        return "{$documentType}/{$year}/{$month}";
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