<?php

namespace App\Http\Controllers;

use App\Models\StockTransfer;
use App\Models\StockTransferItem;
use App\Models\Warehouse;
use App\Models\User;
use App\Models\Approval;
use App\Imports\StockTransfersImport;
use Maatwebsite\Excel\Facades\Excel;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\View\View;

use App\Services\ApprovalService;
use App\Services\ProductService;
use App\Services\WarehouseService;

class StockTransferController extends Controller
{
    private const ALLOWED_SORT_COLUMNS = [
        'reference_no',
        'transaction_date',
        'from_warehouse',
        'created_at',
    ];
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

    public function getStockTransfers(Request $request): JsonResponse
    {
        $this->authorize('viewAny', StockTransfer::class);

        $user = auth()->user();
        $isAdmin = $user->hasRole('admin');
        $campusIds = $user->campus->pluck('id')->toArray();

        $validated = $request->validate([
            'search' => 'nullable|string|max:255',
            'sortColumn' => 'nullable|string',
            'sortDirection' => 'nullable|string|in:asc,desc',
            'limit' => 'nullable|integer|min:1|max:' . self::MAX_LIMIT,
            'page' => 'nullable|integer|min:1',
            'draw' => 'nullable|integer',
        ]);

        $sortColumn = $validated['sortColumn'] ?? 'stock_transfers.id';
        $sortDirection = $validated['sortDirection'] ?? 'desc';

        $query = StockTransfer::with([
            'warehouse',
            'destinationWarehouse',
            'updatedBy',
        ])
        ->when(!$isAdmin, fn($q) => $q->whereHas('warehouse.building.campus', fn($q2) => $q2->whereIn('id', $campusIds)))
        ->when($validated['search'] ?? null, fn($q, $search) => $q->where(fn($subQ) =>
            $subQ->where('reference_no', 'like', "%{$search}%")
                ->orWhereHas('warehouse', fn($wQ) => $wQ->where('name', 'like', "%{$search}%"))
                ->orWhereHas('createdBy', fn($cQ) => $cQ->where('name', 'like', "%{$search}%"))
        ));

        // Sorting via join for relational columns
        if ($sortColumn === 'from_warehouse') {
            $query
                ->join('warehouses', 'stock_transfers.warehouse_id', '=', 'warehouses.id')
                ->orderBy('warehouses.name', $sortDirection)
                ->select('stock_transfers.*');
        } elseif ($sortColumn === 'created_by') {
            $query->join('users', 'stock_transfers.created_by', '=', 'users.id')
                ->orderBy('users.name', $sortDirection)
                ->select('stock_transfers.*');
        } else {
            $query->orderBy($sortColumn, $sortDirection);
        }

        $stockTransfers = $query->paginate(
            $validated['limit'] ?? self::DEFAULT_LIMIT,
            ['*'],
            'page',
            $validated['page'] ?? 1
        );

        $stockTransfersMapped = $stockTransfers->map(fn($transfer) => [
            'id' => $transfer->id,
            'reference_no' => $transfer->reference_no,
            'transaction_date' => $transfer->transaction_date,
            'from_warehouse' => $transfer->warehouse->name ?? null,
            'to_warehouse' => $transfer->destinationWarehouse->name ?? null,
            'purpose' => $transfer->remarks,
            'total_price' => $transfer->stockTransferItems->sum('total_price'),
            'created_by' => $transfer->createdBy->name ?? null,
            'created_at' => $transfer->created_at,
            'updated_at' => $transfer->updated_at,
            'approval_status' => $transfer->approval_status,
        ]);

        return response()->json([
            'data' => $stockTransfersMapped,
            'recordsTotal' => $stockTransfers->total(),
            'recordsFiltered' => $stockTransfers->total(),
            'draw' => (int) ($validated['draw'] ?? 1),
        ]);
    }

    public function form(StockTransfer $stockTransfer = null): View
    {
        $this->authorize($stockTransfer ? 'update' : 'create', [StockTransfer::class, $stockTransfer]);
        return view('Inventory.stockTransfer.form', compact('stockTransfer'));
    }

    public function import(Request $request): JsonResponse
    {
        $this->authorize('create', StockTransfer::class);

        $validated = $request->validate([
            'file' => 'required|file|mimes:xlsx,xls,csv|max:2048',
        ]);

        try {
            $import = new StockTransfersImport();
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
                'message' => 'Stock transfers data parsed successfully.',
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
                'message' => 'Failed to parse stock transfers',
                'errors' => [$e->getMessage()],
            ], 500);
        }
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
                'approvals.*.request_type' => 'required|string|in:approve,initial',
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
                'approvals.*.request_type' => 'required|string|in:approve,initial',
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


    public function show(StockTransfer $stockTransfer)
    {
        $this->authorize('view', $stockTransfer);

        try {
            // Load related data including approvals
            $stockTransfer->load([
                'stockTransferItems.productVariant.product.unit',
                'warehouse.building.campus',
                'destinationWarehouse.building.campus',
                'createdBy',
                'updatedBy',
                'creatorPosition',
                'approvals.responder',
                'approvals.responderPosition',
            ]);

            // Check if the approval button should be shown
            $approvalButtonData = $this->canShowApprovalButton($stockTransfer->id);

            // Derive responders from approvals
            $responders = $stockTransfer->approvals->map(function ($approval) {
                return [
                    'id' => $approval->id,
                    'user_id' => $approval->responder_id,
                    'position_id' => $approval->position_id,
                    'request_type' => $approval->request_type,
                    'name' => $approval->responder->name ?? 'N/A',
                ];
            })->toArray();

            // Track the count of each request_type as we process approvals
            $typeOccurrenceCounts = [];

            // Map approvals with dynamic request_type_label
            $approvals = $stockTransfer->approvals
                ->sortBy('ordinal')
                ->values()
                ->map(function ($approval) use (&$typeOccurrenceCounts) {
                    $typeMap = [
                        'approve' => 'Approved',
                        'initial'   => 'Initialed',
                    ];

                    // Get the base label from typeMap or fallback to ucfirst
                    $label = $typeMap[$approval->request_type] ?? ucfirst($approval->request_type);

                    // Increment the occurrence count for this request_type
                    $typeOccurrenceCounts[$approval->request_type] = 
                        ($typeOccurrenceCounts[$approval->request_type] ?? 0) + 1;

                    // Add "Co-" prefix if this is the second or later occurrence
                    if ($typeOccurrenceCounts[$approval->request_type] > 1) {
                        $label = 'Co-' . $label;
                    }

                    return [
                        'id' => $approval->id,
                        'request_type' => $approval->request_type,
                        'approval_status' => $approval->approval_status,
                        'request_type_label' => $label,
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
                })->toArray();

            return view('Inventory.stockTransfer.show', [
                'stockTransfer' => $stockTransfer,
                'totalQuantity' => round($stockTransfer->stockTransferItems->sum('quantity'), 4),
                'totalValue' => round($stockTransfer->stockTransferItems->sum('total_price'), 4),
                'approvals' => $approvals,
                'responders' => $responders,
                'showApprovalButton' => $approvalButtonData['showButton'],
                'approvalRequestType' => $approvalButtonData['requestType'],
                'approvalButtonData' => $approvalButtonData,
            ]);
            Log::info('Stock transfer displayed', ['id' => $stockTransfer->id]);
        } catch (\Exception $e) {
            Log::error('Error fetching stock transfer for display', [
                'error_message' => $e->getMessage(),
                'stock_transfer_id' => $stockTransfer->id,
            ]);
            return response()->view('errors.500', [
                'message' => 'Failed to fetch stock transfer',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function destroy(StockTransfer $stockTransfer): JsonResponse
    {
        $this->authorize('delete', $stockTransfer);

        try {
            DB::transaction(function () use ($stockTransfer) {
                $userId = auth()->id() ?? 1;

                // Hard delete related approvals
                Approval::where([
                    'approvable_type' => StockTransfer::class,
                    'approvable_id' => $stockTransfer->id,
                ])->delete();

                // Soft delete related stock transfers
                foreach ($stockTransfer->stockTransferItems as $stockTransferItem) {
                    $stockTransferItem->deleted_by = $userId;
                    $stockTransferItem->save();
                    $stockTransferItem->delete();
                }

                // Soft delete the stock transfer
                $stockTransfer->deleted_by = $userId;
                $stockTransfer->save();
                $stockTransfer->delete();
            });

            return response()->json([
                'message' => 'Stock transfer and related approvals deleted successfully.',
            ], 200);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to delete stock transfer', ['error' => $e->getMessage(), 'id' => $stockTransfer->id]);
            return response()->json([
                'message' => 'Failed to delete stock transfer',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    private function stockTransferValidationRules(): array
    {
        return [
            'transaction_date' => 'required|date_format:' . self::DATE_FORMAT,
            'warehouse_id' => 'required|exists:warehouses,id|different:destination_warehouse_id',
            'destination_warehouse_id' => 'required|exists:warehouses,id|different:warehouse_id',
            'remarks' => 'nullable|string|max:1000',
        ];
    }

    private function stockTransferItemValidationRules(): array
    {
        return [
            'items' => 'required|array|min:1',
            'items.*.id' => 'nullable|exists:stock_transfer_items,id',
            'items.*.product_id' => 'required|exists:product_variants,id',
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

    public function submitApproval(Request $request, StockTransfer $stockTransfer, ApprovalService $approvalService): JsonResponse 
    {
        // Validate request
        $validated = $request->validate([
            'request_type' => 'required|string|in:initial,approve',
            'action'       => 'required|string|in:approve,reject,return',
            'comment'      => 'nullable|string|max:1000',
        ]);

        // Check user permission
        $permission = "stockTransfer.{$validated['request_type']}";
        if (!auth()->user()->can($permission)) {
            return response()->json([
                'message' => "You do not have permission to {$validated['request_type']} this stock transfer.",
            ], 403);
        }

        // Process approval via ApprovalService
        $result = $approvalService->handleApprovalAction(
            $stockTransfer,
            $validated['request_type'],
            $validated['action'],
            $validated['comment'] ?? null
        );

        // Ensure $result has 'success' key
        $success = $result['success'] ?? false;

        // Update StockTransfer approval_status if successful
        if ($success) {
            $statusMap = [
                'initial' => 'Initialed',
                'approve' => 'Approved',
                'reject'  => 'Rejected',
                'return'  => 'Returned',
            ];

            $stockTransfer->approval_status =
                $statusMap[$validated['action']] ??
                ($statusMap[$validated['request_type']] ?? 'Pending');

            $stockTransfer->save();
        }

        return response()->json([
            'message'      => $result['message'] ?? 'Action failed',
            'redirect_url' => route('approvals-stock-transfers.show', $stockTransfer->id),
            'approval'     => $result['approval'] ?? null,
        ], $success ? 200 : 400);
    }

    public function reassignResponder(Request $request, StockTransfer $stockTransfer): JsonResponse
    {
        $this->authorize('reassign', $stockTransfer);

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

        if (!$user->hasPermissionTo("stockTransfer.{$validated['request_type']}")) {
            return response()->json([
                'success' => false,
                'message' => "User {$user->id} does not have permission for {$validated['request_type']}.",
            ], 403);
        }

        $approval = Approval::where([
            'approvable_type' => StockTransfer::class,
            'approvable_id'   => $stockTransfer->id,
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
                'document_id'  => $stockTransfer->id,
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

    protected function getOrdinalForRequestType($requestType)
    {
        $ordinals = [
            'approve' => 2,
            'initial' => 1,
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

    public function getUsersForApproval(Request $request): JsonResponse
    {
        $this->authorize('viewAny', StockTransfer::class);

        // Validate request type
        $validated = $request->validate([
            'request_type' => ['required', 'string', 'in:approve,initial'],
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

    private function canShowApprovalButton(int $documentId): array
    {
        try {
            $userId = auth()->id();
            if (!$userId) {
                return $this->approvalButtonResponse('User not authenticated.');
            }

            $approvals = Approval::where([
                'approvable_type' => StockTransfer::class,
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
}