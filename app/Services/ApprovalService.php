<?php

namespace App\Services;

use App\Models\Approval;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Exception;

class ApprovalService
{
    /* ---------------------- Create / Update ---------------------- */

    public function storeApproval(array $data): array
    {
        try {
            if (empty($data['position_id']) && !empty($data['responder_id'])) {
                $responder = User::find($data['responder_id']);
                $data['position_id'] = $responder?->defaultPosition()?->id;
            }

            $approval = Approval::create([
                'approvable_type'    => $data['approvable_type'],
                'approvable_id'      => $data['approvable_id'],
                'document_name'      => $data['document_name'],
                'document_reference' => $data['document_reference'],
                'request_type'       => $data['request_type'],
                'approval_status'    => $data['approval_status'],
                'comment'            => $data['comment'] ?? null,
                'ordinal'            => $data['ordinal'],
                'requester_id'       => $data['requester_id'],
                'responder_id'       => $data['responder_id'],
                'position_id'        => $data['position_id'] ?? null,
                'responded_date'     => $data['approval_status'] === 'Approved' ? now() : null,
            ]);

            Log::debug('Approval created', ['approval_id' => $approval->id]);

            return $this->jsonResponse(true, 'Approval created successfully', $approval);
        } catch (Exception $e) {
            Log::error('Failed to create approval', ['error' => $e->getMessage(), 'data' => $data]);
            return $this->jsonResponse(false, "Failed to create approval: {$e->getMessage()}");
        }
    }

    public function updateApproval(array $data): array
    {
        try {
            $approval = Approval::where([
                'approvable_type' => $data['approvable_type'],
                'approvable_id'   => $data['approvable_id'],
                'responder_id'    => $data['responder_id'],
                'request_type'    => $data['request_type'],
            ])->firstOrFail();

            if ($approval->approval_status !== 'Pending') {
                return $this->jsonResponse(false, 'Approval already processed');
            }

            $approval->update([
                'approval_status' => $data['approval_status'],
                'comment'         => $data['comment'] ?? $approval->comment,
                'responded_date'  => now(),
            ]);

            Log::debug('Approval updated', [
                'approval_id' => $approval->id,
                'status'      => $data['approval_status'],
            ]);

            return $this->jsonResponse(true, 'Approval updated successfully', $approval);
        } catch (Exception $e) {
            Log::error('Failed to update approval', ['error' => $e->getMessage(), 'data' => $data]);
            return $this->jsonResponse(false, "Failed to update approval: {$e->getMessage()}");
        }
    }

    /* ---------------------- Approve / Reject / Return ---------------------- */

    public function handleApprovalAction($approvable, string $requestType, string $action, ?string $comment): array
    {
        $statusMap = [
            'approve' => 'Approved',
            'reject'  => 'Rejected',
            'return'  => 'Returned', // new action
        ];

        $status = $statusMap[$action] ?? null;
        if (!$status) {
            return $this->jsonResponse(false, "Invalid action: {$action}");
        }

        $approval = Approval::where([
            'approvable_type' => get_class($approvable),
            'approvable_id'   => $approvable->id,
            'request_type'    => $requestType,
            'responder_id'    => Auth::id(),
            'approval_status' => 'Pending',
        ])->first();

        if (!$approval) {
            return $this->jsonResponse(false, "Unauthorized or no pending {$requestType} approval assigned");
        }

        if (!$this->canSubmitApproval($approvable, $requestType)) {
            return $this->jsonResponse(false, 'Previous approvals required');
        }

        $data = $this->buildApprovalData($approvable, $requestType, $status, $comment);
        $result = $this->updateApproval($data);

        if ($result['success']) {
            $this->updateDocumentStatus($approvable);
        }

        return $result;
    }

    private function buildApprovalData($approvable, string $requestType, string $status, ?string $comment): array
    {
        return [
            'approvable_type' => get_class($approvable),
            'approvable_id'   => $approvable->id,
            'document_name'   => $approvable->reference_no ?? null,
            'request_type'    => $requestType,
            'approval_status' => $status,
            'comment'         => $comment,
            'ordinal'         => $this->getOrdinalForRequestType(get_class($approvable), $requestType),
            'requester_id'    => $approvable->created_by,
            'responder_id'    => Auth::id(),
        ];
    }

    /* ---------------------- Helpers ---------------------- */

    private function jsonResponse(bool $success, string $message, $approval = null): array
    {
        return array_filter([
            'success'  => $success,
            'message'  => $message,
            'approval' => $approval,
        ]);
    }

    private function canSubmitApproval($approvable, $requestType): bool
    {
        $ordinal = $this->getOrdinalForRequestType(get_class($approvable), $requestType);
        if ($ordinal === 1) return true;

        return Approval::where([
            'approvable_type' => get_class($approvable),
            'approvable_id'   => $approvable->id,
            'ordinal'         => $ordinal - 1,
            'approval_status' => 'Approved',
        ])->exists();
    }

    private function getOrdinalForRequestType($approvableType, $requestType): int
    {
        $ordinals = [
            'App\Models\MainStockBeginning' => [
                'review' => 1,
                'check'  => 2,
                'approve'=> 3,
            ],
            'App\Models\StockRequest' => [
                'approve'=> 1,
            ],
        ];
        return $ordinals[$approvableType][$requestType] ?? 1;
    }

    private function updateDocumentStatus($approvable): void
    {
        $approvals = $approvable->approvals;

        $allApproved = $approvals->every(fn($a) => $a->approval_status === 'Approved');
        $anyRejected = $approvals->contains(fn($a) => $a->approval_status === 'Rejected');
        $anyReturned = $approvals->contains(fn($a) => $a->approval_status === 'Returned');

        // Determine new document status
        $newStatus = $anyRejected ? 'Rejected' : ($anyReturned ? 'Returned' : ($allApproved ? 'Approved' : 'Pending'));

        $approvable->update(['status' => $newStatus]);

        Log::debug('Document status updated', [
            'approvable_id' => $approvable->id,
            'new_status'    => $newStatus
        ]);
    }
}
