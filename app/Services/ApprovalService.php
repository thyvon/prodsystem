<?php

namespace App\Services;

use App\Models\Approval;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Collection;
use Exception;

class ApprovalService
{
    protected array $typeLabels = [
        'initial'      => "ផ្ទៀងផ្ទាត់ដោយ<br>Initialed By",
        'check'        => "ត្រួតពិនិត្យដោយ<br>Checked By",
        'approve'      => "អនុម័តដោយ<br>Approved By",
        'review'       => "បានពិនិត្យឡើងវិញដោយ<br>Reviewed By",
        'receive'      => "ទទួលដោយ<br>Received By",
        'verify'       => "បានផ្ទៀងផ្ទាត់ដោយ<br>Verified By",
        'return'       => "ត្រូវបានត្រឡប់ដោយ<br>Returned By",
        'reject'       => "ត្រូវបានបដិសេធដោយ<br>Rejected By",
    ];

    /* ---------------------- Map Approvals for UI ---------------------- */
    public function mapApprovals(Collection $approvals): Collection
    {
        return $approvals->map(function ($a, $key) use ($approvals) {
            if (!$a->responder) return null;
            $sameTypeCount = $approvals->where('request_type', $a->request_type)->count();
            $occurrences   = $approvals->take($key + 1)->where('request_type', $a->request_type)->count();
            $label = $this->typeLabels[$a->request_type] ?? ucfirst(str_replace('_', ' ', $a->request_type));
            if ($sameTypeCount > 1 && $occurrences > 1) {
                $lines = explode('<br>', $label);
                $khmer = $lines[0] ?? '';
                $english = $lines[1] ?? '';
                $khmer = 'រួម-' . $khmer;
                $english = 'Co-' . $english;
                $label = $khmer . '<br>' . $english;
            }

            return [
                'id'                 => $a->id,
                'user_id'            => $a->responder->id,
                'user_profile_url'   => $a->responder->profile_url,
                'user_signature_url' => $a->responder->signature_url,
                'name'               => $a->responder->name,
                'email'              => $a->responder->email,
                'request_type'       => $a->request_type,
                'approval_status'    => $a->approval_status,
                'position_id'        => $a->position_id,
                'position_title'     => $a->responderPosition->title ?? 'N/A',
                'ordinal'            => $a->ordinal,
                'responded_date'     => $a->responded_date,
                'comment'            => $a->comment,
                'request_type_label' => $label,
                'prod_action'        => $a->prod_action,
            ];
        });
    }


    /* ---------------------- Store Approval ---------------------- */
    public function storeApproval(array $data): array
    {
        try {
            $approval = Approval::create([
                'approvable_type'    => $data['approvable_type'],
                'approvable_id'      => $data['approvable_id'],
                'document_reference' => $data['document_reference'] ?? null,
                'document_name'      => $data['document_name'] ?? null,
                'request_type'       => $data['request_type'],
                'approval_status'    => $data['approval_status'] ?? 'Pending',
                'comment'            => $data['comment'] ?? null,
                'ordinal'            => $data['ordinal'] ?? 0,
                'requester_id'       => $data['requester_id'],
                'responder_id'       => $data['responder_id'],
                'position_id'        => $data['position_id'] ?? null,
                'responded_date'     => $data['responded_date'] ?? null, // always null on creation
                'prod_action'        => $data['prod_action'] ?? 0,
            ]);

            return $this->jsonResponse(true, 'Approval created successfully', $approval);

        } catch (Exception $e) {
            Log::error('Failed to create approval', ['error' => $e->getMessage(), 'data' => $data]);
            return $this->jsonResponse(false, "Failed to create approval: {$e->getMessage()}");
        }
    }

    /* ---------------------- Handle Approval Action ---------------------- */
    public function handleApprovalAction($approvable, string $requestType, string $action, ?string $comment = null): array
    {
        $statusMap = [
            'approve' => 'Approved',
            'reject'  => 'Rejected',
            'return'  => 'Returned',
            'prod-verify'=> 'PROD-Verified',
            'receive' => 'Received',
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
            return $this->jsonResponse(false, "No pending {$requestType} approval assigned to this user.");
        }

        try {
            $approval->update([
                'approval_status' => $status,
                'comment'         => $comment ?? $approval->comment,
                'responded_date'  => now(),
            ]);

            return $this->jsonResponse(true, 'Approval processed successfully', $approval);

        } catch (\Exception $e) {
            Log::error('Failed to process approval', ['error' => $e->getMessage(), 'approval_id' => $approval->id]);
            return $this->jsonResponse(false, "Failed to process approval: {$e->getMessage()}");
        }
    }

    /* ---------------------- Helper ---------------------- */
    private function jsonResponse(bool $success, string $message, $approval = null): array
    {
        return array_filter([
            'success'  => $success,
            'message'  => $message,
            'approval' => $approval,
        ]);
    }
}
