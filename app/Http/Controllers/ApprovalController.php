<?php

namespace App\Http\Controllers;

use App\Models\Approval;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Http\JsonResponse;

class ApprovalController extends Controller
{
    private const ALLOWED_SORT_COLUMNS = [
        'id', 'document_name', 'document_reference',
        'request_type', 'approval_status', 'ordinal',
        'responded_date', 'created_at', 'updated_at',
    ];

    private const DEFAULT_SORT_COLUMN = 'created_at';
    private const DEFAULT_SORT_DIRECTION = 'desc';
    private const DEFAULT_LIMIT = 10;
    private const MAX_LIMIT = 1000;

    /* ---------------------- Views ---------------------- */

    public function index()
    {
        return view('approval.index');
    }

    /* ---------------------- Create / Update ---------------------- */

    /**
     * Store a new approval.
     */
    public function storeApproval(array $data): array
    {
        try {
            if (empty($data['position_id']) && !empty($data['responder_id'])) {
                $responder = User::find($data['responder_id']);
                $data['position_id'] = $responder?->defaultPosition()?->id;
            }

            $approval = Approval::create([
                'approvable_type'   => $data['approvable_type'],
                'approvable_id'     => $data['approvable_id'],
                'document_name'     => $data['document_name'],
                'document_reference'=> $data['document_reference'],
                'request_type'      => $data['request_type'],
                'approval_status'   => $data['approval_status'],
                'comment'           => $data['comment'] ?? null,
                'ordinal'           => $data['ordinal'],
                'requester_id'      => $data['requester_id'],
                'responder_id'      => $data['responder_id'],
                'position_id'       => $data['position_id'] ?? null,
                'responded_date'    => $data['approval_status'] === 'Approved' ? now() : null,
            ]);
            return $this->jsonResponse(true, 'Approval created successfully', $approval);
        } catch (\Exception $e) {
            Log::error('Failed to create approval', ['error' => $e->getMessage(), 'data' => $data]);
            return $this->jsonResponse(false, "Failed to create approval: {$e->getMessage()}");
        }
    }

    /**
     * Update an existing approval.
     */
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
                Log::debug('Approval update rejected: Not pending', ['approval_id' => $approval->id]);
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
        } catch (\Exception $e) {
            Log::error('Failed to update approval', ['error' => $e->getMessage(), 'data' => $data]);
            return $this->jsonResponse(false, "Failed to update approval: {$e->getMessage()}");
        }
    }

    /* ---------------------- Approve / Reject ---------------------- */

    /**
     * Approve a pending approval.
     */
    public function confirmApproval(Request $request, $approvableType, $approvableId, $requestType): array
    {
        return $this->handleApprovalAction($request, $approvableType, $approvableId, $requestType, 'Approved');
    }

    /**
     * Reject a pending approval.
     */
    public function rejectApproval(Request $request, $approvableType, $approvableId, $requestType): array
    {
        return $this->handleApprovalAction($request, $approvableType, $approvableId, $requestType, 'Rejected');
    }

    /**
     * Generic handler for approve/reject.
     */
    private function handleApprovalAction(Request $request, $approvableType, $approvableId, $requestType, string $status): array
    {
        $request->validate(['comment' => 'nullable|string|max:1000']);

        $approvable = $this->findApprovable($approvableType, $approvableId);

        $approval = Approval::where([
            'approvable_type' => $approvableType,
            'approvable_id'   => $approvableId,
            'request_type'    => $requestType,
            'responder_id'    => Auth::id(),
            'approval_status' => 'Pending',
        ])->first();

        if (!$approval) {
            Log::debug('Approval action failed: Unauthorized or no pending', [
                'user_id' => Auth::id(),
                'approvable_id' => $approvableId,
                'request_type' => $requestType,
            ]);
            return $this->jsonResponse(false, "Unauthorized or no pending {$requestType} approval assigned");
        }

        if (!$this->canSubmitApproval($approvable, $requestType)) {
            Log::debug('Approval action blocked: Previous approvals required', [
                'approvable_id' => $approvableId,
                'request_type' => $requestType,
            ]);
            return $this->jsonResponse(false, 'Previous approvals required');
        }

        $data = $this->buildApprovalData($approvable, $requestType, $status, $request->comment);
        $result = $this->updateApproval($data);

        if ($result['success']) {
            $this->updateDocumentStatus($approvable);
        }

        return $result;
    }

    /**
     * Build approval update data array.
     */
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

    /**
     * Consistent JSON response helper.
     */
    private function jsonResponse(bool $success, string $message, $approval = null): array
    {
        return array_filter([
            'success'  => $success,
            'message'  => $message,
            'approval' => $approval,
        ]);
    }

    /* ---------------------- Get / List Approvals ---------------------- */

    // public function getApprovals(Request $request): JsonResponse
    // {
    //     $validated = $request->validate([
    //         'search'        => 'nullable|string|max:255',
    //         'sortColumn'    => 'nullable|string|in:' . implode(',', self::ALLOWED_SORT_COLUMNS),
    //         'sortDirection' => 'nullable|string|in:asc,desc',
    //         'limit'         => 'nullable|integer|min:1|max:' . self::MAX_LIMIT,
    //         'draw'          => 'nullable|integer',
    //         'page'          => 'nullable|integer|min:1',
    //     ]);

    //     $search        = $validated['search'] ?? null;
    //     $sortColumn    = $validated['sortColumn'] ?? self::DEFAULT_SORT_COLUMN;
    //     $sortDirection = strtolower($validated['sortDirection'] ?? self::DEFAULT_SORT_DIRECTION);
    //     $limit         = (int) ($validated['limit'] ?? self::DEFAULT_LIMIT);
    //     $page          = (int) ($validated['page'] ?? 1);
    //     $draw          = (int) ($validated['draw'] ?? 1);

    //     $user = Auth::user();

    //     $query = Approval::with(['requester:id,name', 'responder:id,name'])
    //         ->when($search, function ($query, $search) {
    //             $query->where(function ($q) use ($search) {
    //                 $q->where('document_name', 'like', "%{$search}%")
    //                     ->orWhere('request_type', 'like', "%{$search}%")
    //                     ->orWhere('approval_status', 'like', "%{$search}%")
    //                     ->orWhere('document_reference', 'like', "%{$search}%")
    //                     ->orWhereHas('requester', fn($q) => $q->where('name', 'like', "%{$search}%"))
    //                     ->orWhereHas('responder', fn($q) => $q->where('name', 'like', "%{$search}%"));
    //             });
    //         });

    //     if (!$user->hasRole('admin')) {
    //         $query->where('responder_id', $user->id);
    //     }

    //     $recordsTotal = $user->hasRole('admin') ? Approval::count() : $query->count();
    //     $query->orderBy($sortColumn, $sortDirection);
    //     $approvals = $query->paginate($limit, ['*'], 'page', $page);

    //     $items = $approvals->getCollection()->map(fn($approval) => $this->formatApprovalForList($approval, $user));

    //     return response()->json([
    //         'data'            => $items,
    //         'recordsTotal'    => $recordsTotal,
    //         'recordsFiltered' => $approvals->total(),
    //         'draw'            => $draw,
    //     ]);
    // }

    public function getApprovals(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'search'        => 'nullable|string|max:255',
            'sortColumn'    => 'nullable|string|in:' . implode(',', self::ALLOWED_SORT_COLUMNS),
            'sortDirection' => 'nullable|string|in:asc,desc',
            'limit'         => 'nullable|integer|min:1|max:' . self::MAX_LIMIT,
            'draw'          => 'nullable|integer',
            'page'          => 'nullable|integer|min:1',
            'filterType'    => 'nullable|string|in:all,pending,completed,upcoming,returned,rejected',
        ]);

        $search        = $validated['search'] ?? null;
        $sortColumn    = $validated['sortColumn'] ?? self::DEFAULT_SORT_COLUMN;
        $sortDirection = strtolower($validated['sortDirection'] ?? self::DEFAULT_SORT_DIRECTION);
        $limit         = (int) ($validated['limit'] ?? self::DEFAULT_LIMIT);
        $page          = (int) ($validated['page'] ?? 1);
        $draw          = (int) ($validated['draw'] ?? 1);
        $filterType    = $validated['filterType'] ?? null;

        $user = Auth::user();

        $query = Approval::with(['requester:id,name', 'responder:id,name'])
            ->when($search, function ($query, $search) {
                $query->where(function ($q) use ($search) {
                    $q->where('document_name', 'like', "%{$search}%")
                    ->orWhere('request_type', 'like', "%{$search}%")
                    ->orWhere('approval_status', 'like', "%{$search}%")
                    ->orWhere('document_reference', 'like', "%{$search}%")
                    ->orWhereHas('requester', fn($q) => $q->where('name', 'like', "%{$search}%"))
                    ->orWhereHas('responder', fn($q) => $q->where('name', 'like', "%{$search}%"));
                });
            });

        // Filter only approvals for the current user or requested by the user
        $query->where(function ($q) use ($user) {
            $q->where('responder_id', $user->id)
            ->orWhere(function ($qq) use ($user) {
                $qq->where('requester_id', $user->id)
                    ->whereIn('approval_status', ['returned', 'rejected']);
            });
        });

        $query->orderBy($sortColumn, $sortDirection);

        $approvalsAll = $query->get();
        $mappedApprovals = $approvalsAll->map(fn($approval) => $this->formatApprovalForList($approval, $user));

        // -----------------------
        // Status counts based on $displayStatus
        // -----------------------
        $statusCounts = [
            'all'       => $mappedApprovals->count(),
            'upcoming'  => $mappedApprovals->filter(fn($a) => str_contains(strtolower($a['approval_status']), 'waiting'))->count(),
            'completed' => $mappedApprovals->filter(fn($a) =>
                strtolower($a['approval_status']) === 'done' ||
                str_contains(strtolower($a['approval_status']), 'rejected') ||
                str_contains(strtolower($a['approval_status']), 'returned')
            )->count(),
            'pending'   => $mappedApprovals->filter(fn($a) =>
                !str_contains(strtolower($a['approval_status']), 'waiting') &&
                strtolower($a['approval_status']) !== 'done' &&
                !str_contains(strtolower($a['approval_status']), 'rejected') &&
                !str_contains(strtolower($a['approval_status']), 'returned')
            )->count(),
            'returned'  => $mappedApprovals->filter(fn($a) =>
                str_contains(strtolower($a['approval_status']), 'returned')
            )->count(),
            'rejected'  => $mappedApprovals->filter(fn($a) =>
                str_contains(strtolower($a['approval_status']), 'rejected')
            )->count(),
        ];

        // -----------------------
        // Apply filter if selected
        // -----------------------
        if ($filterType && $filterType !== 'all') {
            $mappedApprovals = match ($filterType) {
                'upcoming'  => $mappedApprovals->filter(
                    fn($a) => str_contains(strtolower($a['approval_status']), 'waiting')
                ),

                'completed' => $mappedApprovals->filter(fn($a) =>
                    strtolower($a['approval_status']) === 'done' ||
                    str_contains(strtolower($a['approval_status']), 'rejected') ||
                    str_contains(strtolower($a['approval_status']), 'returned')
                ),

                'pending'   => $mappedApprovals->filter(fn($a) =>
                    !str_contains(strtolower($a['approval_status']), 'waiting') &&
                    strtolower($a['approval_status']) !== 'done' &&
                    !str_contains(strtolower($a['approval_status']), 'rejected') &&
                    !str_contains(strtolower($a['approval_status']), 'returned')
                ),

                // ✅ NEW: Returned (Requester only)
                'returned'  => $mappedApprovals->filter(fn($a) =>
                    str_contains(strtolower($a['approval_status']), 'returned')
                ),

                'rejected'  => $mappedApprovals->filter(fn($a) =>
                    str_contains(strtolower($a['approval_status']), 'rejected')
                ),

                default => $mappedApprovals,
            };
        }

        $paginated = $mappedApprovals->forPage($page, $limit);

        return response()->json([
            'data'            => $paginated->values(),
            'recordsTotal'    => $mappedApprovals->count(),
            'recordsFiltered' => $mappedApprovals->count(),
            'statusCounts'    => $statusCounts,
            'draw'            => $draw,
        ]);
    }

    /**
     * Format approval for list view with waiting/rejected logic.
     */
    private function formatApprovalForList($approval, $user): array
    {
        // Initialize display status and response date
        $displayStatus = $approval->approval_status;
        $displayResponseDate = $approval->responded_date;

        // Eager load requester relationships
        $approval->load([
            'requester.defaultPosition',
            'requester.defaultDepartment',
            'requester.defaultCampus',
            'responder'
        ]);

        // Fetch all approvals for the same document
        $allApprovals = Approval::where('approvable_type', $approval->approvable_type)
            ->where('approvable_id', $approval->approvable_id)
            ->orderBy('ordinal')
            ->orderBy('id')
            ->get();

        // Filter previous approvals
        $previous = $allApprovals->filter(fn($a) =>
            $a->ordinal < $approval->ordinal ||
            ($a->ordinal === $approval->ordinal && $a->id < $approval->id)
        );

        // Determine blocking approvals
        $blockingApproval = $previous->first(fn($a) => strtolower(trim($a->approval_status)) !== 'approved');
        $blockingRejected = $previous->last(fn($a) => strtolower(trim($a->approval_status)) === 'rejected');
        $blockingReturned = $previous->last(fn($a) => strtolower(trim($a->approval_status)) === 'returned');

        if ($blockingApproval) {
            $displayStatus = 'Waiting ' . ucwords($blockingApproval->request_type);
        }

        if ($blockingRejected) {
            $displayStatus = 'Rejected by ' . ($blockingRejected->responder?->name ?? 'Unknown');
            $displayResponseDate = $blockingRejected->responded_date;
        }

        if ($blockingReturned) {
            $displayStatus = 'Returned by ' . ($blockingReturned->responder?->name ?? 'Unknown');
            $displayResponseDate = $blockingReturned->responded_date;
        }

        // Map "Approved" to "Done"
        if (strtolower($displayStatus) === 'approved') {
            $displayStatus = 'Done';
        }

        // Get requester safely
        $requester = $approval->requester;

        // Return mapped approval data
        return [
            'id'                  => $approval->id,
            'approvable_type'     => $approval->approvable_type,
            'approvable_id'       => $approval->approvable_id,
            'document_name'       => $approval->document_name,
            'document_reference'  => $approval->document_reference,
            'request_type'        => ucwords($approval->request_type),
            'approval_status'     => $displayStatus,
            'comment'             => $approval->comment,
            'ordinal'             => $approval->ordinal,
            'requester_name'      => $requester?->name,
            'requester_position'  => $requester?->defaultPosition?->title,
            'requester_department'=> $requester?->defaultDepartment?->short_name,
            'requester_campus'    => $requester?->defaultCampus?->short_name,
            'responder_name'      => $approval->responder?->name,
            'responded_date'      => $displayResponseDate,
            'created_at'          => $approval->created_at?->toDateTimeString(),
            'updated_at'          => $approval->updated_at?->toDateTimeString(),
        ];
    }

    /* ---------------------- Helpers ---------------------- */

    protected function findApprovable($type, $id)
    {
        if (!class_exists($type)) {
            throw new \Exception("Model {$type} does not exist");
        }
        return $type::findOrFail($id);
    }

    protected function canSubmitApproval($approvable, $requestType)
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

    protected function getOrdinalForRequestType($approvableType, $requestType): int
    {
        $ordinals = [
            'App\Models\MainStockBeginning' => [
                'review' => 1,
                'check'  => 2,
                'approve'=> 3,
            ],
        ];
        return $ordinals[$approvableType][$requestType] ?? 1;
    }

    protected function updateDocumentStatus($approvable): void
    {
        $approvals = $approvable->approvals;
        $allApproved = $approvals->every(fn($a) => $a->approval_status === 'Approved');
        $anyRejected = $approvals->contains(fn($a) => $a->approval_status === 'Rejected');

        $newStatus = $anyRejected ? 'Rejected' : ($allApproved ? 'Approved' : 'Pending');
        $approvable->update(['status' => $newStatus]);

        Log::debug('Document status updated', ['approvable_id' => $approvable->id, 'new_status' => $newStatus]);
    }
}
