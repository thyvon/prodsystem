<?php

namespace App\Services;

use App\Http\Controllers\ApprovalController; // Correct namespace
use App\Models\StockRequest;
use App\Models\StockRequestItem;
use App\Models\User;
use App\Models\Approval;
use App\Models\Campus;
use App\Models\Warehouse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Maatwebsite\Excel\Facades\Excel;
use App\Imports\StockRequestsImport;

class StockRequestService
{
    private const ALLOWED_SORT_COLUMNS = ['request_number', 'request_date', 'created_at', 'updated_at', 'created_by', 'updated_by'];
    private const DEFAULT_SORT_COLUMN = 'created_at';
    private const DEFAULT_SORT_DIRECTION = 'desc';
    private const DEFAULT_LIMIT = 10;
    private const MAX_LIMIT = 1000;
    private const DATE_FORMAT = 'Y-m-d';

    protected $approvalController;

    public function __construct(ApprovalController $approvalController)
    {
        $this->approvalController = $approvalController;
    }

    /**
     * Validate the stock request request data.
     *
     * @param Request $request
     * @param int|null $stockRequestId
     * @return array
     * @throws \Illuminate\Validation\ValidationException
     */
    public function validateRequest(Request $request, ?int $stockRequestId = null): array
    {
        $rules = array_merge(
            $this->stockRequestValidationRules($stockRequestId),
            $this->stockRequestItemValidationRules(),
            [
                'approvals' => 'required|array|min:3',
                'approvals.*.user_id' => 'required|exists:users,id',
                'approvals.*.request_type' => 'required|string|in:review,check,approve',
            ]
        );

        $validated = Validator::make($request->all(), $rules)->validate();

        // Validate that each approver has the appropriate permission
        foreach ($validated['approvals'] as $approval) {
            $user = User::findOrFail($approval['user_id']);
            $permission = "stockRequest.{$approval['request_type']}";
            if (!$user->hasPermissionTo($permission)) {
                throw new \Illuminate\Validation\ValidationException(null, response()->json([
                    'message' => "User ID {$approval['user_id']} does not have permission for {$approval['request_type']}.",
                ], 403));
            }
        }

        // Ensure unique request types and users
        $requestTypes = array_column($validated['approvals'], 'request_type');
        $uniqueRequestTypes = array_unique($requestTypes);
        if (count($uniqueRequestTypes) !== count($requestTypes) || !array_intersect(['review', 'check', 'approve'], $uniqueRequestTypes) == ['review', 'check', 'approve']) {
            throw new \Illuminate\Validation\ValidationException(null, response()->json([
                'message' => 'Exactly one review, check, and approve request type is required.',
            ], 422));
        }

        $userIds = array_column($validated['approvals'], 'user_id');
        if (count(array_unique($userIds)) !== count($userIds)) {
            throw new \Illuminate\Validation\ValidationException(null, response()->json([
                'message' => 'Each approval must be assigned to a unique user.',
            ], 422));
        }

        return $validated;
    }

    /**
     * Create a new stock request with its items and approvals.
     *
     * @param array $validated
     * @return StockRequest
     */
    public function createStockRequest(array $validated): StockRequest
    {
        return DB::transaction(function () use ($validated) {
            $requestNumber = $this->generateRequestNumber($validated['warehouse_id'], $validated['request_date']);

            $stockRequest = StockRequest::create([
                'request_number' => $requestNumber,
                'warehouse_id' => $validated['warehouse_id'],
                'campus_id' => $validated['campus_id'],
                'request_date' => $validated['request_date'],
                'type' => $validated['type'],
                'purpose' => $validated['purpose'] ?? null,
                'approval_status' => 'Pending',
                'created_by' => auth()->id() ?? 1,
            ]);

            $items = array_map(function ($item) use ($stockRequest) {
                return [
                    'stock_request_id' => $stockRequest->id,
                    'product_id' => $item['product_id'],
                    'quantity' => $item['quantity'],
                    'average_price' => $item['average_price'],
                    'total_price' => $item['quantity'] * $item['average_price'],
                    'remarks' => $item['remarks'] ?? null,
                    'created_by' => auth()->id() ?? 1,
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }, $validated['items']);

            StockRequestItem::insert($items);

            $this->initializeApprovals($stockRequest, $validated['approvals']);

            return $stockRequest;
        });
    }

    /**
     * Update an existing stock request with its items and approvals.
     *
     * @param StockRequest $stockRequest
     * @param array $validated
     * @return StockRequest
     */
    public function updateStockRequest(StockRequest $stockRequest, array $validated): StockRequest
    {
        return DB::transaction(function () use ($stockRequest, $validated) {
            // Update stock request header
            $stockRequest->update([
                'warehouse_id' => $validated['warehouse_id'],
                'campus_id' => $validated['campus_id'],
                'request_date' => $validated['request_date'],
                'type' => $validated['type'],
                'purpose' => $validated['purpose'] ?? null,
                'updated_by' => auth()->id() ?? 1,
            ]);

            // Handle approvals
            $existingApprovalKeys = $stockRequest->approvals->map(fn($a) => "{$a->responder_id}|{$a->request_type}")->toArray();
            $newApprovalKeys = collect($validated['approvals'])->map(fn($a) => "{$a['user_id']}|{$a['request_type']}")->toArray();

            // Remove approvals not in the new set
            $approvalsToRemove = array_diff($existingApprovalKeys, $newApprovalKeys);
            foreach ($approvalsToRemove as $approvalKey) {
                [$userId, $requestType] = explode('|', $approvalKey);
                Approval::where([
                    'approvable_type' => StockRequest::class,
                    'approvable_id' => $stockRequest->id,
                    'responder_id' => $userId,
                    'request_type' => $requestType,
                ])->delete();
            }

            // Add new approvals
            $approvalsToAdd = array_diff($newApprovalKeys, $existingApprovalKeys);
            foreach ($approvalsToAdd as $approvalKey) {
                [$userId, $requestType] = explode('|', $approvalKey);
                $approvalData = [
                    'approvable_type' => StockRequest::class,
                    'approvable_id' => $stockRequest->id,
                    'document_name' => 'Stock Request',
                    'document_reference' => $stockRequest->request_number,
                    'request_type' => $requestType,
                    'approval_status' => 'Pending',
                    'ordinal' => $this->getOrdinalForRequestType($requestType),
                    'requester_id' => $stockRequest->created_by,
                    'responder_id' => $userId,
                ];
                $this->approvalController->storeApproval($approvalData);
            }

            // Handle items
            $existingItemIds = $stockRequest->items->pluck('id')->toArray();
            $submittedItemIds = array_filter(array_column($validated['items'], 'id'), fn($id) => !is_null($id));

            // Delete removed items
            StockRequestItem::where('stock_request_id', $stockRequest->id)
                ->whereNotIn('id', $submittedItemIds)
                ->each(function ($item) {
                    $item->deleted_by = auth()->id() ?? 1;
                    $item->save();
                    $item->delete();
                });

            // Process items: update or insert
            $itemsToInsert = [];
            foreach ($validated['items'] as $item) {
                if (!empty($item['id']) && in_array($item['id'], $existingItemIds)) {
                    $stockRequestItem = StockRequestItem::find($item['id']);
                    if ($stockRequestItem) {
                        $stockRequestItem->update([
                            'product_id' => $item['product_id'],
                            'quantity' => $item['quantity'],
                            'average_price' => $item['average_price'],
                            'total_price' => $item['quantity'] * $item['average_price'],
                            'remarks' => $item['remarks'] ?? null,
                            'updated_by' => auth()->id() ?? 1,
                        ]);
                    }
                } else {
                    $itemsToInsert[] = [
                        'stock_request_id' => $stockRequest->id,
                        'product_id' => $item['product_id'],
                        'quantity' => $item['quantity'],
                        'average_price' => $item['average_price'],
                        'total_price' => $item['quantity'] * $item['average_price'],
                        'remarks' => $item['remarks'] ?? null,
                        'created_by' => auth()->id() ?? 1,
                        'updated_by' => auth()->id() ?? 1,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ];
                }
            }

            if (!empty($itemsToInsert)) {
                StockRequestItem::insert($itemsToInsert);
            }

            return $stockRequest;
        });
    }

    /**
     * Delete a stock request and its associated items and approvals.
     *
     * @param StockRequest $stockRequest
     * @return void
     */
    public function deleteStockRequest(StockRequest $stockRequest): void
    {
        DB::transaction(function () use ($stockRequest) {
            $userId = auth()->id() ?? 1;

            // Hard delete related approvals
            Approval::where([
                'approvable_type' => StockRequest::class,
                'approvable_id' => $stockRequest->id,
            ])->delete();

            // Soft delete related items
            foreach ($stockRequest->items as $item) {
                $item->deleted_by = $userId;
                $item->save();
                $item->delete();
            }

            // Soft delete the stock request
            $stockRequest->deleted_by = $userId;
            $stockRequest->save();
            $stockRequest->delete();
        });
    }

    /**
     * Retrieve users with specific approval permissions for a request type.
     *
     * @param Request $request
     * @return array
     */
    public function getUsersForApproval(Request $request): array
    {
        $validated = $request->validate([
            'request_type' => ['required', 'string', 'in:review,check,approve'],
        ]);

        $permission = "stockRequest.{$validated['request_type']}";

        return User::query()
            ->where(function ($query) use ($permission) {
                $query->whereHas('permissions', fn ($q) => $q->where('name', $permission))
                    ->orWhereHas('roles.permissions', fn ($q) => $q->where('name', $permission));
            })
            ->select('id', 'name')
            ->get()
            ->toArray();
    }

    /**
     * Retrieve paginated stock requests with optional search and sort.
     *
     * @param Request $request
     * @return array
     */
    public function getStockRequests(Request $request): array
    {
        $user = auth()->user();
        $isAdmin = $user->hasRole('admin');
        $warehouseIds = $isAdmin ? [] : $user->warehouses->pluck('id')->toArray();

        $validated = $request->validate([
            'search' => 'nullable|string|max:255',
            'sortColumn' => 'nullable|string|in:' . implode(',', self::ALLOWED_SORT_COLUMNS),
            'sortDirection' => 'nullable|string|in:asc,desc',
            'limit' => 'nullable|integer|min:1|max:' . self::MAX_LIMIT,
            'page' => 'nullable|integer|min:1',
            'draw' => 'nullable|integer',
        ]);

        $query = StockRequest::with([
            'warehouse.building.campus',
            'campus',
            'items.product.unit',
            'createdBy',
            'updatedBy',
        ])
            ->when(!$isAdmin, fn($q) => $q->whereIn('warehouse_id', $warehouseIds))
            ->when($validated['search'] ?? null, function ($query, $search) {
                $query->where(function ($q) use ($search) {
                    $q->where('request_number', 'like', "%{$search}%")
                        ->orWhere('type', 'like', "%{$search}%")
                        ->orWhere('purpose', 'like', "%{$search}%")
                        ->orWhereHas('warehouse', fn($q) => $q->where('name', 'like', "%{$search}%"))
                        ->orWhereHas('campus', fn($q) => $q->where('short_name', 'like', "%{$search}%"))
                        ->orWhereHas('items.product', function ($q) use ($search) {
                            $q->where('name', 'like', "%{$search}%")
                                ->orWhere('khmer_name', 'like', "%{$search}%")
                                ->orWhere('description', 'like', "%{$search}%")
                                ->orWhere('item_code', 'like', "%{$search}%")
                                ->orWhereHas('unit', fn($q3) => $q3->where('name', 'like', "%{$search}%"));
                        });
                });
            });

        $recordsTotal = $isAdmin
            ? StockRequest::count()
            : StockRequest::whereIn('warehouse_id', $warehouseIds)->count();

        $recordsFiltered = $query->count();

        $sortColumn = $validated['sortColumn'] ?? self::DEFAULT_SORT_COLUMN;
        $sortDirection = $validated['sortDirection'] ?? self::DEFAULT_SORT_DIRECTION;

        $query->orderBy($sortColumn, $sortDirection);

        $limit = $validated['limit'] ?? self::DEFAULT_LIMIT;
        $page = $validated['page'] ?? 1;

        $stockRequests = $query->paginate($limit, ['*'], 'page', $page);

        $data = $stockRequests->getCollection()->map(function ($item) {
            return [
                'id' => $item->id,
                'request_number' => $item->request_number,
                'request_date' => $item->request_date,
                'type' => $item->type,
                'purpose' => $item->purpose,
                'warehouse_name' => $item->warehouse->name ?? null,
                'campus_name' => $item->campus->short_name ?? null,
                'building_name' => $item->warehouse->building->short_name ?? null,
                'quantity' => round($item->items->sum('quantity'), 4),
                'total_price' => round($item->items->sum('total_price'), 4),
                'created_at' => optional($item->created_at)->toDateTimeString(),
                'updated_at' => optional($item->updated_at)->toDateTimeString(),
                'created_by' => $item->createdBy->name ?? 'System',
                'updated_by' => $item->updatedBy->name ?? 'System',
                'approval_status' => $item->approval_status,
                'items' => $item->items->map(function ($sr) {
                    return [
                        'id' => $sr->id,
                        'product_id' => $sr->product_id,
                        'item_code' => $sr->product->item_code ?? null,
                        'quantity' => $sr->quantity,
                        'average_price' => $sr->average_price,
                        'total_price' => $sr->total_price,
                        'remarks' => $sr->remarks,
                        'product_name' => $sr->product->name ?? null,
                        'product_khmer_name' => $sr->product->khmer_name ?? null,
                        'unit_name' => $sr->product->unit->name ?? null,
                    ];
                })->toArray(),
            ];
        });

        return [
            'data' => $data->all(),
            'recordsTotal' => $recordsTotal,
            'recordsFiltered' => $recordsFiltered,
            'draw' => (int) ($validated['draw'] ?? 1),
        ];
    }

    /**
     * Import stock requests from an Excel file.
     *
     * @param Request $request
     * @return array
     */
    public function importStockRequests(Request $request): array
    {
        $validated = $request->validate([
            'file' => 'required|file|mimes:xlsx,xls,csv|max:2048',
        ]);

        $import = new StockRequestsImport();
        Excel::import($import, $request->file('file'));

        $data = $import->getData();

        if (!empty($data['errors'])) {
            return [
                'message' => 'Errors found in Excel file.',
                'errors' => $data['errors'],
                'status' => 422,
            ];
        }

        if (empty($data['items'])) {
            return [
                'message' => 'No valid data found in the Excel file.',
                'errors' => ['No valid rows processed.'],
                'status' => 422,
            ];
        }

        return [
            'message' => 'Stock request data parsed successfully.',
            'data' => [
                'request_date' => $data['request_date'] ?? null,
                'campus_id' => $data['campus_id'] ?? null,
                'warehouse_id' => $data['warehouse_id'] ?? null,
                'request_number' => $data['request_number'] ?? null,
                'type' => $data['type'] ?? null,
                'purpose' => $data['purpose'] ?? null,
                'approval_status' => $data['approval_status'] ?? 'Pending',
                'items' => $data['items'],
            ],
            'status' => 200,
        ];
    }

    /**
     * Submit an approval action (approve or reject) for a stock request.
     *
     * @param Request $request
     * @param StockRequest $stockRequest
     * @return array
     */
    public function submitApproval(Request $request, StockRequest $stockRequest): array
    {
        $validated = $request->validate([
            'request_type' => 'required|string|in:review,check,approve',
            'action' => 'required|string|in:approve,reject',
            'comment' => 'nullable|string|max:1000',
        ]);

        $permission = "stockRequest.{$validated['request_type']}";
        if (!auth()->user()->can($permission)) {
            return [
                'message' => "You do not have permission to {$validated['request_type']} this stock request.",
                'status' => 403,
            ];
        }

        $method = $validated['action'] === 'approve' ? 'confirmApproval' : 'rejectApproval';
        $result = $this->approvalController->$method(
            $request,
            StockRequest::class,
            $stockRequest->id,
            $validated['request_type']
        );

        if ($result['success']) {
            $this->approvalController->updateDocumentStatus($stockRequest);
        }

        return [
            'message' => $result['message'],
            'redirect_url' => route('stock-requests.show', $stockRequest->id),
            'approval' => $result['approval'] ?? null,
            'status' => $result['success'] ? 200 : 400,
        ];
    }

    /**
     * Reassign a responder for a specific request type.
     *
     * @param Request $request
     * @param StockRequest $stockRequest
     * @return array
     */
    public function reassignResponder(Request $request, StockRequest $stockRequest): array
    {
        $validated = $request->validate([
            'request_type' => 'required|string|in:review,check,approve',
            'new_user_id' => 'required|exists:users,id',
            'comment' => 'nullable|string|max:1000',
        ]);

        $user = User::findOrFail($validated['new_user_id']);
        $permission = "stockRequest.{$validated['request_type']}";
        if (!$user->hasPermissionTo($permission)) {
            return [
                'message' => "User ID {$validated['new_user_id']} does not have permission for {$validated['request_type']}.",
                'success' => false,
                'status' => 403,
            ];
        }

        $result = $this->approvalController->reassignResponder(
            $request,
            StockRequest::class,
            $stockRequest->id,
            $validated['request_type']
        );

        return [
            'message' => $result['message'],
            'success' => $result['success'],
            'status' => $result['success'] ? 200 : 400,
        ];
    }

    /**
     * List approvals for a specific stock request.
     *
     * @param Request $request
     * @param StockRequest $stockRequest
     * @return array
     */
    public function listApprovals(Request $request, StockRequest $stockRequest): array
    {
        $result = $this->approvalController->getApprovals($request);

        return [
            'message' => $result['message'] ?? 'Approvals retrieved successfully.',
            'approvals' => $result['data'] ?? [],
            'status' => $result['success'] ?? true ? 200 : 403,
        ];
    }

    /**
     * Determine if the authenticated user can see and interact with the approval button.
     *
     * @param int $stockRequestId
     * @return array
     */
    public function canShowApprovalButton(int $stockRequestId): array
    {
        try {
            $stockRequest = StockRequest::findOrFail($stockRequestId);
            $userId = auth()->id();
            if (!$userId) {
                return [
                    'message' => 'Approval button not available: User not authenticated.',
                    'showButton' => false,
                    'requestType' => null,
                ];
            }

            $hasPermission = auth()->user()->hasAnyPermission([
                'stockRequest.review',
                'stockRequest.check',
                'stockRequest.approve',
            ]);
            if (!$hasPermission) {
                return [
                    'message' => 'Approval button not available: User lacks approval permissions.',
                    'showButton' => false,
                    'requestType' => null,
                ];
            }

            $approvals = Approval::where([
                'approvable_type' => StockRequest::class,
                'approvable_id' => $stockRequestId,
            ])
                ->orderBy('ordinal', 'asc')
                ->orderBy('id', 'asc')
                ->get();

            if ($approvals->isEmpty()) {
                return [
                    'message' => 'Approval button not available: No approvals configured.',
                    'showButton' => false,
                    'requestType' => null,
                ];
            }

            $currentApproval = $approvals->firstWhere('approval_status', 'Pending');
            if (!$currentApproval) {
                return [
                    'message' => 'Approval button not available: All approvals completed or none pending.',
                    'showButton' => false,
                    'requestType' => null,
                ];
            }

            if ($currentApproval->responder_id !== $userId) {
                return [
                    'message' => 'Approval button not available: User is not the assigned responder.',
                    'showButton' => false,
                    'requestType' => null,
                ];
            }

            $previousApprovals = $approvals->filter(function ($approval) use ($currentApproval) {
                return ($approval->ordinal < $currentApproval->ordinal) ||
                    ($approval->ordinal === $currentApproval->ordinal && $approval->id < $currentApproval->id);
            });

            $anyPreviousRejected = $previousApprovals->contains(function ($approval) {
                return strtolower(trim($approval->approval_status)) === 'rejected';
            });

            if ($anyPreviousRejected) {
                return [
                    'message' => 'Approval button not available: A previous approval was rejected.',
                    'showButton' => false,
                    'requestType' => null,
                ];
            }

            $allPreviousApproved = $previousApprovals->every(function ($approval) {
                return strtolower(trim($approval->approval_status)) === 'approved';
            });

            if (!$allPreviousApproved) {
                return [
                    'message' => 'Approval button not available: Previous approval steps are not completed.',
                    'showButton' => false,
                    'requestType' => null,
                ];
            }

            return [
                'message' => 'Approval button available.',
                'showButton' => true,
                'requestType' => $currentApproval->request_type,
            ];
        } catch (\Exception $e) {
            Log::error('Failed to check approval button visibility', [
                'stock_request_id' => $stockRequestId,
                'user_id' => auth()->id(),
                'error' => $e->getMessage(),
            ]);
            return [
                'message' => 'Failed to check approval button visibility',
                'showButton' => false,
                'requestType' => null,
            ];
        }
    }

    /**
     * Initialize approvals for a stock request.
     *
     * @param StockRequest $stockRequest
     * @param array $approvals
     * @return void
     */
    protected function initializeApprovals(StockRequest $stockRequest, array $approvals): void
    {
        foreach ($approvals as $approval) {
            $approvalData = [
                'approvable_type' => StockRequest::class,
                'approvable_id' => $stockRequest->id,
                'document_name' => 'Stock Request',
                'document_reference' => $stockRequest->request_number,
                'request_type' => $approval['request_type'],
                'approval_status' => 'Pending',
                'ordinal' => $this->getOrdinalForRequestType($approval['request_type']),
                'requester_id' => $stockRequest->created_by,
                'responder_id' => $approval['user_id'],
            ];
            $this->approvalController->storeApproval($approvalData);
        }
    }

    /**
     * Get ordinal for a request type.
     *
     * @param string $requestType
     * @return int
     */
    protected function getOrdinalForRequestType(string $requestType): int
    {
        $ordinals = ['review' => 1, 'check' => 2, 'approve' => 3];
        return $ordinals[$requestType] ?? 1;
    }

    /**
     * Generate a unique request number in format STR-short_name-mmyyyy-sequence.
     *
     * @param int $warehouseId
     * @param string $requestDate
     * @return string
     * @throws \InvalidArgumentException If the date format is invalid or warehouse is not found.
     */
    protected function generateRequestNumber(int $warehouseId, string $requestDate): string
    {
        $warehouse = Warehouse::with('building.campus')->findOrFail($warehouseId);

        try {
            $date = \Carbon\Carbon::createFromFormat(self::DATE_FORMAT, $requestDate);
            if (!$date || $date->format(self::DATE_FORMAT) !== $requestDate) {
                throw new \InvalidArgumentException('Invalid date format. Expected ' . self::DATE_FORMAT . '.');
            }
        } catch (\Exception $e) {
            throw new \InvalidArgumentException('Invalid date format. Expected ' . self::DATE_FORMAT . '.', 0, $e);
        }

        $shortName = $warehouse->building?->campus?->short_name ?? 'WH';
        $monthYear = $date->format('my');
        $sequence = $this->getSequenceNumber($shortName, $monthYear);

        return "STR-{$shortName}-{$monthYear}-{$sequence}";
    }

    /**
     * Generate a sequence number for uniqueness, including soft-deleted records.
     *
     * @param string $shortName
     * @param string $monthYear
     * @return string
     */
    protected function getSequenceNumber(string $shortName, string $monthYear): string
    {
        $prefix = "STR-{$shortName}-{$monthYear}-";

        $count = StockRequest::withTrashed()
            ->where('request_number', 'like', "{$prefix}%")
            ->count();

        return str_pad($count + 1, 2, '0', STR_PAD_LEFT);
    }

    /**
     * Get validation rules for stock request creation/update.
     *
     * @param int|null $stockRequestId
     * @return array
     */
    protected function stockRequestValidationRules(?int $stockRequestId = null): array
    {
        return [
            'request_date' => ['required', 'date', 'date_format:' . self::DATE_FORMAT],
            'warehouse_id' => ['required', 'integer', 'exists:warehouses,id'],
            'campus_id' => ['required', 'integer', 'exists:campus,id'],
            'type' => ['required', 'string', 'in:issue,transfer,replenishment'],
            'purpose' => ['nullable', 'string', 'max:1000'],
            'approval_status' => ['required', 'string', 'in:Pending,Reviewed,Checked,Approved,Rejected'],
        ];
    }

    /**
     * Get validation rules for stock request items.
     *
     * @return array
     */
    protected function stockRequestItemValidationRules(): array
    {
        return [
            'items' => ['required', 'array', 'min:1'],
            'items.*.id' => ['nullable', 'integer', 'exists:stock_request_items,id'],
            'items.*.product_id' => ['required', 'integer', 'exists:products,id'],
            'items.*.quantity' => ['required', 'numeric', 'min:0.01'],
            'items.*.average_price' => ['required', 'numeric', 'min:0'],
            'items.*.remarks' => ['nullable', 'string', 'max:1000'],
        ];
    }
}