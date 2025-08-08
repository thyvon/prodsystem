<?php
namespace App\Http\Controllers;

use App\Models\Approval;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Http\JsonResponse;

class ApprovalController extends Controller
{

    private const ALLOWED_SORT_COLUMNS = [
        'id',
        'document_name',
        'document_reference',
        'request_type',
        'approval_status',
        'ordinal',
        'responded_date',
        'created_at',
        'updated_at',
    ];

    private const DEFAULT_SORT_COLUMN = 'created_at';
    private const DEFAULT_SORT_DIRECTION = 'desc';
    private const DEFAULT_LIMIT = 10;
    private const MAX_LIMIT = 1000;
    private const DATE_FORMAT = 'Y-m-d';


    public function index()
    {
        return view('approval.index');
    }
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
                'document_reference' => $data['document_reference'],
                'request_type' => $data['request_type'],
                'approval_status' => $data['approval_status'],
                'comment' => $data['comment'] ?? null,
                'ordinal' => $data['ordinal'],
                'requester_id' => $data['requester_id'],
                'responder_id' => $data['responder_id'],
                'responded_date' => isset($data['approval_status']) && $data['approval_status'] === 'Approved' ? now() : null,
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
                'responded_date' => now(),
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
            'approval_status' => 'Approved',
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
            'approval_status' => 'Rejected',
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

    public function getApprovals(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'search' => 'nullable|string|max:255',
            'sortColumn' => 'nullable|string|in:' . implode(',', self::ALLOWED_SORT_COLUMNS),
            'sortDirection' => 'nullable|string|in:asc,desc',
            'limit' => 'nullable|integer|min:1|max:' . self::MAX_LIMIT,
            'draw' => 'nullable|integer',
            'page' => 'nullable|integer|min:1',
        ]);

        $search = $validated['search'] ?? null;
        $sortColumn = $validated['sortColumn'] ?? self::DEFAULT_SORT_COLUMN;
        $sortDirection = strtolower($validated['sortDirection'] ?? self::DEFAULT_SORT_DIRECTION);
        $limit = (int) ($validated['limit'] ?? self::DEFAULT_LIMIT);
        $page = (int) ($validated['page'] ?? 1);
        $draw = (int) ($validated['draw'] ?? 1);

        $user = Auth::user();

        $query = Approval::with(['requester:id,name', 'responder:id,name'])
            ->when($search, function ($query, $search) {
                $query->where(function ($q) use ($search) {
                    $q->where('document_name', 'like', "%{$search}%")
                        ->orWhere('request_type', 'like', "%{$search}%")
                        ->orWhere('approval_status', 'like', "%{$search}%")
                        ->orWhere('document_reference', 'like', "%{$search}%")
                        ->orWhereHas('requester', function ($q) use ($search) {
                            $q->where('name', 'like', "%{$search}%");
                        })
                        ->orWhereHas('responder', function ($q) use ($search) {
                            $q->where('name', 'like', "%{$search}%");
                        });
                });
            });

        // Role-based filter: admin sees all, others see their own approvals (pending or responded)
        if (!$user->hasRole('admin')) {
            $query->where('responder_id', $user->id);
        }

        $recordsTotal = $user->hasRole('admin') ? Approval::count() : $query->count();

        // Apply sorting
        $query->orderBy($sortColumn, $sortDirection);

        // Pagination (database-side)
        $approvals = $query->paginate($limit, ['*'], 'page', $page);

        // Post-process for pending approvals with waiting status logic (filter only for non-admin)
        $items = $approvals->getCollection()->map(function ($approval) use ($user) {
            $displayStatus = $approval->approval_status;
            $displayResponseDate = $approval->responded_date;

            // Only check waiting status for non-admin and pending approvals
            $allApprovals = Approval::where('approvable_type', $approval->approvable_type)
                ->where('approvable_id', $approval->approvable_id)
                ->orderBy('ordinal')
                ->orderBy('id')
                ->get();

            // Previous approvals by ordinal and id
            $previousApprovals = $allApprovals->filter(function ($a) use ($approval) {
                return ($a->ordinal < $approval->ordinal) ||
                    ($a->ordinal === $approval->ordinal && $a->id < $approval->id);
            });

            // Check if any previous approval is not approved
            $blockingApproval = $previousApprovals->first(fn($a) => strtolower(trim($a->approval_status)) !== 'approved');
            // Check if any previous approval is rejected
            $blockingApprovalRejected = $previousApprovals->last(fn($a) => strtolower(trim($a->approval_status)) === 'rejected');

            if ($blockingApproval) {
                $displayStatus = 'Waiting ' . ucwords($blockingApproval->request_type);
            }

            if ($blockingApprovalRejected) {
                $displayStatus = 'Rejected by ' . ($blockingApprovalRejected->responder->name ?? 'Unknown');
                $displayResponseDate = $blockingApprovalRejected->responded_date;
            }

            return [
                'id' => $approval->id,
                'approvable_type' => $approval->approvable_type,
                'approvable_id' => $approval->approvable_id,
                'document_name' => $approval->document_name,
                'document_reference' => $approval->document_reference,
                'request_type' => ucwords($approval->request_type),
                'approval_status' => $displayStatus,
                'comment' => $approval->comment,
                'ordinal' => $approval->ordinal,
                'requester_name' => $approval->requester->name ?? null,
                'requester_position' => $approval->requester->defaultPosition()?->title ?? null,
                'requester_department' => $approval->requester?->defaultDepartment()?->name ?? null,
                'responder_name' => $approval->responder->name ?? null,
                'responded_date' => $displayResponseDate,
                'created_at' => $approval->created_at?->toDateTimeString(),
                'updated_at' => $approval->updated_at?->toDateTimeString(),
            ];
        });

        return response()->json([
            'data' => $items,
            'recordsTotal' => $recordsTotal,
            'recordsFiltered' => $approvals->total(),
            'draw' => $draw,
        ]);
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
            'approval_status' => 'Approved',
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
        $allApproved = $approvals->every(fn ($approval) => $approval->approval_status === 'Approved');
        $anyRejected = $approvals->contains(fn ($approval) => $approval->approval_status === 'Rejected');

        $newStatus = 'Pending';
        if ($anyRejected) {
            $newStatus = 'Rejected';
        } elseif ($allApproved) {
            $newStatus = 'Approved';
        }

        $approvable->update(['status' => $newStatus]);
        Log::debug('Document status updated', [
            'approvable_id' => $approvable->id,
            'new_status' => $newStatus,
        ]);
    }

}