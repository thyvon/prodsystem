<?php

namespace App\Http\Controllers;

use App\Models\StockRequest;
use App\Models\StockRequestItem;
use App\Models\Warehouse;
use App\Models\Campus;
use App\Models\User;
use App\Models\Approval;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Http\JsonResponse;
use Maatwebsite\Excel\Facades\Excel;
use App\Exports\StockRequestExport;
// use App\Imports\StockBeginningsImport;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use App\Services\WarehouseService;
use App\Services\ProductService;
use App\Services\CampusService;
use App\Services\ApprovalService;
use App\Services\DepartmentService;
use App\Http\Resources\StockRequestCollection;

class StockRequestController extends Controller
{
    // Constants for sort columns and default values
    private const ALLOWED_SORT_COLUMNS = ['request_date', 'request_number', 'created_at', 'updated_at', 'created_by', 'updated_by'];
    private const DEFAULT_SORT_COLUMN = 'created_at';
    private const DEFAULT_SORT_DIRECTION = 'desc';
    private const DEFAULT_LIMIT = 10;
    private const MAX_LIMIT = 1000;
    private const DATE_FORMAT = 'Y-m-d';

    protected $warehouseService;
    protected $productService;
    protected $campusService;
    protected $departmentService;
    protected $approvalService;

    public function __construct(
        WarehouseService $warehouseService,
        ProductService $productService,
        CampusService $campusService,
        DepartmentService $departmentService,
        ApprovalService $approvalService
    ) {
        $this->warehouseService = $warehouseService;
        $this->productService = $productService;
        $this->campusService = $campusService;
        $this->departmentService = $departmentService;
        $this->approvalService = $approvalService;
    }

    /**
     * Display the stock requests index view.
     *
     * @return \Illuminate\View\View
     */
    public function index()
    {
        $this->authorize('viewAny', StockRequest::class);
        return view('Inventory.stockRequest.index');
    }

    /**
     * Show the form for creating a new main stock request.
     *
     * @return \Illuminate\View\View
     */
    public function create()
    {
        $this->authorize('create', StockRequest::class);

        $user = auth()->user();

        // Get all departments & campuses for the user
        $departments = $user->departments; // Collection of Department models
        $campuses = $user->campus;         // Collection of Campus models

        // Get default department & campus
        $defaultDepartment = $user->defaultDepartment(); // single Department model or null
        $defaultCampus = $user->defaultCampus();         // single Campus model or null

        return view('Inventory.stockRequest.form', compact(
            'departments',
            'campuses',
            'defaultDepartment',
            'defaultCampus'
        ));
    }

    /**
     * Display a single main stock request with its line items and approvals for printing.
     *
     * @param StockRequest $stockRequest
     * @return \Illuminate\View\View
     */
    public function show(StockRequest $stockRequest)
    {
        $this->authorize('view', $stockRequest);

        try {
            // Load related data including approvals
            $stockRequest->load([
                'stockRequestItems.productVariant.product.unit',
                'stockRequestItems.campus',
                'stockRequestItems.department',
                'warehouse.building.campus',
                'createdBy',
                'updatedBy',
                'campus',
                'creatorPosition',
                'approvals.responder',
                'approvals.responderPosition',
            ]);

            // Check if the approval button should be shown
            $approvalButtonData = $this->canShowApprovalButton($stockRequest->id);

            // Derive responders from approvals
            $responders = $stockRequest->approvals->map(function ($approval) {
                return [
                    'id' => $approval->id,
                    'user_id' => $approval->responder_id,
                    'position_id' => $approval->position_id,
                    'request_type' => $approval->request_type,
                    'name' => $approval->responder->name ?? 'N/A',
                ];
            })->toArray();

            return view('Inventory.stockRequest.show', [
                'stockRequest' => $stockRequest,
                'totalQuantity' => round($stockRequest->stockRequestItems->sum('quantity'), 4),
                'totalValue' => round($stockRequest->stockRequestItems->sum('total_price'), 4),
                'approvals' => $stockRequest->approvals
                    ->sortBy('ordinal')
                    ->values()
                    ->map(function ($approval) {
                        return [
                            'id' => $approval->id,
                            'request_type' => $approval->request_type,
                            'approval_status' => $approval->approval_status,
                            'responder_name' => $approval->responder->name ?? 'N/A',
                            'responder_profile_url' => $approval->responder->profile_url ?? 'N/A',
                            'responder_signature_url' => $approval->responder->signature_url ?? 'N/A',
                            'position_name' => $approval->responderPosition->title ?? 'N/A',
                            'ordinal' => $approval->ordinal,
                            'comment' => $approval->comment,
                            'created_at' => $approval->created_at?->toDateTimeString(),
                            'updated_at' => $approval->updated_at?->toDateTimeString(),
                            'responded_date' => $approval->responded_date,
                        ];
                })->toArray(),
                'responders' => $responders,
                'showApprovalButton' => $approvalButtonData['showButton'],
                'approvalRequestType' => $approvalButtonData['requestType'],
                'approvalButtonData' => $approvalButtonData,
            ]);
        } catch (\Exception $e) {
            Log::error('Error fetching stock request for display', [
                'error_message' => $e->getMessage(),
                'stock_request_id' => $stockRequest->id,
            ]);
            return response()->view('errors.500', [
                'message' => 'Failed to fetch stock request',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Store a new main stock request with its line items and approvals.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function store(Request $request): JsonResponse
    {
        $this->authorize('create', StockRequest::class);

        $validated = Validator::make($request->all(), array_merge(
            $this->stockRequestValidationRules(),
            $this->stockRequestItemValidationRules(),
            [
                'approvals' => 'required|array|min:1',
                'approvals.*.user_id' => 'required|exists:users,id',
                'approvals.*.request_type' => 'required|string|in:approve',
            ]
        ))->validate();

        // Validate that each approver has the appropriate permission
        foreach ($validated['approvals'] as $approval) {
            $user = User::findOrFail($approval['user_id']);
            $permission = "stockRequest.{$approval['request_type']}";
            if (!$user->hasPermissionTo($permission)) {
                return response()->json([
                    'message' => "User ID {$approval['user_id']} does not have permission for {$approval['request_type']}.",
                ], 403);
            }
        }

        try {
            return DB::transaction(function () use ($validated) {
                $referenceNo = $this->generateReferenceNo($validated['warehouse_id'], $validated['request_date']);
                $userCampus = auth()->user()->defaultCampus(); // returns model or null
                $userPosition = auth()->user()->defaultPosition(); // returns model or null

                if (!$userCampus) {
                    return response()->json([
                        'message' => 'No default campus assigned to this user.',
                    ], 404);
                }
                if (!$userPosition) {
                    return response()->json([
                        'message' => 'No default position assigned to this user.',
                    ], 404);
                }

                $stockRequest = StockRequest::create([
                    'request_number' => $referenceNo,
                    'warehouse_id' => $validated['warehouse_id'],
                    'campus_id' => $userCampus->id,
                    'position_id' => $userPosition->id,
                    'type' => $validated['type'],
                    'purpose' => $validated['purpose'] ?? null,
                    'request_date' => $validated['request_date'],
                    'created_by' => auth()->id() ?? 1,
                    'approval_status' => 'Pending',
                ]);

                $items = array_map(function ($item) use ($stockRequest) {
                    return [
                        'stock_request_id' => $stockRequest->id,
                        'product_id' => $item['product_id'],
                        'campus_id' => $item['campus_id'],
                        'department_id' => $item['department_id'],
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

                $this->storeApprovals($stockRequest, $validated['approvals']);

                return response()->json([
                    'message' => 'Stock request created successfully.',
                    'data' => $stockRequest->load('stockRequestItems', 'approvals.responder'),
                ], 201);
            });
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to create stock request', ['error' => $e->getMessage()]);
            return response()->json([
                'message' => 'Failed to create stock request',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Show the form for editing an existing main stock request.
     *
     * @param StockRequest $stockRequest
     * @return \Illuminate\View\View
     */
    public function edit(StockRequest $stockRequest)
    {
        $this->authorize('update', $stockRequest);

        try {
            $user = auth()->user();

            // Eager load all relations needed for the form
            $stockRequest->load([
                'stockRequestItems.productVariant.product.unit',
                'stockRequestItems.department',
                'stockRequestItems.campus',
                'warehouse',
                'campus',
                'approvals.responder',
            ]);

            // User-specific departments & campuses
            $departments = $user->departments()->select('departments.id', 'departments.name', 'departments.short_name')->get();
            $campuses = $user->campus()->select('campus.id', 'campus.name', 'campus.short_name')->get();

            $defaultDepartment = $user->defaultDepartment();
            $defaultCampus = $user->defaultCampus();

            // Prepare data payload for Vue
            $stockRequestData = [
                'id' => $stockRequest->id,
                'request_number' => $stockRequest->request_number,
                'warehouse_id' => $stockRequest->warehouse_id,
                'campus_id' => $stockRequest->campus_id,
                'position_id' => $stockRequest->position_id,
                'type' => $stockRequest->type,
                'purpose' => $stockRequest->purpose,
                'approval_status' => $stockRequest->approval_status,
                'request_date' => $stockRequest->request_date,
                // Inline items with default department/campus fallback
                'items' => $stockRequest->stockRequestItems->map(function ($item) use ($defaultDepartment, $defaultCampus) {
                    return [
                        'id' => $item->id,
                        'product_id' => $item->product_id,
                        'department_id' => $item->department_id ?? $defaultDepartment?->id,
                        'campus_id' => $item->campus_id ?? $defaultCampus?->id,
                        'quantity' => $item->quantity,
                        'average_price' => $item->average_price,
                        'total_price' => $item->total_price,
                        'remarks' => $item->remarks,
                        'item_code' => $item->productVariant->item_code ?? null,
                        'product_name' => $item->productVariant->product->name ?? null,
                        'product_khmer_name' => $item->productVariant->product->khmer_name ?? null,
                        'unit_name' => $item->productVariant->product->unit->name ?? null,
                    ];
                })->toArray(),

                // Warehouse & campus for form selects
                'warehouse' => $stockRequest->warehouse ? ['id' => $stockRequest->warehouse->id, 'name' => $stockRequest->warehouse->name] : null,
                'campus' => $stockRequest->campus ? ['id' => $stockRequest->campus->id, 'short_name' => $stockRequest->campus->short_name] : null,

                // Approvals
                'approvals' => $stockRequest->approvals->map(function ($approval) {
                    return [
                        'id' => $approval->id,
                        'user_id' => $approval->responder_id,
                        'position_id' => $approval->position_id,
                        'request_type' => $approval->request_type,
                    ];
                })->toArray(),

                // Lists for inline selects
                'departments' => $departments->map(fn($d) => ['id' => $d->id, 'name' => $d->name])->toArray(),
                'campuses' => $campuses->map(fn($c) => ['id' => $c->id, 'name' => $c->name])->toArray(),
            ];

            return view('Inventory.stockRequest.form', compact(
                'stockRequest',
                'stockRequestData',
                'departments',
                'campuses',
                'defaultDepartment',
                'defaultCampus'
            ));

        } catch (\Exception $e) {
            Log::error('Error fetching stock request for editing', [
                'error_message' => $e->getMessage(),
                'stock_request_id' => $stockRequest->id,
            ]);

            return response()->view('errors.500', [
                'message' => 'Failed to fetch stock request',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Update an existing main stock request and its line items.
     *
     * @param Request $request
     * @param StockRequest $stockRequest
     * @return JsonResponse
     */
    public function update(Request $request, StockRequest $stockRequest): JsonResponse
    {
        $this->authorize('update', $stockRequest);

        $validated = Validator::make($request->all(), array_merge(
            $this->stockRequestValidationRules($stockRequest->id),
            $this->stockRequestItemValidationRules(),
            [
                'approvals' => 'required|array|min:1',
                'approvals.*.user_id' => 'required|exists:users,id',
                'approvals.*.request_type' => 'required|string|in:approve',
            ]
        ))->validate();

        // Validate that each approver has the appropriate permission
        foreach ($validated['approvals'] as $approval) {
            $user = User::findOrFail($approval['user_id']);
            $permission = "stockRequest.{$approval['request_type']}";
            if (!$user->hasPermissionTo($permission)) {
                return response()->json([
                    'message' => "User ID {$approval['user_id']} does not have permission for {$approval['request_type']}.",
                ], 403);
            }
        }

        try {
            return DB::transaction(function () use ($validated, $stockRequest) {
                // Update main stock request header
                $userCampus = auth()->user()->defaultCampus(); // returns model or null
                $userPosition = auth()->user()->defaultPosition(); // returns model or null

                if (!$userCampus) {
                    return response()->json([
                        'message' => 'No default campus assigned to this user.',
                    ], 404);
                }
                if (!$userPosition) {
                    return response()->json([
                        'message' => 'No default position assigned to this user.',
                    ], 404);
                }

                $stockRequest->update([
                    'warehouse_id' => $validated['warehouse_id'],
                    'campus_id' => $userCampus->id,
                    'position_id' => $userPosition?->id,
                    'type' => $validated['type'],
                    'purpose' => $validated['purpose'] ?? null,
                    'request_date' => $validated['request_date'],
                    'updated_by' => auth()->id() ?? 1,
                ]);

                // ---------------- Reset Returned status ----------------
                if ($stockRequest->approval_status === 'Returned') {
                    // Reset the stock request status
                    $stockRequest->update([
                        'approval_status' => 'Pending',
                    ]);

                    // Reset all approvals related to this stock request
                    Approval::where([
                        'approvable_type' => StockRequest::class,
                        'approvable_id'   => $stockRequest->id,
                    ])->update([
                        'approval_status' => 'Pending',
                        'responded_date'  => null,
                        'comment'         => null,
                    ]);
                }

                // ---------------- Approvals Handling ----------------
                // Build existing and new composite approval keys
                $existingApprovalKeys = $stockRequest->approvals->map(
                    fn($a) => "{$a->responder_id}|{$a->position_id}|{$a->request_type}"
                )->toArray();

                $newApprovalKeys = collect($validated['approvals'])->map(function ($a) {
                    $user = User::find($a['user_id']);
                    $positionId = $user?->defaultPosition()?->id;
                    return "{$a['user_id']}|{$positionId}|{$a['request_type']}";
                })->toArray();

                // Determine approvals to remove
                $approvalsToRemove = array_diff($existingApprovalKeys, $newApprovalKeys);
                foreach ($approvalsToRemove as $approvalKey) {
                    [$userId, $positionId, $requestType] = explode('|', $approvalKey);

                    Approval::where([
                        'approvable_type' => StockRequest::class,
                        'approvable_id'   => $stockRequest->id,
                        'responder_id'    => $userId,
                        'position_id'     => $positionId,
                        'request_type'    => $requestType,
                    ])->delete();
                }

                // Determine approvals to add
                $approvalsToAdd = array_diff($newApprovalKeys, $existingApprovalKeys);
                foreach ($approvalsToAdd as $approvalKey) {
                    [$userId, $positionId, $requestType] = explode('|', $approvalKey);

                    $approvalData = [
                        'approvable_type'    => StockRequest::class,
                        'approvable_id'      => $stockRequest->id,
                        'document_name'      => 'Stock Request',
                        'document_reference' => $stockRequest->request_number,
                        'request_type'       => $requestType,
                        'approval_status'    => 'Pending',
                        'ordinal'            => $this->getOrdinalForRequestType($requestType),
                        'requester_id'       => $stockRequest->created_by,
                        'responder_id'       => $userId,
                        'position_id'        => $positionId,
                    ];
                    $this->approvalService->storeApproval($approvalData);
                }

                // ---------------- Items Handling ----------------
                $existingItemIds = $stockRequest->stockRequestItems->pluck('id')->toArray();
                $submittedItemIds = array_filter(array_column($validated['items'], 'id'), fn($id) => !is_null($id));

                // Delete removed items
                StockRequestItem::where('stock_request_id', $stockRequest->id)
                    ->whereNotIn('id', $submittedItemIds)
                    ->each(function ($stockRequestItem) {
                        $stockRequestItem->deleted_by = auth()->id() ?? 1;
                        $stockRequestItem->save();
                        $stockRequestItem->delete();
                    });

                // Process items: update or insert
                $itemsToInsert = [];
                foreach ($validated['items'] as $item) {
                    if (!empty($item['id']) && in_array($item['id'], $existingItemIds)) {
                        // Update existing
                        $stockRequestItem = StockRequestItem::find($item['id']);
                        if ($stockRequestItem) {
                            $stockRequestItem->update([
                                'product_id' => $item['product_id'],
                                'department_id' => $item['department_id'],
                                'campus_id' => $item['campus_id'],
                                'quantity' => $item['quantity'],
                                'average_price' => $item['average_price'],
                                'total_price' => $item['quantity'] * $item['average_price'],
                                'remarks' => $item['remarks'] ?? null,
                                'updated_by' => auth()->id() ?? 1,
                            ]);
                        }
                    } else {
                        // Prepare for insert
                        $itemsToInsert[] = [
                            'stock_request_id' => $stockRequest->id,
                            'product_id' => $item['product_id'],
                            'department_id' => $item['department_id'],
                            'campus_id' => $item['campus_id'],
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

                return response()->json([
                    'message' => 'Stock request updated successfully.',
                    'data' => $stockRequest->load('stockRequestItems', 'approvals.responder'),
                ]);
            });
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to update stock request', ['error' => $e->getMessage()]);
            return response()->json([
                'message' => 'Failed to update stock request',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Retrieve users with specific approval permissions for a request type.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function getUsersForApproval(Request $request): JsonResponse
    {
        // Validate request type
        $validated = $request->validate([
            'request_type' => ['required', 'string', 'in:approve'],
        ]);

        $permission = "stockRequest.{$validated['request_type']}";
        $authUser = $request->user();

        try {
            // Get department IDs of the authenticated user
            $authDepartmentIds = $authUser->departments()->pluck('departments.id')->toArray();

            // Fetch users with direct or role-based permission
            $usersQuery = User::query()
                ->where(function ($query) use ($permission) {
                    $query->whereHas('permissions', fn ($q) => $q->where('name', $permission))
                        ->orWhereHas('roles.permissions', fn ($q) => $q->where('name', $permission));
                })
                ->whereHas('departments', fn ($q) => $q->whereIn('departments.id', $authDepartmentIds));

            $users = $usersQuery->select('id', 'name')->get();

            return response()->json([
                'message' => 'Users fetched successfully.',
                'data' => $users,
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to fetch users for approval', [
                'request_type' => $validated['request_type'],
                'auth_user_id' => $authUser->id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'message' => 'Failed to fetch users for approval.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
    /**
     * Retrieve paginated main stock requests with optional search and sort.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function getStockRequests(Request $request): JsonResponse
    {
        $this->authorize('viewAny', StockRequest::class);

        $user = auth()->user();
        $isAdmin = $user->hasRole('admin');
        $campusIds = $user->campus->pluck('id')->toArray();

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
            'stockRequestItems.productVariant.product.unit',
            'createdBy',
            'updatedBy',
        ])
        ->campusFilter($isAdmin, $campusIds)
        ->search($validated['search'] ?? null)
        ->orderBy($validated['sortColumn'] ?? self::DEFAULT_SORT_COLUMN, $validated['sortDirection'] ?? self::DEFAULT_SORT_DIRECTION);

        $stockRequests = $query->paginate($validated['limit'] ?? self::DEFAULT_LIMIT, ['*'], 'page', $validated['page'] ?? 1);

        return response()->json([
            'data' => (new StockRequestCollection($stockRequests))->toArray($request),
            'recordsTotal' => $stockRequests->total(),
            'recordsFiltered' => $stockRequests->total(),
            'draw' => (int) ($validated['draw'] ?? 1),
        ]);
    }

    /**
     * Export stock requests to an Excel file.
     *
     * @param Request $request
     * @return \Symfony\Component\HttpFoundation\BinaryFileResponse
     */
    public function export(Request $request)
    {
        $this->authorize('viewAny', StockRequest::class);

        $validated = $request->validate([
            'search' => 'nullable|string|max:255',
            'sortColumn' => 'nullable|string|in:' . implode(',', self::ALLOWED_SORT_COLUMNS),
            'sortDirection' => 'nullable|string|in:asc,desc',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date',
            'warehouse_id' => 'nullable|integer|exists:warehouses,id',
        ]);

        $query = StockRequest::with([
            'warehouse.building.campus',
            'createdBy',
            'stockRequestItems.productVariant.product.unit'
        ])
        ->search($validated['search'] ?? null)
        ->when($validated['start_date'] ?? null, fn($q, $start) => $q->whereDate('request_date', '>=', $start))
        ->when($validated['end_date'] ?? null, fn($q, $end) => $q->whereDate('request_date', '<=', $end))
        ->when($validated['warehouse_id'] ?? null, fn($q, $wid) => $q->where('warehouse_id', $wid))
        ->orderBy($validated['sortColumn'] ?? self::DEFAULT_SORT_COLUMN, $validated['sortDirection'] ?? self::DEFAULT_SORT_DIRECTION);

        return Excel::download(
            new StockRequestExport($query),
            'stock_requests_' . now()->format('Ymd_His') . '.xlsx'
        );
    }

    /**
     * Get validation rules for main stock request creation/update.
     *
     * @param int|null $stockRequestId
     * @return array
     */
    private function stockRequestValidationRules(?int $stockRequestId = null): array
    {
        return [
            'request_date' => ['required', 'date', 'date_format:' . self::DATE_FORMAT],
            'warehouse_id' => ['required', 'integer', 'exists:warehouses,id'],
            'type' => ['required', 'string'],
            'purpose' => ['required', 'string', 'max:1000'],
        ];
    }

    /**
     * Get validation rules for stock request line items.
     *
     * @return array
     */
    private function stockRequestItemValidationRules(): array
    {
        return [
            'items' => ['required', 'array', 'min:1'],
            'items.*.id' => ['nullable', 'integer', 'exists:stock_request_items,id'],
            'items.*.product_id' => ['required', 'integer', 'exists:product_variants,id'],
            'items.*.department_id' => ['required', 'integer', 'exists:departments,id'],
            'items.*.campus_id' => ['required', 'integer', 'exists:campus,id'],
            'items.*.quantity' => ['required', 'numeric', 'min:0.01'],
            'items.*.average_price' => ['required', 'numeric', 'min:0'],
            'items.*.remarks' => ['nullable', 'string', 'max:1000'],
        ];
    }

    /**
     * Generate a unique reference number in format STB-short_name-mmyyyy-sequence.
     *
     * @param int $warehouseId
     * @param string $requestDate
     * @return string
     * @throws \InvalidArgumentException If the date format is invalid or warehouse is not found.
     */
    private function generateReferenceNo(int $warehouseId, string $requestDate): string
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

        // Only call once
        $sequence = $this->getSequenceNumber($shortName, $monthYear);

        // Use the one you just got
        return "STR-{$shortName}-{$monthYear}-{$sequence}";
    }

    /**
     * Generate a sequence number for uniqueness, including soft-deleted records.
     *
     * @param string $shortName
     * @param string $monthYear
     * @return string
     */
    private function getSequenceNumber(string $shortName, string $monthYear): string
    {
        $prefix = "STR-{$shortName}-{$monthYear}-";

        $count = StockRequest::withTrashed()
            ->where('request_number', 'like', "{$prefix}%")
            ->count();

        return str_pad($count + 1, 2, '0', STR_PAD_LEFT);
    }


    /**
     * Delete a main stock request and its associated line items and approvals.
     *
     * @param StockRequest $stockRequest
     * @return JsonResponse
     */
    public function destroy(StockRequest $stockRequest): JsonResponse
    {
        $this->authorize('delete', $stockRequest);

        try {
            DB::transaction(function () use ($stockRequest) {
                $userId = auth()->id() ?? 1;

                // Hard delete related approvals
                Approval::where([
                    'approvable_type' => StockRequest::class,
                    'approvable_id' => $stockRequest->id,
                ])->delete();

                // Soft delete related stock requests
                foreach ($stockRequest->stockRequestItems as $stockRequestItem) {
                    $stockRequestItem->deleted_by = $userId;
                    $stockRequestItem->save();
                    $stockRequestItem->delete();
                }

                // Soft delete the main stock request
                $stockRequest->deleted_by = $userId;
                $stockRequest->save();
                $stockRequest->delete();
            });

            return response()->json([
                'message' => 'Stock request and related approvals deleted successfully.',
            ], 200);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to delete stock request', ['error' => $e->getMessage(), 'id' => $stockRequest->id]);
            return response()->json([
                'message' => 'Failed to delete stock request',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    //Approval Service
    public function submitApproval(Request $request, StockRequest $stockRequest, ApprovalService $approvalService): JsonResponse
    {
        // Validate request
        $validated = $request->validate([
            'request_type' => 'required|string|in:approve',
            'action'       => 'required|string|in:approve,reject,return',
            'comment'      => 'nullable|string|max:1000',
        ]);

        // Check user permission
        $permission = "stockRequest.{$validated['request_type']}";
        if (!auth()->user()->can($permission)) {
            return response()->json([
                'message' => "You do not have permission to {$validated['request_type']} this stock request.",
            ], 403);
        }

        // Process approval via ApprovalService
        $result = $approvalService->handleApprovalAction(
            $stockRequest,
            $validated['request_type'],
            $validated['action'],
            $validated['comment'] ?? null
        );

        // Ensure $result has 'success' key
        $success = $result['success'] ?? false;

        // Update StockRequest approval_status if successful
        if ($success) {
            $statusMap = [
                'approve' => 'Approved',
                'reject'  => 'Rejected',
                'return'  => 'Returned',
            ];
            $stockRequest->approval_status = $statusMap[$validated['action']] ?? 'Pending';
            $stockRequest->save();
        }

        return response()->json([
            'message'      => $result['message'] ?? 'Action failed',
            'redirect_url' => route('approvals-stock-requests.show', $stockRequest->id),
            'approval'     => $result['approval'] ?? null,
        ], $success ? 200 : 400);
    }

    /**
     * Initialize approvals for a stock request.
     *
     * @param StockRequest $stockRequest
     * @param array $approvals
     * @return void
     */
    protected function storeApprovals(StockRequest $stockRequest, array $approvals)
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
            $this->approvalService->storeApproval($approvalData);
        }
    }

        /**
     * Reassign a responder for a specific request type.
     *
     * @param Request $request
     * @param int $documentId
     * @return JsonResponse
     */
    public function reassignResponder(Request $request, StockRequest $stockRequest): JsonResponse
    {
        $this->authorize('reassign', $stockRequest);

        $validated = $request->validate([
            'request_type'   => 'required|string|in:approve',
            'new_user_id'    => 'required|exists:users,id',
            'new_position_id'=> 'nullable|exists:positions,id',
            'comment'        => 'nullable|string|max:1000',
        ]);

        $user = User::findOrFail($validated['new_user_id']);
        $positionId = $validated['new_position_id'] ?? $user->defaultPosition()?->id;

        if (!$positionId) {
            return response()->json([
                'success' => false,
                'message' => 'The new user does not have a default position assigned.',
            ], 422);
        }

        if (!$user->hasPermissionTo("stockRequest.{$validated['request_type']}")) {
            return response()->json([
                'success' => false,
                'message' => "User {$user->id} does not have permission for {$validated['request_type']}.",
            ], 403);
        }

        $approval = Approval::where([
            'approvable_type' => StockRequest::class,
            'approvable_id'   => $stockRequest->id,
            'request_type'    => $validated['request_type'],
            'approval_status' => 'Pending',
        ])->first();

        if (!$approval) {
            return response()->json([
                'success' => false,
                'message' => 'No pending approval found for the specified request type.',
            ], 404);
        }

        try {
            $approval->update([
                'responder_id' => $user->id,
                'position_id'  => $positionId,
                'comment'      => $validated['comment'] ?? $approval->comment,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Responder reassigned successfully.',
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to reassign responder', [
                'document_id'  => $stockRequest->id,
                'request_type' => $validated['request_type'],
                'error'        => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to reassign responder.',
                'error'   => $e->getMessage(),
            ], 500);
        }
    }


    /**
     * Determine if the authenticated user can see and interact with the approval button for a stock request.
     *
     * @param int $documentId
     * @return array
     */
    private function canShowApprovalButton(int $documentId): array
    {
        try {
            $userId = auth()->id();
            if (!$userId) {
                return $this->approvalButtonResponse('User not authenticated.');
            }

            if (!auth()->user()->hasAnyPermission(['stockRequest.approve'])) {
                return $this->approvalButtonResponse('User lacks approval permissions.');
            }

            $approvals = Approval::where([
                'approvable_type' => StockRequest::class,
                'approvable_id'   => $documentId,
            ])->orderBy('ordinal')->orderBy('id')->get();

            if ($approvals->isEmpty()) {
                return $this->approvalButtonResponse('No approvals configured.');
            }

            // Find the first pending approval for the current user
            $currentApproval = $approvals->firstWhere(function($a) use ($userId) {
                return $a->approval_status === 'Pending' && $a->responder_id === $userId;
            });

            if (!$currentApproval) {
                return $this->approvalButtonResponse('No pending approval assigned to current user.');
            }

            // Check all previous approvals (lower OR same ordinal but lower id)
            $previousApprovals = $approvals->filter(function($a) use ($currentApproval) {
                return ($a->ordinal < $currentApproval->ordinal) || 
                    ($a->ordinal === $currentApproval->ordinal && $a->id < $currentApproval->id);
            });

            // Block if any previous approval is Rejected
            if ($previousApprovals->contains(fn($a) => $a->approval_status === 'Rejected')) {
                return $this->approvalButtonResponse('A previous approval was rejected.');
            }

            // Block if any previous approval is Returned
            if ($previousApprovals->contains(fn($a) => $a->approval_status === 'Returned')) {
                return $this->approvalButtonResponse('A previous approval was returned.');
            }

            // Block if any previous approval is still Pending
            if ($previousApprovals->contains(fn($a) => $a->approval_status === 'Pending')) {
                return $this->approvalButtonResponse('Previous approval steps are not completed.');
            }

            return [
                'message' => 'Approval button available.',
                'showButton' => true,
                'requestType' => $currentApproval->request_type,
            ];

        } catch (\Exception $e) {
            Log::error('Failed to check approval button visibility', [
                'document_id' => $documentId,
                'user_id' => $userId,
                'error' => $e->getMessage(),
            ]);

            return $this->approvalButtonResponse('Failed to check approval button visibility');
        }
    }

    private function approvalButtonResponse(string $reason): array
    {
        return [
            'message' => "Approval button not available: {$reason}",
            'showButton' => false,
            'requestType' => null,
        ];
    }

    /**
     * Get ordinal for a request type.
     *
     * @param string $requestType
     * @return int
     */
    protected function getOrdinalForRequestType($requestType)
    {
        $ordinals = ['approve' => 3];
        return $ordinals[$requestType] ?? 1;
    }

    // Other Services
    public function fetchWarehousesForStockRequest(Request $request)
    {
        $this->authorize('viewAny', StockRequest::class);

        $user = $request->user();
        if ($user->hasRole('admin')) {
            $warehouses = $this->warehouseService->getWarehouses($request);
            return response()->json($warehouses);
        }
        if (!$user->warehouses()->exists()) {
            return response()->json([
                'message' => 'No warehouses assigned to this user.',
            ], 404);
        }
        $warehouses = $user->warehouses()->get();
        return response()->json($warehouses);
    }

    public function fetchCampusesForStockRequest(Request $request)
    {
        $this->authorize('viewAny', StockRequest::class);
        $user = $request->user();
        if($user->hasRole('admin')) {
            $campus = $this->campusService->getCampuses($request);
            return response()->json($campus);
        }
        if (!$user->campus()->exists()) {
            return response()->json([
                'message' => 'No campuses assigned to this user.',
            ], 404);
        }
        $campus = $user->campus()->get();
        return response()->json($campus);
    }

    public function fetchProductsForStockRequest(Request $request)
    {
        $this->authorize('viewAny', StockRequest::class);
        $response = $this->productService->getStockManagedVariants($request);
        
        // Filter response to include only items where is_active = 1
        $filteredResponse = [
            'data' => collect($response['data'])->filter(function ($item) {
                return $item['is_active'] == 1;
            })->values()->all(),
            'recordsTotal' => $response['recordsTotal'],
            'recordsFiltered' => count($response['data']), // Update recordsFiltered to reflect filtered count
            'draw' => $response['draw'],
        ];
        
        return response()->json($filteredResponse);
    }
}