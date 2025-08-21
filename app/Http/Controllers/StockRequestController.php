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
use App\Services\DepartmentService;

class StockRequestController extends Controller
{
    // Constants for sort columns and default values
    private const ALLOWED_SORT_COLUMNS = ['request_date', 'request_number', 'created_at', 'updated_at', 'created_by', 'updated_by'];
    private const DEFAULT_SORT_COLUMN = 'created_at';
    private const DEFAULT_SORT_DIRECTION = 'desc';
    private const DEFAULT_LIMIT = 10;
    private const MAX_LIMIT = 1000;
    private const DATE_FORMAT = 'Y-m-d';

    protected $approvalController;
    protected $warehouseService;
    protected $productService;
    protected $campusService;
    protected $departmentService;

    public function __construct(
        ApprovalController $approvalController,
        WarehouseService $warehouseService,
        ProductService $productService,
        CampusService $campusService,
        DepartmentService $departmentService
    ) {
        $this->approvalController = $approvalController;
        $this->warehouseService = $warehouseService;
        $this->productService = $productService;
        $this->campusService = $campusService;
        $this->departmentService = $departmentService;
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
                'warehouse.building.campus',
                'createdBy',
                'updatedBy',
                'approvals.responder',
            ]);

            // Check if the approval button should be shown
            $approvalButtonData = $this->canShowApprovalButton($stockRequest->id);

            // Derive responders from approvals
            $responders = $stockRequest->approvals->map(function ($approval) {
                return [
                    'id' => $approval->id,
                    'user_id' => $approval->responder_id,
                    'request_type' => $approval->request_type,
                    'name' => $approval->responder->name ?? 'N/A',
                ];
            })->toArray();

            return view('Inventory.stockRequestItem.show', [
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
                'approvals.*.request_type' => 'required|string|in:review,check,approve',
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

                if (!$userCampus) {
                    return response()->json([
                        'message' => 'No default campus assigned to this user.',
                    ], 404);
                }
                $stockRequest = StockRequest::create([
                    'request_number' => $referenceNo,
                    'warehouse_id' => $validated['warehouse_id'],
                    'campus_id' => $userCampus->id,
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

                $this->initializeApprovals($stockRequest, $validated['approvals']);

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
            $stockRequestItemData = [
                'id' => $stockRequest->id,
                'request_number' => $stockRequest->request_number,
                'warehouse_id' => $stockRequest->warehouse_id,
                'campus_id' => $stockRequest->campus_id,
                'type' => $stockRequest->type,
                'purpose' => $stockRequest->purpose,
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
                        'request_type' => $approval->request_type,
                    ];
                })->toArray(),

                // Lists for inline selects
                'departments' => $departments->map(fn($d) => ['id' => $d->id, 'name' => $d->name])->toArray(),
                'campuses' => $campuses->map(fn($c) => ['id' => $c->id, 'name' => $c->name])->toArray(),
            ];

            return view('Inventory.stockRequest.form', compact(
                'stockRequest',
                'stockRequestItemData',
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
                'approvals.*.request_type' => 'required|string|in:review,check,approve',
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
                if (!$userCampus) {
                    return response()->json([
                        'message' => 'No default campus assigned to this user.',
                    ], 404);
                }
                $stockRequest->update([
                    'warehouse_id' => $validated['warehouse_id'],
                    'campus_id' => $userCampus->id,
                    'type' => $validated['type'],
                    'purpose' => $validated['purpose'] ?? null,
                    'request_date' => $validated['request_date'],
                    'updated_by' => auth()->id() ?? 1,
                ]);

                // Build existing and new composite approval keys
                $existingApprovalKeys = $stockRequest->approvals->map(fn($a) => "{$a->responder_id}|{$a->request_type}")->toArray();
                $newApprovalKeys = collect($validated['approvals'])->map(fn($a) => "{$a['user_id']}|{$a['request_type']}")->toArray();

                // Determine approvals to remove
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

                // Determine approvals to add
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

                // Handle stock request line items
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
            'request_type' => ['required', 'string', 'in:review,check,approve'],
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
        $campusIds = $isAdmin ? [] : $user->campus->pluck('id')->toArray();

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
                // 'createdBy.defaultCampus',
                'stockRequestItems.productVariant.product.unit',
                'createdBy',
                'updatedBy',
            ])
            // Apply warehouse filter only if NOT admin
            ->when(!$isAdmin, fn($q) => $q->whereIn('campus_id', $campusIds))
            ->when($validated['search'] ?? null, function ($query, $search) {
                $query->where(function ($q) use ($search) {
                    $q->where('request_number', 'like', "%{$search}%")
                        ->orWhereHas('warehouse', fn($q) => $q->where('name', 'like', "%{$search}%"))
                        ->orWhereHas('stockRequestItems.productVariant.product', function ($q) use ($search) {
                            $q->where(function ($q2) use ($search) {
                                $q2->where('name', 'like', "%{$search}%")
                                    ->orWhere('khmer_name', 'like', "%{$search}%")
                                    ->orWhere('description', 'like', "%{$search}%")
                                    ->orWhere('item_code', 'like', "%{$search}%")
                                    ->orWhereHas('unit', fn($q3) => $q3->where('name', 'like', "%{$search}%"));
                            });
                        });
                });
            });

        $recordsTotal = $isAdmin
            ? StockRequest::count()
            : StockRequest::whereIn('campus_id', $campusIds)->count();

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
                'warehouse_name' => $item->warehouse->name ?? null,
                'warehouse_campus_name' => $item->warehouse->building->campus->short_name ?? null,
                'user_campus_name' => $item->campus->short_name ?? null,
                'building_name' => $item->warehouse->building->short_name ?? null,
                'quantity' => round($item->stockRequestItems->sum('quantity'), 4),
                'total_price' => round($item->stockRequestItems->sum('total_price'), 4),
                'created_at' => optional($item->created_at)->toDateTimeString(),
                'updated_at' => optional($item->updated_at)->toDateTimeString(),
                'created_by' => $item->createdBy->name ?? 'System',
                'updated_by' => $item->updatedBy->name ?? 'System',
                'approval_status' => $item->approval_status,
                'items' => $item->stockRequestItems->map(function ($sb) {
                    return [
                        'id' => $sb->id,
                        'product_id' => $sb->product_id,
                        'department_id' => $sb->department_id,
                        'campus_id' => $sb->campus_id,
                        'item_code' => $sb->productVariant->item_code ?? null,
                        'quantity' => $sb->quantity,
                        'average_price' => $sb->average_price,
                        'total_price' => $sb->total_price,
                        'remarks' => $sb->remarks,
                        'product_name' => $sb->productVariant->product->name ?? null,
                        'product_khmer_name' => $sb->productVariant->product->khmer_name ?? null,
                        'unit_name' => $sb->productVariant->product->unit->name ?? null,
                    ];
                })->toArray(),
            ];
        });

        return response()->json([
            'data' => $data->all(),
            'recordsTotal' => $recordsTotal,
            'recordsFiltered' => $recordsFiltered,
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
            ->when($validated['search'] ?? null, function ($q, $search) {
                $q->where('request_number', 'like', "%{$search}%")
                ->orWhereHas('warehouse', fn($q2) => $q2->where('name', 'like', "%{$search}%"))
                ->orWhereHas('stockRequestItems.productVariant.product', fn($q3) => $q3->where(function ($q4) use ($search) {
                    $q4->where('name', 'like', "%{$search}%")
                        ->orWhere('khmer_name', 'like', "%{$search}%")
                        ->orWhere('description', 'like', "%{$search}%")
                        ->orWhere('item_code', 'like', "%{$search}%")
                        ->orWhereHas('unit', fn($q5) => $q5->where('name', 'like', "%{$search}%"));
                }));
            })
            ->when($validated['start_date'] ?? null, fn($q, $start) => $q->whereDate('request_date', '>=', $start))
            ->when($validated['end_date'] ?? null, fn($q, $end) => $q->whereDate('request_date', '<=', $end))
            ->when($validated['warehouse_id'] ?? null, fn($q, $wid) => $q->where('warehouse_id', $wid));

        $sortColumn = in_array($validated['sortColumn'] ?? self::DEFAULT_SORT_COLUMN, self::ALLOWED_SORT_COLUMNS)
            ? $validated['sortColumn']
            : self::DEFAULT_SORT_COLUMN;

        $sortDirection = in_array(strtolower($validated['sortDirection'] ?? self::DEFAULT_SORT_DIRECTION), ['asc', 'desc'])
            ? $validated['sortDirection']
            : self::DEFAULT_SORT_DIRECTION;

        $query->orderBy($sortColumn, $sortDirection);

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
            'purpose' => ['nullable', 'string', 'max:1000'],
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

    /**
     * Submit an approval action (approve or reject) for a stock request.
     *
     * @param Request $request
     * @param int $documentId
     * @return JsonResponse
     */
    public function submitApproval(Request $request, StockRequest $stockRequest): JsonResponse
    {
        $validated = $request->validate([
            'request_type' => 'required|string|in:review,check,approve',
            'action' => 'required|string|in:approve,reject',
            'comment' => 'nullable|string|max:1000',
        ]);

        // Build permission string
        $permission = "stockRequest.{$validated['request_type']}";

        // Check permission using Spatie's `can`
        if (!auth()->user()->can($permission)) {
            return response()->json([
                'message' => "You do not have permission to {$validated['request_type']} this stock request.",
            ], 403);
        }

        $method = $validated['action'] === 'approve' ? 'confirmApproval' : 'rejectApproval';

        $result = $this->approvalController->$method(
            $request,
            StockRequest::class,
            $stockRequest->id,
            $validated['request_type']
        );

        if ($result['success']) {
            // Update StockRequest approval_status based on request_type and action
            if ($validated['action'] === 'approve') {
                $statusMap = [
                    'review' => 'Reviewed',
                    'check' => 'Checked',
                    'approve' => 'Approved',
                ];

                $stockRequest->approval_status = $statusMap[$validated['request_type']] ?? null;
            } else {
                // Reject action sets status to 'Rejected'
                $stockRequest->approval_status = 'Rejected';
            }

            // Save the updated status
            $stockRequest->save();
        }

        return response()->json([
            'message' => $result['message'],
            'redirect_url' => route('approvals-stock-requests.show', $stockRequest->id),
            'approval' => $result['approval'] ?? null,
        ], $result['success'] ? 200 : 400);
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
            'request_type' => 'required|string|in:review,check,approve',
            'new_user_id' => 'required|exists:users,id',
            'comment' => 'nullable|string|max:1000',
        ]);

        $user = User::findOrFail($validated['new_user_id']);
        $permission = "stockRequest.{$validated['request_type']}";
        if (!$user->hasPermissionTo($permission)) {
            return response()->json([
                'message' => "User ID {$validated['new_user_id']} does not have permission for {$validated['request_type']}.",
            ], 403);
        }

        try {
            $approval = Approval::where([
                'approvable_type' => StockRequest::class,
                'approvable_id' => $stockRequest->id,
                'request_type' => $validated['request_type'],
                'approval_status' => 'Pending',
            ])->first();

            if (!$approval) {
                return response()->json([
                    'message' => 'No pending approval found for the specified request type.',
                    'success' => false,
                ], 404);
            }

            $approval->update([
                'responder_id' => $validated['new_user_id'],
                'comment' => $validated['comment'],
                'updated_at' => now(),
            ]);

            return response()->json([
                'message' => 'Responder reassigned successfully.',
                'success' => true,
            ], 200);
        } catch (\Exception $e) {
            Log::error('Failed to reassign responder', [
                'document_id' => $stockRequest->id,
                'request_type' => $validated['request_type'],
                'error' => $e->getMessage(),
            ]);
            return response()->json([
                'message' => 'Failed to reassign responder.',
                'success' => false,
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * List approvals for a specific stock request.
     *
     * @param Request $request
     * @param int $documentId
     * @return JsonResponse
     */
    public function listApprovals(Request $request, $documentId): JsonResponse
    {
        $stockRequest = StockRequest::findOrFail($documentId);
        $this->authorize('view', $stockRequest);

        $result = $this->approvalController->listApprovals($request, StockRequest::class, $documentId);

        return response()->json([
            'message' => $result['message'],
            'approvals' => $result['approvals'] ?? null,
        ], $result['success'] ? 200 : 403);
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
            $stockRequest = StockRequest::findOrFail($documentId);
            $userId = auth()->id();
            if (!$userId) {
                return [
                    'message' => 'Approval button not available: User not authenticated.',
                    'showButton' => false,
                    'requestType' => null,
                ];
            }

            // Check user permissions for any approval-related actions
            $hasPermission = auth()->user()->hasAnyPermission([
                'stockRequest.review',
                'stockRequest.check',
                'stockRequest.approve'
            ]);
            if (!$hasPermission) {
                return [
                    'message' => 'Approval button not available: User lacks approval permissions.',
                    'showButton' => false,
                    'requestType' => null,
                ];
            }

            // Get all approvals for this stock request, ordered by ordinal and id
            $approvals = Approval::where([
                'approvable_type' => StockRequest::class,
                'approvable_id' => $documentId,
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

            // Find the first pending approval
            $currentApproval = $approvals->firstWhere('approval_status', 'Pending');
            if (!$currentApproval) {
                return [
                    'message' => 'Approval button not available: All approvals completed or none pending.',
                    'showButton' => false,
                    'requestType' => null,
                ];
            }

            // Check if the current user is the responder for the pending approval
            if ($currentApproval->responder_id !== $userId) {
                return [
                    'message' => 'Approval button not available: User is not the assigned responder.',
                    'showButton' => false,
                    'requestType' => null,
                ];
            }

            // Filter previous approvals (with lower ordinal)
            $previousApprovals = $approvals->filter(function ($approval) use ($currentApproval) {
                return ($approval->ordinal < $currentApproval->ordinal) ||
                    ($approval->ordinal === $currentApproval->ordinal && $approval->id < $currentApproval->id);
            });

            // Check if any previous approval is rejected
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

            // Check if all previous approvals are approved
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
                'document_id' => $documentId,
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
    protected function initializeApprovals(StockRequest $stockRequest, array $approvals)
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
    protected function getOrdinalForRequestType($requestType)
    {
        $ordinals = ['review' => 1, 'check' => 2, 'approve' => 3];
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
        return response()->json($response);
    }
}