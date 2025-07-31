<?php
namespace App\Http\Controllers;

use App\Models\Approval;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class ApprovalController extends Controller
{
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

            return [
                'success' => true,
                'approval' => $approval,
                'message' => 'Approval created successfully'
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => 'Failed to create approval: ' . $e->getMessage()
            ];
        }
    }

    public function updateApproval(array $data)
    {
        try {
            $approval = Approval::where('approvable_type', $data['approvable_type'])
                ->where('approvable_id', $data['approvable_id'])
                ->where('responder_id', $data['responder_id'])
                ->where('request_type', $data['request_type'])
                ->firstOrFail();

            if ($approval->approval_status !== 'pending') {
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

            return [
                'success' => true,
                'approval' => $approval,
                'message' => 'Approval updated successfully'
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => 'Failed to update approval: ' . $e->getMessage()
            ];
        }
    }

    public function confirmApproval(Request $request, $approvableType, $approvableId, $requestType)
    {
        $request->validate([
            'comment' => 'nullable|string|max:1000',
        ]);

        $approvable = $this->findApprovable($approvableType, $approvableId);
        $responder = $approvable->responders->firstWhere('pivot.request_type', $requestType);

        if (!$responder || $responder->id !== Auth::id()) {
            return [
                'success' => false,
                'message' => "Unauthorized or no {$requestType} assigned"
            ];
        }

        if (!$this->canSubmitApproval($approvable, $requestType)) {
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
            'responder_id' => $responder->id,
        ];

        $result = $this->updateApproval($data);

        if ($result['success']) {
            $this->updateDocumentStatus($approvable);
        }

        return $result;
    }

    public function rejectApproval(Request $request, $approvableType, $approvableId, $requestType)
    {
        $request->validate([
            'comment' => 'nullable|string|max:1000',
        ]);

        $approvable = $this->findApprovable($approvableType, $approvableId);
        $responder = $approvable->responders->firstWhere('pivot.request_type', $requestType);

        if (!$responder || $responder->id !== Auth::id()) {
            return [
                'success' => false,
                'message' => "Unauthorized or no {$requestType} assigned"
            ];
        }

        if (!$this->canSubmitApproval($approvable, $requestType)) {
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
            'responder_id' => $responder->id,
        ];

        $result = $this->updateApproval($data);

        if ($result['success']) {
            $this->updateDocumentStatus($approvable);
        }

        return $result;
    }

    public function reassignResponder(Request $request, $approvableType, $approvableId, $requestType)
    {
        $request->validate([
            'new_user_id' => 'required|exists:users,id',
            'comment' => 'nullable|string|max:1000',
        ]);

        $approvable = $this->findApprovable($approvableType, $approvableId);
        $currentResponder = $approvable->responders->firstWhere('pivot.request_type', $requestType);

        if (!$currentResponder || $currentResponder->id !== Auth::id()) {
            return [
                'success' => false,
                'message' => "Unauthorized or no {$requestType} assigned"
            ];
        }

        $approval = Approval::where('approvable_type', $approvableType)
            ->where('approvable_id', $approvableId)
            ->where('responder_id', $currentResponder->id)
            ->where('request_type', $requestType)
            ->first();

        if ($approval && $approval->approval_status !== 'pending') {
            return [
                'success' => false,
                'message' => 'Cannot reassign: Approval already processed'
            ];
        }

        try {
            DB::transaction(function () use ($approvable, $approvableType, $approvableId, $requestType, $request, $currentResponder) {
                // Update responder in document_user
                $approvable->responders()->updateExistingPivot(
                    $currentResponder->id,
                    ['request_type' => $requestType],
                    ['user_id' => $request->new_user_id]
                );

                // Delete old approval
                Approval::where('approvable_type', $approvableType)
                    ->where('approvable_id', $approvableId)
                    ->where('responder_id', $currentResponder->id)
                    ->where('request_type', $requestType)
                    ->delete();

                // Create new pending approval
                $approvalData = [
                    'approvable_type' => $approvableType,
                    'approvable_id' => $approvableId,
                    'document_name' => $approvable->reference_no,
                    'request_type' => $requestType,
                    'approval_status' => 'pending',
                    'comment' => $request->comment,
                    'ordinal' => $this->getOrdinalForRequestType($approvableType, $requestType),
                    'requester_id' => $approvable->created_by,
                    'responder_id' => $request->new_user_id,
                ];

                $this->storeApproval($approvalData);
            });

            return [
                'success' => true,
                'message' => 'Responder reassigned successfully'
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => 'Failed to reassign responder: ' . $e->getMessage()
            ];
        }
    }

    public function listApprovals(Request $request, $approvableType, $approvableId)
    {
        $approvable = $this->findApprovable($approvableType, $approvableId);

        // Authorization: Only creator or responders can view approvals
        $isCreator = $approvable->created_by === Auth::id();
        $isResponder = $approvable->responders->contains('id', Auth::id());

        if (!$isCreator && !$isResponder) {
            return [
                'success' => false,
                'message' => 'Unauthorized to view approvals'
            ];
        }

        $approvals = Approval::where('approvable_type', $approvableType)
            ->where('approvable_id', $approvableId)
            ->with(['requester' => fn($query) => $query->select('id', 'name'),
                    'responder' => fn($query) => $query->select('id', 'name')])
            ->get(['id', 'approvable_type', 'approvable_id', 'document_name', 'request_type', 'approval_status', 'comment', 'ordinal', 'requester_id', 'responder_id', 'responded_date', 'created_at', 'updated_at']);

        return [
            'success' => true,
            'approvals' => $approvals,
            'message' => 'Approvals retrieved successfully'
        ];
    }

    protected function findApprovable($approvableType, $approvableId)
    {
        $modelClass = $approvableType;
        if (!class_exists($modelClass)) {
            throw new \Exception("Model {$modelClass} does not exist");
        }
        return $modelClass::with('responders')->findOrFail($approvableId);
    }

    protected function canSubmitApproval($approvable, $requestType)
    {
        $ordinal = $this->getOrdinalForRequestType(get_class($approvable), $requestType);
        if ($ordinal === 1) {
            return true;
        }

        return Approval::where('approvable_type', get_class($approvable))
            ->where('approvable_id', $approvable->id)
            ->where('ordinal', $ordinal - 1)
            ->where('approval_status', 'approved')
            ->exists();
    }

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

    protected function updateDocumentStatus($approvable)
    {
        $approvals = $approvable->approvals;
        $allApproved = $approvals->every(fn ($approval) => $approval->approval_status === 'approved');
        $anyRejected = $approvals->contains(fn ($approval) => $approval->approval_status === 'rejected');

        $newStatus = 'pending';
        if ($anyRejected) {
            $newStatus = 'rejected';
        } elseif ($allApproved && $approvals->count() === $approvable->responders->count()) {
            $newStatus = 'approved';
        }

        $approvable->update(['status' => $newStatus]);
    }
}