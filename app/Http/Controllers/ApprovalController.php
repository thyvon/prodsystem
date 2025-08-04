<?php
namespace App\Http\Controllers;

use App\Models\Approval;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ApprovalController extends Controller
{
    /**
     * Store a new approval record.
     *
     * @param array $data
     * @return array
     */
    public function storeApproval(array $data)
    {
        try {
            $approval = Approval::create([
                'approvable_type' => $data['approvable_type'],
                'approvable_id' => $data['approvable_id'],
                'document_name' => $data['document_name'],
                'request_type' => $data['request_type'],
                'approval_status' => $data['approval_status'],
                'comment' => $data['comment'] ?? null,
                'ordinal' => $data['ordinal'],
                'requester_id' => $data['requester_id'],
                'responder_id' => $data['responder_id'],
                'responded_date' => isset($data['approval_status']) && $data['approval_status'] === 'approved' ? now() : null,
            ]);

            Log::debug('Approval created', ['approval_id' => $approval->id, 'data' => $data]);

            return [
                'success' => true,
                'approval' => $approval,
                'message' => 'Approval created successfully'
            ];
        } catch (\Exception $e) {
            Log::error('Failed to create approval', ['data' => $data, 'error' => $e->getMessage()]);
            return [
                'success' => false,
                'message' => 'Failed to create approval: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Update an existing approval record.
     *
     * @param array $data
     * @return array
     */
    public function updateApproval(array $data)
    {
        try {
            $approval = Approval::where([
                'approvable_type' => $data['approvable_type'],
                'approvable_id' => $data['approvable_id'],
                'responder_id' => $data['responder_id'],
                'request_type' => $data['request_type'],
            ])->firstOrFail();

            if ($approval->approval_status !== 'Pending') {
                Log::debug('Approval update rejected: Not pending', ['approval_id' => $approval->id]);
                return [
                    'success' => false,
                    'message' => 'Approval already processed'
                ];
            }

            $approval->update([
                'approval_status' => $data['approval_status'],
                'comment' => $data['comment'] ?? $approval->comment,
                'responded_date' => $data['approval_status'] === 'approved' ? now() : null,
            ]);

            Log::debug('Approval updated', ['approval_id' => $approval->id, 'status' => $data['approval_status']]);

            return [
                'success' => true,
                'approval' => $approval,
                'message' => 'Approval updated successfully'
            ];
        } catch (\Exception $e) {
            Log::error('Failed to update approval', ['data' => $data, 'error' => $e->getMessage()]);
            return [
                'success' => false,
                'message' => 'Failed to update approval: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Approve a pending approval for a document.
     *
     * @param Request $request
     * @param string $approvableType
     * @param int $approvableId
     * @param string $requestType
     * @return array
     */
    public function confirmApproval(Request $request, $approvableType, $approvableId, $requestType)
    {
        $request->validate([
            'comment' => 'nullable|string|max:1000',
        ]);

        $approvable = $this->findApprovable($approvableType, $approvableId);

        // Check if user is the assigned responder
        $approval = Approval::where([
            'approvable_type' => $approvableType,
            'approvable_id' => $approvableId,
            'request_type' => $requestType,
            'responder_id' => Auth::id(),
            'approval_status' => 'Pending',
        ])->first();

        if (!$approval) {
            Log::debug('Confirm approval failed: Unauthorized or no pending approval', [
                'user_id' => Auth::id(),
                'approvable_id' => $approvableId,
                'request_type' => $requestType,
            ]);
            return [
                'success' => false,
                'message' => "Unauthorized or no pending {$requestType} approval assigned"
            ];
        }

        if (!$this->canSubmitApproval($approvable, $requestType)) {
            Log::debug('Confirm approval failed: Previous approvals required', [
                'approvable_id' => $approvableId,
                'request_type' => $requestType,
            ]);
            return [
                'success' => false,
                'message' => 'Previous approvals required'
            ];
        }

        $data = [
            'approvable_type' => $approvableType,
            'approvable_id' => $approvableId,
            'document_name' => $approvable->reference_no,
            'request_type' => $requestType,
            'approval_status' => 'approved',
            'comment' => $request->comment,
            'ordinal' => $this->getOrdinalForRequestType($approvableType, $requestType),
            'requester_id' => $approvable->created_by,
            'responder_id' => Auth::id(),
        ];

        $result = $this->updateApproval($data);

        if ($result['success']) {
            $this->updateDocumentStatus($approvable);
        }

        return $result;
    }

    /**
     * Reject a pending approval for a document.
     *
     * @param Request $request
     * @param string $approvableType
     * @param int $approvableId
     * @param string $requestType
     * @return array
     */
    public function rejectApproval(Request $request, $approvableType, $approvableId, $requestType)
    {
        $request->validate([
            'comment' => 'nullable|string|max:1000',
        ]);

        $approvable = $this->findApprovable($approvableType, $approvableId);

        // Check if user is the assigned responder
        $approval = Approval::where([
            'approvable_type' => $approvableType,
            'approvable_id' => $approvableId,
            'request_type' => $requestType,
            'responder_id' => Auth::id(),
            'approval_status' => 'Pending',
        ])->first();

        if (!$approval) {
            Log::debug('Reject approval failed: Unauthorized or no pending approval', [
                'user_id' => Auth::id(),
                'approvable_id' => $approvableId,
                'request_type' => $requestType,
            ]);
            return [
                'success' => false,
                'message' => "Unauthorized or no pending {$requestType} approval assigned"
            ];
        }

        if (!$this->canSubmitApproval($approvable, $requestType)) {
            Log::debug('Reject approval failed: Previous approvals required', [
                'approvable_id' => $approvableId,
                'request_type' => $requestType,
            ]);
            return [
                'success' => false,
                'message' => 'Previous approvals required'
            ];
        }

        $data = [
            'approvable_type' => $approvableType,
            'approvable_id' => $approvableId,
            'document_name' => $approvable->reference_no,
            'request_type' => $requestType,
            'approval_status' => 'rejected',
            'comment' => $request->comment,
            'ordinal' => $this->getOrdinalForRequestType($approvableType, $requestType),
            'requester_id' => $approvable->created_by,
            'responder_id' => Auth::id(),
        ];

        $result = $this->updateApproval($data);

        if ($result['success']) {
            $this->updateDocumentStatus($approvable);
        }

        return $result;
    }

    /**
     * Reassign a responder for a specific approval.
     *
     * @param Request $request
     * @param string $approvableType
     * @param int $approvableId
     * @param string $requestType
     * @return array
     */
    public function reassignResponder(Request $request, $approvableType, $approvableId, $requestType)
    {
        $request->validate([
            'new_user_id' => 'required|exists:users,id',
            'comment' => 'nullable|string|max:1000',
        ]);

        $approvable = $this->findApprovable($approvableType, $approvableId);

        // Check if the user is authorized (creator or current responder)
        $approval = Approval::where([
            'approvable_type' => $approvableType,
            'approvable_id' => $approvableId,
            'request_type' => $requestType,
            'approval_status' => 'Pending',
        ])->first();

        if (!$approval) {
            Log::debug('Reassign responder failed: No pending approval', [
                'approvable_id' => $approvableId,
                'request_type' => $requestType,
            ]);
            return [
                'success' => false,
                'message' => 'No pending approval found for the specified request type'
            ];
        }

        $isCreator = $approvable->created_by === Auth::id();
        $isResponder = $approval->responder_id === Auth::id();

        if (!$isCreator && !$isResponder) {
            Log::debug('Reassign responder failed: Unauthorized', [
                'user_id' => Auth::id(),
                'approvable_id' => $approvableId,
                'request_type' => $requestType,
            ]);
            return [
                'success' => false,
                'message' => 'Unauthorized to reassign responder'
            ];
        }

        try {
            $approval->update([
                'responder_id' => $request->new_user_id,
                'comment' => $request->comment ?? $approval->comment,
                'updated_at' => now(),
            ]);

            Log::debug('Responder reassigned', [
                'approval_id' => $approval->id,
                'new_responder_id' => $request->new_user_id,
            ]);

            return [
                'success' => true,
                'message' => 'Responder reassigned successfully'
            ];
        } catch (\Exception $e) {
            Log::error('Failed to reassign responder', [
                'approvable_id' => $approvableId,
                'request_type' => $requestType,
                'error' => $e->getMessage(),
            ]);
            return [
                'success' => false,
                'message' => 'Failed to reassign responder: ' . $e->getMessage()
            ];
        }
    }

    /**
     * List approvals for a specific document.
     *
     * @param Request $request
     * @param string $approvableType
     * @param int $approvableId
     * @return array
     */
    public function listApprovals(Request $request, $approvableType, $approvableId)
    {
        $approvable = $this->findApprovable($approvableType, $approvableId);

        // Authorization: Only creator or responders can view approvals
        $isCreator = $approvable->created_by === Auth::id();
        $isResponder = Approval::where([
            'approvable_type' => $approvableType,
            'approvable_id' => $approvableId,
            'responder_id' => Auth::id(),
        ])->exists();

        if (!$isCreator && !$isResponder) {
            Log::debug('List approvals failed: Unauthorized', [
                'user_id' => Auth::id(),
                'approvable_id' => $approvableId,
            ]);
            return [
                'success' => false,
                'message' => 'Unauthorized to view approvals'
            ];
        }

        $approvals = Approval::where([
            'approvable_type' => $approvableType,
            'approvable_id' => $approvableId,
        ])->with([
            'requester' => fn($query) => $query->select('id', 'name'),
            'responder' => fn($query) => $query->select('id', 'name')
        ])->get([
            'id',
            'approvable_type',
            'approvable_id',
            'document_name',
            'request_type',
            'approval_status',
            'comment',
            'ordinal',
            'requester_id',
            'responder_id',
            'responded_date',
            'created_at',
            'updated_at'
        ]);

        return [
            'success' => true,
            'approvals' => $approvals,
            'message' => 'Approvals retrieved successfully'
        ];
    }

    /**
     * Find the approvable model instance.
     *
     * @param string $approvableType
     * @param int $approvableId
     * @return \Illuminate\Database\Eloquent\Model
     * @throws \Exception
     */
    protected function findApprovable($approvableType, $approvableId)
    {
        $modelClass = $approvableType;
        if (!class_exists($modelClass)) {
            throw new \Exception("Model {$modelClass} does not exist");
        }
        return $modelClass::findOrFail($approvableId);
    }

    /**
     * Check if an approval can be submitted based on previous approvals.
     *
     * @param \Illuminate\Database\Eloquent\Model $approvable
     * @param string $requestType
     * @return bool
     */
    protected function canSubmitApproval($approvable, $requestType)
    {
        $ordinal = $this->getOrdinalForRequestType(get_class($approvable), $requestType);
        if ($ordinal === 1) {
            return true;
        }

        return Approval::where([
            'approvable_type' => get_class($approvable),
            'approvable_id' => $approvable->id,
            'ordinal' => $ordinal - 1,
            'approval_status' => 'approved',
        ])->exists();
    }

    /**
     * Get the ordinal for a request type.
     *
     * @param string $approvableType
     * @param string $requestType
     * @return int
     */
    protected function getOrdinalForRequestType($approvableType, $requestType)
    {
        $ordinals = [
            'App\Models\MainStockBeginning' => [
                'review' => 1,
                'check' => 2,
                'approve' => 3,
            ],
            // Add other models as needed, e.g.:
            // 'App\Models\Invoice' => ['review' => 1, 'validate' => 2, 'approve' => 3]
        ];

        return $ordinals[$approvableType][$requestType] ?? 1;
    }

    /**
     * Update the document's status based on approval states.
     *
     * @param \Illuminate\Database\Eloquent\Model $approvable
     * @return void
     */
    protected function updateDocumentStatus($approvable)
    {
        $approvals = $approvable->approvals;
        $allApproved = $approvals->every(fn ($approval) => $approval->approval_status === 'approved');
        $anyRejected = $approvals->contains(fn ($approval) => $approval->approval_status === 'rejected');

        $newStatus = 'Pending';
        if ($anyRejected) {
            $newStatus = 'rejected';
        } elseif ($allApproved) {
            $newStatus = 'approved';
        }

        $approvable->update(['status' => $newStatus]);
        Log::debug('Document status updated', [
            'approvable_id' => $approvable->id,
            'new_status' => $newStatus,
        ]);
    }
}