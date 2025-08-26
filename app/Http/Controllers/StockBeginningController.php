<?php

namespace App\Http\Controllers;

use App\Models\MainStockBeginning;
use App\Models\StockBeginning;
use App\Models\Warehouse;
use App\Models\User;
use App\Models\Approval;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Http\JsonResponse;
use Maatwebsite\Excel\Facades\Excel;
use App\Exports\StockBeginningsExport;
use App\Imports\StockBeginningsImport;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use App\Services\WarehouseService;
use App\Services\ProductService;

class StockBeginningController extends Controller
{
    // Constants for sort columns and default values
    private const ALLOWED_SORT_COLUMNS = ['reference_no', 'beginning_date', 'created_at', 'updated_at', 'created_by', 'updated_by'];
    private const DEFAULT_SORT_COLUMN = 'created_at';
    private const DEFAULT_SORT_DIRECTION = 'desc';
    private const DEFAULT_LIMIT = 10;
    private const MAX_LIMIT = 1000;
    private const DATE_FORMAT = 'Y-m-d';

    protected $approvalController;
    protected $warehouseService;
    protected $productService;

    public function __construct(
        ApprovalController $approvalController,
        WarehouseService $warehouseService,
        ProductService $productService
    ) {
        $this->approvalController = $approvalController;
        $this->warehouseService = $warehouseService;
        $this->productService = $productService;
    }

    /**
     * Display the stock beginnings index view.
     *
     * @return \Illuminate\View\View
     */
    public function index()
    {
        $this->authorize('viewAny', MainStockBeginning::class);
        return view('Inventory.stockBeginning.index');
    }

    /**
     * Show the form for creating a new main stock beginning.
     *
     * @return \Illuminate\View\View
     */
    public function create()
    {
        $this->authorize('create', MainStockBeginning::class);
        return view('Inventory.stockBeginning.form');
    }

    /**
     * Display a single main stock beginning with its line items and approvals for printing.
     *
     * @param MainStockBeginning $mainStockBeginning
     * @return \Illuminate\View\View
     */
    public function show(MainStockBeginning $mainStockBeginning)
    {
        $this->authorize('view', $mainStockBeginning);

        try {
            // Load related data including approvals
            $mainStockBeginning->load([
                'stockBeginnings.productVariant.product.unit',
                'warehouse.building.campus',
                'createdBy',
                'updatedBy',
                'creatorPosition',
                'approvals.responder',
                'approvals.responderPosition',
            ]);

            // Check if the approval button should be shown
            $approvalButtonData = $this->canShowApprovalButton($mainStockBeginning->id);

            // Derive responders from approvals
            $responders = $mainStockBeginning->approvals->map(function ($approval) {
                return [
                    'id' => $approval->id,
                    'user_id' => $approval->responder_id,
                    'position_id' => $approval->position_id,
                    'request_type' => $approval->request_type,
                    'name' => $approval->responder->name ?? 'N/A',
                ];
            })->toArray();

            return view('Inventory.stockBeginning.show', [
                'mainStockBeginning' => $mainStockBeginning,
                'totalQuantity' => round($mainStockBeginning->stockBeginnings->sum('quantity'), 4),
                'totalValue' => round($mainStockBeginning->stockBeginnings->sum('total_value'), 4),
                'approvals' => $mainStockBeginning->approvals
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
            Log::error('Error fetching stock beginning for display', [
                'error_message' => $e->getMessage(),
                'stock_beginning_id' => $mainStockBeginning->id,
            ]);
            return response()->view('errors.500', [
                'message' => 'Failed to fetch stock beginning',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Store a new main stock beginning with its line items and approvals.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function store(Request $request): JsonResponse
    {
        $this->authorize('create', MainStockBeginning::class);

        $validated = Validator::make($request->all(), array_merge(
            $this->mainStockBeginningValidationRules(),
            $this->stockBeginningValidationRules(),
            [
                'approvals' => 'required|array|min:1',
                'approvals.*.user_id' => 'required|exists:users,id',
                'approvals.*.request_type' => 'required|string|in:review,check,approve',
            ]
        ))->validate();

        // Validate that each approver has the appropriate permission
        foreach ($validated['approvals'] as $approval) {
            $user = User::findOrFail($approval['user_id']);
            $permission = "mainStockBeginning.{$approval['request_type']}";
            if (!$user->hasPermissionTo($permission)) {
                return response()->json([
                    'message' => "User ID {$approval['user_id']} does not have permission for {$approval['request_type']}.",
                ], 403);
            }
        }

        try {
            return DB::transaction(function () use ($validated) {
                $referenceNo = $this->generateReferenceNo($validated['warehouse_id'], $validated['beginning_date']);
                $userPosition = auth()->user()->defaultPosition(); // returns model or null

                if (!$userPosition) {
                    return response()->json([
                        'message' => 'No default position assigned to this user.',
                    ], 404);
                }

                $mainStockBeginning = MainStockBeginning::create([
                    'reference_no' => $referenceNo,
                    'warehouse_id' => $validated['warehouse_id'],
                    'position_id' => $userPosition->id,
                    'beginning_date' => $validated['beginning_date'],
                    'created_by' => auth()->id() ?? 1,
                    'approval_status' => 'Pending',
                ]);

                $items = array_map(function ($item) use ($mainStockBeginning) {
                    return [
                        'main_form_id' => $mainStockBeginning->id,
                        'product_id' => $item['product_id'],
                        'quantity' => $item['quantity'],
                        'unit_price' => $item['unit_price'],
                        'total_value' => $item['quantity'] * $item['unit_price'],
                        'remarks' => $item['remarks'] ?? null,
                        'created_by' => auth()->id() ?? 1,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ];
                }, $validated['items']);

                StockBeginning::insert($items);

                $this->storeApprovals($mainStockBeginning, $validated['approvals']);

                return response()->json([
                    'message' => 'Stock beginning created successfully.',
                    'data' => $mainStockBeginning->load('stockBeginnings', 'approvals.responder'),
                ], 201);
            });
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to create stock beginning', ['error' => $e->getMessage()]);
            return response()->json([
                'message' => 'Failed to create stock beginning',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Show the form for editing an existing main stock beginning.
     *
     * @param MainStockBeginning $mainStockBeginning
     * @return \Illuminate\View\View
     */
    public function edit(MainStockBeginning $mainStockBeginning)
    {
        $this->authorize('update', $mainStockBeginning);

        try {
            // Load related data including approvals
            $mainStockBeginning->load([
                'stockBeginnings.productVariant.product.unit',
                'warehouse.building.campus',
                'approvals.responder',
            ]);

            // Prepare data for the Vue form
            $stockBeginningData = [
                'id' => $mainStockBeginning->id,
                'reference_no' => $mainStockBeginning->reference_no,
                'warehouse_id' => $mainStockBeginning->warehouse_id,
                'beginning_date' => $mainStockBeginning->beginning_date,
                'items' => $mainStockBeginning->stockBeginnings->map(function ($item) {
                    return [
                        'id' => $item->id,
                        'product_id' => $item->product_id,
                        'quantity' => $item->quantity,
                        'unit_price' => $item->unit_price,
                        'total_value' => $item->total_value,
                        'remarks' => $item->remarks,
                        'item_code' => $item->productVariant->item_code ?? null,
                        'product_name' => $item->productVariant->product->name ?? null,
                        'product_khmer_name' => $item->productVariant->product->khmer_name ?? null,
                        'unit_name' => $item->productVariant->product->unit->name ?? null,
                    ];
                })->toArray(),
                'warehouse' => $mainStockBeginning->warehouse ? [
                    'id' => $mainStockBeginning->warehouse->id,
                    'name' => $mainStockBeginning->warehouse->name,
                    'building' => $mainStockBeginning->warehouse->building ? [
                        'id' => $mainStockBeginning->warehouse->building->id,
                        'short_name' => $mainStockBeginning->warehouse->building->short_name,
                        'campus' => $mainStockBeginning->warehouse->building->campus ? [
                            'id' => $mainStockBeginning->warehouse->building->campus->id,
                            'short_name' => $mainStockBeginning->warehouse->building->campus->short_name,
                        ] : null,
                    ] : null,
                ] : null,
                'approvals' => $mainStockBeginning->approvals->map(function ($approval) {
                    return [
                        'id' => $approval->id,
                        'user_id' => $approval->responder_id,
                        'position_id' => $approval->position_id,
                        'request_type' => $approval->request_type,
                    ];
                })->toArray(),
            ];

            return view('Inventory.stockBeginning.form', [
                'mainStockBeginning' => $mainStockBeginning,
                'stockBeginningData' => $stockBeginningData,
            ]);
        } catch (\Exception $e) {
            Log::error('Error fetching stock beginning for editing', [
                'error_message' => $e->getMessage(),
                'stock_beginning_id' => $mainStockBeginning->id,
            ]);
            return response()->view('errors.500', [
                'message' => 'Failed to fetch stock beginning',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Update an existing main stock beginning and its line items.
     *
     * @param Request $request
     * @param MainStockBeginning $mainStockBeginning
     * @return JsonResponse
     */
    public function update(Request $request, MainStockBeginning $mainStockBeginning): JsonResponse
    {
        $this->authorize('update', $mainStockBeginning);

        $validated = Validator::make($request->all(), array_merge(
            $this->mainStockBeginningValidationRules($mainStockBeginning->id),
            $this->stockBeginningValidationRules(),
            [
                'approvals' => 'required|array|min:1',
                'approvals.*.user_id' => 'required|exists:users,id',
                'approvals.*.request_type' => 'required|string|in:review,check,approve',
            ]
        ))->validate();

        // Validate that each approver has the appropriate permission
        foreach ($validated['approvals'] as $approval) {
            $user = User::findOrFail($approval['user_id']);
            $permission = "mainStockBeginning.{$approval['request_type']}";
            if (!$user->hasPermissionTo($permission)) {
                return response()->json([
                    'message' => "User ID {$approval['user_id']} does not have permission for {$approval['request_type']}.",
                ], 403);
            }
        }

        try {
            return DB::transaction(function () use ($validated, $mainStockBeginning) {
                // Update main stock beginning header
                $mainStockBeginning->update([
                    'warehouse_id' => $validated['warehouse_id'],
                    'beginning_date' => $validated['beginning_date'],
                    'updated_by' => auth()->id() ?? 1,
                ]);

                // ---------------- Reset Returned status ----------------
                if ($mainStockBeginning->approval_status === 'Returned') {
                    // Reset the stock request status
                    $mainStockBeginning->update([
                        'approval_status' => 'Pending',
                    ]);

                    // Reset all approvals related to this stock request
                    Approval::where([
                        'approvable_type' => MainStockBeginning::class,
                        'approvable_id'   => $mainStockBeginning->id,
                    ])->update([
                        'approval_status' => 'Pending',
                        'responded_date'  => null,
                        'comment'         => null,
                    ]);
                }

                // Build existing and new composite approval keys
                $existingApprovalKeys = $mainStockBeginning->approvals->map(fn($a) => "{$a->responder_id}|{$a->request_type}")->toArray();
                $newApprovalKeys = collect($validated['approvals'])->map(fn($a) => "{$a['user_id']}|{$a['request_type']}")->toArray();

                // Determine approvals to remove
                $approvalsToRemove = array_diff($existingApprovalKeys, $newApprovalKeys);
                foreach ($approvalsToRemove as $approvalKey) {
                    [$userId, $requestType] = explode('|', $approvalKey);
                    Approval::where([
                        'approvable_type' => MainStockBeginning::class,
                        'approvable_id' => $mainStockBeginning->id,
                        'responder_id' => $userId,
                        'request_type' => $requestType,
                    ])->delete();
                }

                // Determine approvals to add
                $approvalsToAdd = array_diff($newApprovalKeys, $existingApprovalKeys);
                foreach ($approvalsToAdd as $approvalKey) {
                    [$userId, $requestType] = explode('|', $approvalKey);
                    $approvalData = [
                        'approvable_type' => MainStockBeginning::class,
                        'approvable_id' => $mainStockBeginning->id,
                        'document_name' => 'Stock Beginning',
                        'document_reference' => $mainStockBeginning->reference_no,
                        'request_type' => $requestType,
                        'approval_status' => 'Pending',
                        'ordinal' => $this->getOrdinalForRequestType($requestType),
                        'requester_id' => $mainStockBeginning->created_by,
                        'responder_id' => $userId,
                    ];
                    $this->approvalController->storeApproval($approvalData);
                }

                // Handle stock beginning line items
                $existingItemIds = $mainStockBeginning->stockBeginnings->pluck('id')->toArray();
                $submittedItemIds = array_filter(array_column($validated['items'], 'id'), fn($id) => !is_null($id));

                // Delete removed items
                StockBeginning::where('main_form_id', $mainStockBeginning->id)
                    ->whereNotIn('id', $submittedItemIds)
                    ->each(function ($stockBeginning) {
                        $stockBeginning->deleted_by = auth()->id() ?? 1;
                        $stockBeginning->save();
                        $stockBeginning->delete();
                    });

                // Process items: update or insert
                $itemsToInsert = [];
                foreach ($validated['items'] as $item) {
                    if (!empty($item['id']) && in_array($item['id'], $existingItemIds)) {
                        // Update existing
                        $stockBeginning = StockBeginning::find($item['id']);
                        if ($stockBeginning) {
                            $stockBeginning->update([
                                'product_id' => $item['product_id'],
                                'quantity' => $item['quantity'],
                                'unit_price' => $item['unit_price'],
                                'total_value' => $item['quantity'] * $item['unit_price'],
                                'remarks' => $item['remarks'] ?? null,
                                'updated_by' => auth()->id() ?? 1,
                            ]);
                        }
                    } else {
                        // Prepare for insert
                        $itemsToInsert[] = [
                            'main_form_id' => $mainStockBeginning->id,
                            'product_id' => $item['product_id'],
                            'quantity' => $item['quantity'],
                            'unit_price' => $item['unit_price'],
                            'total_value' => $item['quantity'] * $item['unit_price'],
                            'remarks' => $item['remarks'] ?? null,
                            'created_by' => auth()->id() ?? 1,
                            'updated_by' => auth()->id() ?? 1,
                            'created_at' => now(),
                            'updated_at' => now(),
                        ];
                    }
                }

                if (!empty($itemsToInsert)) {
                    StockBeginning::insert($itemsToInsert);
                }

                return response()->json([
                    'message' => 'Stock beginning updated successfully.',
                    'data' => $mainStockBeginning->load('stockBeginnings', 'approvals.responder'),
                ]);
            });
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to update stock beginning', ['error' => $e->getMessage()]);
            return response()->json([
                'message' => 'Failed to update stock beginning',
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

        $permission = "mainStockBeginning.{$validated['request_type']}";

        try {
            // Fetch users with direct or role-based permission
            $users = User::query()
                ->where(function ($query) use ($permission) {
                    $query->whereHas('permissions', fn ($q) => $q->where('name', $permission))
                        ->orWhereHas('roles.permissions', fn ($q) => $q->where('name', $permission));
                })
                ->select('id', 'name')
                ->get();

            return response()->json([
                'message' => 'Users fetched successfully.',
                'data' => $users,
            ]);
        } catch (\Exception $e) {
            // Log error
            Log::error('Failed to fetch users for approval', [
                'request_type' => $validated['request_type'],
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'message' => 'Failed to fetch users for approval.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Retrieve paginated main stock beginnings with optional search and sort.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function getStockBeginnings(Request $request): JsonResponse
    {
        $this->authorize('viewAny', MainStockBeginning::class);
        $user = auth()->user();
        $isAdmin = $user->hasRole('admin');

        // Get warehouse IDs only if NOT admin
        $warehouseIds = $isAdmin ? [] : $user->warehouses->pluck('id')->toArray();

        $validated = $request->validate([
            'search' => 'nullable|string|max:255',
            'sortColumn' => 'nullable|string|in:' . implode(',', self::ALLOWED_SORT_COLUMNS),
            'sortDirection' => 'nullable|string|in:asc,desc',
            'limit' => 'nullable|integer|min:1|max:' . self::MAX_LIMIT,
            'page' => 'nullable|integer|min:1',
            'draw' => 'nullable|integer',
        ]);

        $query = MainStockBeginning::with([
                'warehouse.building.campus',
                'stockBeginnings.productVariant.product.unit',
                'createdBy',
                'updatedBy',
            ])
            // Apply warehouse filter only if NOT admin
            ->when(!$isAdmin, fn($q) => $q->whereIn('warehouse_id', $warehouseIds))
            ->when($validated['search'] ?? null, function ($query, $search) {
                $query->where(function ($q) use ($search) {
                    $q->where('reference_no', 'like', "%{$search}%")
                        ->orWhereHas('warehouse', fn($q) => $q->where('name', 'like', "%{$search}%"))
                        ->orWhereHas('stockBeginnings.productVariant.product', function ($q) use ($search) {
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
            ? MainStockBeginning::count()
            : MainStockBeginning::whereIn('warehouse_id', $warehouseIds)->count();

        $recordsFiltered = $query->count();

        $sortColumn = $validated['sortColumn'] ?? self::DEFAULT_SORT_COLUMN;
        $sortDirection = $validated['sortDirection'] ?? self::DEFAULT_SORT_DIRECTION;

        $query->orderBy($sortColumn, $sortDirection);

        $limit = $validated['limit'] ?? self::DEFAULT_LIMIT;
        $page = $validated['page'] ?? 1;

        $mainStockBeginnings = $query->paginate($limit, ['*'], 'page', $page);

        $data = $mainStockBeginnings->getCollection()->map(function ($item) {
            return [
                'id' => $item->id,
                'reference_no' => $item->reference_no,
                'beginning_date' => $item->beginning_date,
                'warehouse_name' => $item->warehouse->name ?? null,
                'campus_name' => $item->warehouse->building->campus->short_name ?? null,
                'building_name' => $item->warehouse->building->short_name ?? null,
                'quantity' => round($item->stockBeginnings->sum('quantity'), 4),
                'total_value' => round($item->stockBeginnings->sum('total_value'), 4),
                'created_at' => optional($item->created_at)->toDateTimeString(),
                'updated_at' => optional($item->updated_at)->toDateTimeString(),
                'created_by' => $item->createdBy->name ?? 'System',
                'updated_by' => $item->updatedBy->name ?? 'System',
                'approval_status' => $item->approval_status,
                'items' => $item->stockBeginnings->map(function ($sb) {
                    return [
                        'id' => $sb->id,
                        'product_id' => $sb->product_id,
                        'item_code' => $sb->productVariant->item_code ?? null,
                        'quantity' => $sb->quantity,
                        'unit_price' => $sb->unit_price,
                        'total_value' => $sb->total_value,
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
     * Import stock beginnings from an Excel file and return data for form population.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function import(Request $request): JsonResponse
    {
        $this->authorize('create', MainStockBeginning::class);

        $validated = $request->validate([
            'file' => 'required|file|mimes:xlsx,xls,csv|max:2048',
        ]);

        try {
            $import = new StockBeginningsImport();
            Excel::import($import, $request->file('file'));

            $data = $import->getData();

            if (!empty($data['errors'])) {
                return response()->json([
                    'message' => 'Errors found in Excel file.',
                    'errors' => $data['errors'],
                ], 422);
            }

            if (empty($data['items'])) {
                return response()->json([
                    'message' => 'No valid data found in the Excel file.',
                    'errors' => ['No valid rows processed.'],
                ], 422);
            }

            return response()->json([
                'message' => 'Stock beginnings data parsed successfully.',
                'data' => [
                    'items' => $data['items'],
                ],
            ], 200);
        } catch (\Maatwebsite\Excel\Validators\ValidationException $e) {
            $errors = $e->failures()->map(function ($failure) {
                $row = $failure->row();
                $errorMessages = $failure->errors();
                return "Row {$row}: " . implode('; ', $errorMessages);
            })->toArray();

            return response()->json([
                'message' => 'Validation failed during import',
                'errors' => $errors,
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to parse stock beginnings',
                'errors' => [$e->getMessage()],
            ], 500);
        }
    }

    /**
     * Export stock beginnings to an Excel file.
     *
     * @param Request $request
     * @return \Symfony\Component\HttpFoundation\BinaryFileResponse
     */
    public function export(Request $request)
    {
        $this->authorize('viewAny', MainStockBeginning::class);

        $validated = $request->validate([
            'search' => 'nullable|string|max:255',
            'sortColumn' => 'nullable|string|in:' . implode(',', self::ALLOWED_SORT_COLUMNS),
            'sortDirection' => 'nullable|string|in:asc,desc',
        ]);

        $query = MainStockBeginning::with(['warehouse', 'stockBeginnings.productVariant.product'])
            ->when($validated['search'] ?? null, function ($query, $search) {
                $query->where('reference_no', 'like', "%{$search}%")
                    ->orWhereHas('warehouse', function ($q) use ($search) {
                        $q->where('name', 'like', "%{$search}%");
                    })
                    ->orWhereHas('stockBeginnings.productVariant.product', function ($q) use ($search) {
                        $q->where(function ($q2) use ($search) {
                            $q2->where('name', 'like', "%{$search}%")
                                ->orWhere('khmer_name', 'like', "%{$search}%")
                                ->orWhere('description', 'like', "%{$search}%")
                                ->orWhere('item_code', 'like', "%{$search}%")
                                ->orWhereHas('unit', function ($q3) use ($search) {
                                    $q3->where('name', 'like', "%{$search}%");
                                });
                        });
                    });
            });

        $sortColumn = in_array($validated['sortColumn'] ?? self::DEFAULT_SORT_COLUMN, self::ALLOWED_SORT_COLUMNS)
            ? $validated['sortColumn']
            : self::DEFAULT_SORT_COLUMN;
        $sortDirection = in_array(strtolower($validated['sortDirection'] ?? self::DEFAULT_SORT_DIRECTION), ['asc', 'desc'])
            ? $validated['sortDirection']
            : self::DEFAULT_SORT_DIRECTION;

        $query->orderBy($sortColumn, $sortDirection);

        return Excel::download(new StockBeginningsExport($query), 'stock_beginnings_' . now()->format('Ymd_His') . '.xlsx');
    }

    /**
     * Get validation rules for main stock beginning creation/update.
     *
     * @param int|null $mainStockBeginningId
     * @return array
     */
    private function mainStockBeginningValidationRules(?int $mainStockBeginningId = null): array
    {
        return [
            'beginning_date' => ['required', 'date', 'date_format:' . self::DATE_FORMAT],
            'warehouse_id' => ['required', 'integer', 'exists:warehouses,id'],
        ];
    }

    /**
     * Get validation rules for stock beginning line items.
     *
     * @return array
     */
    private function stockBeginningValidationRules(): array
    {
        return [
            'items' => ['required', 'array', 'min:1'],
            'items.*.id' => ['nullable', 'integer', 'exists:stock_beginnings,id'],
            'items.*.product_id' => ['required', 'integer', 'exists:product_variants,id'],
            'items.*.quantity' => ['required', 'numeric', 'min:0.01'],
            'items.*.unit_price' => ['required', 'numeric', 'min:0'],
            'items.*.remarks' => ['nullable', 'string', 'max:1000'],
        ];
    }

    /**
     * Generate a unique reference number in format STB-short_name-mmyyyy-sequence.
     *
     * @param int $warehouseId
     * @param string $beginningDate
     * @return string
     * @throws \InvalidArgumentException If the date format is invalid or warehouse is not found.
     */
    private function generateReferenceNo(int $warehouseId, string $beginningDate): string
    {
        $warehouse = Warehouse::with('building.campus')->findOrFail($warehouseId);

        try {
            $date = \Carbon\Carbon::createFromFormat(self::DATE_FORMAT, $beginningDate);
            if (!$date || $date->format(self::DATE_FORMAT) !== $beginningDate) {
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
        return "STB-{$shortName}-{$monthYear}-{$sequence}";
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
        $prefix = "STB-{$shortName}-{$monthYear}-";

        $count = MainStockBeginning::withTrashed()
            ->where('reference_no', 'like', "{$prefix}%")
            ->count();

        return str_pad($count + 1, 2, '0', STR_PAD_LEFT);
    }


    /**
     * Delete a main stock beginning and its associated line items and approvals.
     *
     * @param MainStockBeginning $mainStockBeginning
     * @return JsonResponse
     */
    public function destroy(MainStockBeginning $mainStockBeginning): JsonResponse
    {
        $this->authorize('delete', $mainStockBeginning);

        try {
            DB::transaction(function () use ($mainStockBeginning) {
                $userId = auth()->id() ?? 1;

                // Hard delete related approvals
                Approval::where([
                    'approvable_type' => MainStockBeginning::class,
                    'approvable_id' => $mainStockBeginning->id,
                ])->delete();

                // Soft delete related stock beginnings
                foreach ($mainStockBeginning->stockBeginnings as $stockBeginning) {
                    $stockBeginning->deleted_by = $userId;
                    $stockBeginning->save();
                    $stockBeginning->delete();
                }

                // Soft delete the main stock beginning
                $mainStockBeginning->deleted_by = $userId;
                $mainStockBeginning->save();
                $mainStockBeginning->delete();
            });

            return response()->json([
                'message' => 'Stock beginning and related approvals deleted successfully.',
            ], 200);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to delete stock beginning', ['error' => $e->getMessage(), 'id' => $mainStockBeginning->id]);
            return response()->json([
                'message' => 'Failed to delete stock beginning',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Submit an approval action (approve or reject) for a stock beginning.
     *
     * @param Request $request
     * @param int $documentId
     * @return JsonResponse
     */
    public function submitApproval(Request $request, MainStockBeginning $mainStockBeginning): JsonResponse
    {
        $validated = $request->validate([
            'request_type' => 'required|string|in:review,check,approve',
            'action' => 'required|string|in:approve,reject',
            'comment' => 'nullable|string|max:1000',
        ]);

        // Build permission string
        $permission = "mainStockBeginning.{$validated['request_type']}";

        // Check permission using Spatie's `can`
        if (!auth()->user()->can($permission)) {
            return response()->json([
                'message' => "You do not have permission to {$validated['request_type']} this stock beginning.",
            ], 403);
        }

        $method = $validated['action'] === 'approve' ? 'confirmApproval' : 'rejectApproval';

        $result = $this->approvalController->$method(
            $request,
            MainStockBeginning::class,
            $mainStockBeginning->id,
            $validated['request_type']
        );

        if ($result['success']) {
            // Update MainStockBeginning approval_status based on request_type and action
            if ($validated['action'] === 'approve') {
                $statusMap = [
                    'review' => 'Reviewed',
                    'check' => 'Checked',
                    'approve' => 'Approved',
                ];

                $mainStockBeginning->approval_status = $statusMap[$validated['request_type']] ?? null;
            } else {
                // Reject action sets status to 'Rejected'
                $mainStockBeginning->approval_status = 'Rejected';
            }

            // Save the updated status
            $mainStockBeginning->save();
        }

        return response()->json([
            'message' => $result['message'],
            'redirect_url' => route('approvals-stock-beginnings.show', $mainStockBeginning->id),
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
    public function reassignResponder(Request $request, MainStockBeginning $mainStockBeginning): JsonResponse
    {
        $this->authorize('reassign', $mainStockBeginning);

        $validated = $request->validate([
            'request_type' => 'required|string|in:review,check,approve',
            'new_user_id' => 'required|exists:users,id',
            'comment' => 'nullable|string|max:1000',
        ]);

        $user = User::findOrFail($validated['new_user_id']);
        $permission = "mainStockBeginning.{$validated['request_type']}";
        if (!$user->hasPermissionTo($permission)) {
            return response()->json([
                'message' => "User ID {$validated['new_user_id']} does not have permission for {$validated['request_type']}.",
            ], 403);
        }

        try {
            $approval = Approval::where([
                'approvable_type' => MainStockBeginning::class,
                'approvable_id' => $mainStockBeginning->id,
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
                'document_id' => $mainStockBeginning->id,
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
     * List approvals for a specific stock beginning.
     *
     * @param Request $request
     * @param int $documentId
     * @return JsonResponse
     */
    public function listApprovals(Request $request, $documentId): JsonResponse
    {
        $mainStockBeginning = MainStockBeginning::findOrFail($documentId);
        $this->authorize('view', $mainStockBeginning);

        $result = $this->approvalController->listApprovals($request, MainStockBeginning::class, $documentId);

        return response()->json([
            'message' => $result['message'],
            'approvals' => $result['approvals'] ?? null,
        ], $result['success'] ? 200 : 403);
    }

    /**
     * Determine if the authenticated user can see and interact with the approval button for a stock beginning.
     *
     * @param int $documentId
     * @return array
     */
    private function canShowApprovalButton(int $documentId): array
    {
        try {
            $mainStockBeginning = MainStockBeginning::findOrFail($documentId);
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
                'mainStockBeginning.review',
                'mainStockBeginning.check',
                'mainStockBeginning.approve'
            ]);
            if (!$hasPermission) {
                return [
                    'message' => 'Approval button not available: User lacks approval permissions.',
                    'showButton' => false,
                    'requestType' => null,
                ];
            }

            // Get all approvals for this stock beginning, ordered by ordinal and id
            $approvals = Approval::where([
                'approvable_type' => MainStockBeginning::class,
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
     * Initialize approvals for a stock beginning.
     *
     * @param MainStockBeginning $mainStockBeginning
     * @param array $approvals
     * @return void
     */
    protected function storeApprovals(MainStockBeginning $mainStockBeginning, array $approvals)
    {
        foreach ($approvals as $approval) {
            $approvalData = [
                'approvable_type' => MainStockBeginning::class,
                'approvable_id' => $mainStockBeginning->id,
                'document_name' => 'Stock Beginning',
                'document_reference' => $mainStockBeginning->reference_no,
                'request_type' => $approval['request_type'],
                'approval_status' => 'Pending',
                'ordinal' => $this->getOrdinalForRequestType($approval['request_type']),
                'requester_id' => $mainStockBeginning->created_by,
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
    public function fetchWarehousesForStockBeginning(Request $request)
    {
        $this->authorize('viewAny', MainStockBeginning::class);
        $user = $request->user();
        if($user->hasRole('admin')) {
            $response = $this->warehouseService->getWarehouses($request);
            return response()->json($response);
        }
        $response = $user->defaultWarehouse($request);
        if (!$response) {
            return response()->json([
                'message' => 'No default warehouse assigned to this user.',
            ], 404);
        }
        return response()->json([$response]);
    }


    public function fetProductsForStockBeginning(Request $request)
    {
        $this->authorize('viewAny', MainStockBeginning::class);
        $response = $this->productService->getStockManagedVariants($request);
        return response()->json($response);
    }
}