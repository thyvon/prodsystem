<?php

namespace App\Http\Controllers;

use App\Models\StockTransfer;
use App\Models\StockTransferItem;
use App\Models\Warehouse;
use App\Models\User;
use App\Models\Approval;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;
use Illuminate\Database\Eloquent\ModelNotFoundException;

use App\Services\ApprovalService;
use App\Services\ProductService;
use App\Services\WarehouseService;

class StockTransferController extends Controller
{
    private const ALLOWED_SORT_COLUMNS = ['transaction_date', 'reference_no', 'created_at', 'updated_at', 'created_by', 'updated_by'];
    private const DEFAULT_SORT_COLUMN = 'created_at';
    private const DEFAULT_SORT_DIRECTION = 'desc';
    private const DEFAULT_LIMIT = 10;
    private const MAX_LIMIT = 1000;
    private const DATE_FORMAT = 'Y-m-d';

    protected $approvalService;
    protected $productService;
    protected $warehouseService;

    public function __construct(
        ApprovalService $approvalService,
        ProductService $productService,
        WarehouseService $warehouseService
    ) {
        $this->middleware('auth'); // Ensure authentication for all methods
        $this->approvalService = $approvalService;
        $this->productService = $productService;
        $this->warehouseService = $warehouseService;
    }

    public function index()
    {
        $this->authorize('viewAny', StockTransfer::class);
        return view('Inventory.stockTransfer.index');
    }

    public function form(StockTransfer $stockTransfer = null): Response
    {
        $this->authorize($stockTransfer ? 'update' : 'create', [StockTransfer::class, $stockTransfer]);
        return view('Inventory/StockTransfer/Form', compact('stockTransfer'));
    }

    public function store(Request $request): JsonResponse
    {
        $this->authorize('create', StockTransfer::class);
        
        $validated = Validator::make($request->all(), array_merge(
            $this->stockTransferValidationRules(),
            $this->stockTransferItemValidationRules(),
            [
                'approvals' => 'required|array|min:1',
                'approvals.*.user_id' => 'required|exists:users,id',
                'approvals.*.request_type' => 'required|string|in:approve,receive',
            ]
        ))->validate();

        foreach ($validated['approvals'] as $approval) {
            $user = User::find($approval['user_id']);
            $permission = "stockTransfer.{$approval['request_type']}";
            if (!$user->hasPermissionTo($permission)) {
                return response()->json([
                    'message' => "User ID {$approval['user_id']} does not have permission for {$approval['request_type']}.",
                ], 403);
            }
        }

        try {
            return DB::transaction(function () use ($validated) {
                $referenceNo = $this->generateReferenceNo($validated['warehouse_id'], $validated['transaction_date']);
                $userPosition = auth()->user()->defaultPosition();
                if (!$userPosition) {
                    return response()->json(['message' => 'User does not have a default position set.'], 404);
                }

                $stockTransfer = StockTransfer::create([
                    'reference_no' => $referenceNo,
                    'transaction_date' => $validated['transaction_date'],
                    'warehouse_id' => $validated['warehouse_id'],
                    'destination_warehouse_id' => $validated['destination_warehouse_id'],
                    'remarks' => $validated['remarks'] ?? null,
                    'approval_status' => 'Pending',
                    'created_by' => auth()->id(),
                    'position_id' => $userPosition->id,
                ]);

                $items = array_map(function ($item) use ($stockTransfer) {
                    return [
                        'stock_transfer_id' => $stockTransfer->id,
                        'product_id' => $item['product_id'],
                        'quantity' => $item['quantity'],
                        'unit_price' => $item['unit_price'],
                        'total_price' => $item['quantity'] * $item['unit_price'],
                        'remarks' => $item['remarks'] ?? null,
                        'created_by' => auth()->id(),
                    ];
                }, $validated['items']);
                StockTransferItem::insert($items);

                $this->storeApprovals($stockTransfer, $validated['approvals']);

                return response()->json([
                    'message' => 'Stock transfer created successfully.',
                    'data' => $stockTransfer->load('stockTransferItems', 'approvals.responder'),
                ], 201);
            });
        } catch (\Exception $e) {
            Log::error('Failed to create stock transfer', ['error' => $e->getMessage()]);
            return response()->json([
                'message' => 'Failed to create stock transfer.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function getEditData(StockTransfer $stockTransfer): JsonResponse
    {
        try {
            $this->authorize('update', $stockTransfer);
            $stockTransfer->load('stockTransferItems', 'approvals.responder');
            return response()->json([
                'message' => 'Stock transfer retrieved successfully.',
                'data' => $stockTransfer,
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to retrieve stock transfer', ['id' => $stockTransfer->id, 'error' => $e->getMessage()]);
            return response()->json([
                'message' => 'Failed to retrieve stock transfer.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function update(Request $request, StockTransfer $stockTransfer): JsonResponse
    {
        $this->authorize('update', $stockTransfer);

        $validated = Validator::make($request->all(), array_merge(
            $this->stockTransferValidationRules(),
            $this->stockTransferItemValidationRules(),
            [
                'approvals' => 'required|array|min:1',
                'approvals.*.user_id' => 'required|exists:users,id',
                'approvals.*.request_type' => 'required|string|in:approve,receive',
            ]
        ))->validate();

        // Validate that each approver has the appropriate permission
        foreach ($validated['approvals'] as $approval) {
            $user = User::findOrFail($approval['user_id']);
            $permission = "stockTransfer.{$approval['request_type']}";
            if (!$user->hasPermissionTo($permission)) {
                return response()->json([
                    'message' => "User ID {$approval['user_id']} does not have permission for {$approval['request_type']}.",
                ], 403);
            }
        }

        try {
            return DB::transaction(function () use ($validated, $stockTransfer) {
                // Update stock transfer header
                $userPosition = auth()->user()->defaultPosition();
                if (!$userPosition) {
                    return response()->json([
                        'message' => 'No default position assigned to this user.',
                    ], 404);
                }

                $stockTransfer->update([
                    'transaction_date' => $validated['transaction_date'],
                    'warehouse_id' => $validated['warehouse_id'],
                    'destination_warehouse_id' => $validated['destination_warehouse_id'],
                    'remarks' => $validated['remarks'] ?? null,
                    'updated_by' => auth()->id() ?? 1,
                    'position_id' => $userPosition->id,
                ]);

                // Reset Returned status
                if ($stockTransfer->approval_status === 'Returned') {
                    $stockTransfer->update([
                        'approval_status' => 'Pending',
                    ]);

                    Approval::where([
                        'approvable_type' => StockTransfer::class,
                        'approvable_id' => $stockTransfer->id,
                    ])->update([
                        'approval_status' => 'Pending',
                        'responded_date' => null,
                        'comment' => null,
                    ]);
                }

                // Build existing and new composite approval keys
                $existingApprovalKeys = $stockTransfer->approvals->map(
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
                        'approvable_type' => StockTransfer::class,
                        'approvable_id' => $stockTransfer->id,
                        'responder_id' => $userId,
                        'position_id' => $positionId,
                        'request_type' => $requestType,
                    ])->delete();
                }

                // Determine approvals to add
                $approvalsToAdd = array_diff($newApprovalKeys, $existingApprovalKeys);
                foreach ($approvalsToAdd as $approvalKey) {
                    [$userId, $positionId, $requestType] = explode('|', $approvalKey);
                    $approvalData = [
                        'approvable_type' => StockTransfer::class,
                        'approvable_id' => $stockTransfer->id,
                        'document_name' => 'Stock Transfer',
                        'document_reference' => $stockTransfer->reference_no,
                        'request_type' => $requestType,
                        'approval_status' => 'Pending',
                        'ordinal' => $this->getOrdinalForRequestType($requestType),
                        'requester_id' => $stockTransfer->created_by,
                        'responder_id' => $userId,
                        'position_id' => $positionId,
                    ];
                    $this->approvalService->storeApproval($approvalData);
                }

                // Handle stock transfer line items
                $existingItemIds = $stockTransfer->stockTransferItems->pluck('id')->toArray();
                $submittedItemIds = array_filter(array_column($validated['items'], 'id'), fn($id) => !is_null($id));

                // Delete removed items
                StockTransferItem::where('stock_transfer_id', $stockTransfer->id)
                    ->whereNotIn('id', $submittedItemIds)
                    ->each(function ($stockTransferItem) {
                        $stockTransferItem->deleted_by = auth()->id() ?? 1;
                        $stockTransferItem->save();
                        $stockTransferItem->delete();
                    });

                // Process items: update or insert
                $itemsToInsert = [];
                foreach ($validated['items'] as $item) {
                    if (!empty($item['id']) && in_array($item['id'], $existingItemIds)) {
                        // Update existing
                        $stockTransferItem = StockTransferItem::find($item['id']);
                        if ($stockTransferItem) {
                            $stockTransferItem->update([
                                'product_id' => $item['product_id'],
                                'quantity' => $item['quantity'],
                                'unit_price' => $item['unit_price'],
                                'total_price' => $item['quantity'] * $item['unit_price'],
                                'remarks' => $item['remarks'] ?? null,
                                'updated_by' => auth()->id() ?? 1,
                            ]);
                        }
                    } else {
                        // Prepare for insert
                        $itemsToInsert[] = [
                            'stock_transfer_id' => $stockTransfer->id,
                            'product_id' => $item['product_id'],
                            'quantity' => $item['quantity'],
                            'unit_price' => $item['unit_price'],
                            'total_price' => $item['quantity'] * $item['unit_price'],
                            'remarks' => $item['remarks'] ?? null,
                            'created_by' => auth()->id() ?? 1,
                            'updated_by' => auth()->id() ?? 1,
                            'created_at' => now(),
                            'updated_at' => now(),
                        ];
                    }
                }

                if (!empty($itemsToInsert)) {
                    StockTransferItem::insert($itemsToInsert);
                }

                return response()->json([
                    'message' => 'Stock transfer updated successfully.',
                    'data' => $stockTransfer->load('stockTransferItems', 'approvals.responder'),
                ]);
            });
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to update stock transfer', ['id' => $stockTransfer->id, 'error' => $e->getMessage()]);
            return response()->json([
                'message' => 'Failed to update stock transfer.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    private function stockTransferValidationRules(): array
    {
        return [
            'transaction_date' => 'required|date_format:' . self::DATE_FORMAT,
            'warehouse_id' => 'required|exists:warehouses,id',
            'destination_warehouse_id' => 'required|exists:warehouses,id|different:warehouse_id',
            'remarks' => 'nullable|string|max:1000',
        ];
    }

    private function stockTransferItemValidationRules(): array
    {
        return [
            'items' => 'required|array|min:1',
            'items.*.id' => 'nullable|exists:stock_transfer_items,id',
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.quantity' => 'required|numeric|min:0.01',
            'items.*.unit_price' => 'required|numeric|min:0',
            'items.*.remarks' => 'nullable|string|max:500',
        ];
    }

    private function generateReferenceNo(int $warehouseId, string $transactionDate): string
    {
        $warehouse = Warehouse::with('building.campus')->findOrFail($warehouseId);

        try {
            $date = \Carbon\Carbon::createFromFormat(self::DATE_FORMAT, $transactionDate);
            if (!$date || $date->format(self::DATE_FORMAT) !== $transactionDate) {
                throw new \InvalidArgumentException('Invalid date format. Expected ' . self::DATE_FORMAT . '.');
            }
        } catch (\Exception $e) {
            throw new \InvalidArgumentException('Invalid date format. Expected ' . self::DATE_FORMAT . '.', 0, $e);
        }

        $shortName = $warehouse->building?->campus?->short_name ?? 'WH';
        $monthYear = $date->format('my');
        $sequence = $this->getSequenceNumber($shortName, $monthYear);
        return "STT-{$shortName}-{$monthYear}-{$sequence}";
    }

    private function getSequenceNumber(string $shortName, string $monthYear): string
    {
        $prefix = "STT-{$shortName}-{$monthYear}-";
        $count = StockTransfer::withTrashed()
            ->where('reference_no', 'like', "{$prefix}%")
            ->count();
        return str_pad($count + 1, 2, '0', STR_PAD_LEFT);
    }

    protected function storeApprovals(StockTransfer $stockTransfer, array $approvals)
    {
        foreach ($approvals as $approval) {
            $approvalData = [
                'approvable_type' => StockTransfer::class,
                'approvable_id' => $stockTransfer->id,
                'document_name' => 'Stock Transfer',
                'document_reference' => $stockTransfer->reference_no,
                'request_type' => $approval['request_type'],
                'approval_status' => 'Pending',
                'ordinal' => $this->getOrdinalForRequestType($approval['request_type']),
                'requester_id' => $stockTransfer->created_by,
                'responder_id' => $approval['user_id'],
                'position_id' => User::find($approval['user_id'])?->defaultPosition()?->id,
            ];
            $this->approvalService->storeApproval($approvalData);
        }
    }

    protected function getOrdinalForRequestType($requestType)
    {
        $ordinals = [
            'approve' => 1,
            'receive' => 2,
        ];
        return $ordinals[$requestType] ?? 1;
    }

    public function getWareHousesFrom(Request $request): JsonResponse
    {
        $this->authorize('viewAny', StockTransfer::class);

        $user = $request->user();
        if ($user->hasRole('admin')) {
            $warehousesFrom = $this->warehouseService->getWarehouses($request);
            return response()->json($warehousesFrom);
        }

        if (!$user->warehouses()->exists()) {
            return response()->json([
                'message' => 'No warehouses assigned to this user.',
            ], 404);
        }

        $warehousesFrom = $user->warehouses()->get();
        return response()->json($warehousesFrom);
    }

    public function getWareHousesTo(Request $request): JsonResponse
    {
        $this->authorize('viewAny', StockTransfer::class);

        $user = $request->user();
        if ($user->hasRole('admin')) {
            $warehousesTo = $this->warehouseService->getWarehouses($request);
            return response()->json($warehousesTo);
        }

        if (!$user->warehouses()->exists()) {
            return response()->json([
                'message' => 'No warehouses assigned to this user.',
            ], 404);
        }

        $warehousesTo = $user->warehouses()->get();
        return response()->json($warehousesTo);
    }

    public function getProducts(Request $request): JsonResponse
    {
        $this->authorize('viewAny', StockTransfer::class);
        $response = $this->productService->getStockManagedVariants($request);
        $filteredResponse = [
            'data' => collect($response['data'])->filter(function ($item) {
                return $item['is_active'] == 1;
            })->values()->all(),
            'recordsTotal' => $response['recordsTotal'],
            'recordsFiltered' => count($response['data']),
            'draw' => $response['draw'],
        ];
        return response()->json($filteredResponse);
    }

    public function getProductsStockAndPrice(Request $request): JsonResponse
    {
        $this->authorize('viewAny', StockTransfer::class);
        
        $response = $this->productService->getStockManagedVariants($request);
        
        $filteredResponse = [
            'data' => collect($response['data'])->filter(function ($item) {
                return $item['is_active'] == 1;
            })->map(function ($item) {
                return [
                    'id' => $item['id'],
                    'stock_on_hand' => $item['stock_on_hand'],
                    'average_price' => $item['average_price'],
                ];
            })->values()->all(),
            'recordsTotal' => $response['recordsTotal'],
            'recordsFiltered' => count($response['data']),
            'draw' => $response['draw'],
        ];
        
        return response()->json($filteredResponse);
    }

    public function getUsersForApproval(Request $request): JsonResponse
    {
        $this->authorize('viewAny', StockTransfer::class);

        // Validate request type
        $validated = $request->validate([
            'request_type' => ['required', 'string', 'in:approve,receive'],
        ]);

        $permission = "stockTransfer.{$validated['request_type']}";
        $authUser = $request->user();
        $isAdmin = $authUser->hasRole('admin');

        try {
            // Get department IDs of the authenticated user (only if not admin)
            $authDepartmentIds = !$isAdmin
                ? $authUser->departments()->pluck('departments.id')->toArray()
                : [];

            // Fetch users with direct or role-based permission
            $usersQuery = User::query()
                ->where(function ($query) use ($permission) {
                    $query->whereHas('permissions', fn ($q) => $q->where('name', $permission))
                        ->orWhereHas('roles.permissions', fn ($q) => $q->where('name', $permission));
                });

            // Apply department filter only for non-admin users
            if (!$isAdmin) {
                $usersQuery->whereHas('departments', fn ($q) => $q->whereIn('departments.id', $authDepartmentIds));
            }

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
}