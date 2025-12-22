<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;
use App\Imports\StockCountImport;
use Maatwebsite\Excel\Facades\Excel;
use App\Services\ApprovalService;
use App\Services\ProductService;
use App\Services\StockLedgerService;

use App\Models\StockCount;
use App\Models\StockCountItems;
use App\Models\ProductVariant;
use App\Models\Warehouse;
use App\Models\User;
use App\Models\Approval;

class StockCountController extends Controller
{
    protected $approvalService;
    protected $productService;
    protected $stockLedgerService;

    public function __construct(
        ApprovalService $approvalService,
        ProductService $productService,
        StockLedgerService $stockLedgerService
    ) {
        $this->middleware('auth'); // Ensure authentication for all methods
        $this->approvalService = $approvalService;
        $this->productService = $productService;
        $this->stockLedgerService = $stockLedgerService;
    }
    private const ALLOWED_SORT_COLUMNS = [
        'transaction_date',
        'reference_no',
    ];
    private const DEFAULT_SORT_DIRECTION = 'desc';
    private const DEFAULT_LIMIT = 10;
    private const MAX_LIMIT = 1000;
    private const DATE_FORMAT = 'Y-m-d';

    public function index(): View
    {
        $this->authorize('viewAny', StockCount::class);

        return view('Inventory.stock-count.index');
    }
    public function create(){
        $this ->authorize('create', StockCount::class);

        return view('Inventory.stock-count.form');
    }

    public function show(StockCount $stockCount): View
    {
        $this->authorize('view', $stockCount);

        // Eager load nested relationships
        $stockCount->load([
            'items.product.product.unit',
            'approvals.responder',
            'warehouse.building.campus',
            'creator',
            'creatorPosition',
            'approvals.responderPosition'
        ]);

        // Map items
        $items = $stockCount->items->map(function ($item) use ($stockCount) {
            $product = $item->product?->product;

            $stockOnHand = 0;
            $averagePrice = 0;

            if ($item->product_id) {
                try {
                    $stockOnHand = $this->stockLedgerService->getStockOnHand(
                        $item->product_id,
                        $stockCount->warehouse_id,
                        $stockCount->transaction_date
                    );
                } catch (\Exception $e) {}

                try {
                    $averagePrice = $this->stockLedgerService->getAvgPrice(
                        $item->product_id,
                        $stockCount->transaction_date
                    );
                } catch (\Exception $e) {}
            }

            return [
                'id' => $item->id,
                'product_id' => $item->product_id,
                'product_code' => $item->product?->item_code ?? '',
                'description' => trim(($product->name ?? '') . ' ' . ($item->product?->description ?? '')),
                'unit_name' => $product?->unit->name ?? '',
                'ending_quantity' => $item->ending_quantity,
                'counted_quantity' => $item->counted_quantity,
                'remarks' => $item->remarks,
                'stock_on_hand' => $stockOnHand,
                'average_price' => $averagePrice,
            ];
        });

        // Map approvals
        $approvalLabels = ['initial' => 'Initialed', 'approve' => 'Approved'];

        $approvals = $stockCount->approvals->map(fn($a) => [
            'id' => $a->id,
            'user_id' => $a->responder_id,
            'request_type' => $a->request_type,
            'approval_status' => $a->approval_status,
            'responder_name' => $a->responder?->name ?? '',
            'position_name' => $a->responderPosition?->title ?? '',
            'profile_picture' => $a->responder?->profile_url ?? '',
            'signature' => $a->responder?->signature_url ?? '',
            'responded_date' => $a->responded_date ?? '',
            'comment' => $a->comment ?? '',
            'label' => $approvalLabels[$a->request_type] ?? '',
        ]);

        $approvalButtons = $this->canShowApprovalButton($stockCount->id);

        // Mark current user's pending approvals as seen
        if ($approvalButtons['showButton']) {
            $stockCount->approvals()
                ->where('responder_id', auth()->id())
                ->where('approval_status', 'Pending')
                ->where('is_seen', false)
                ->update(['is_seen' => true]);
        }

        // Prepare initial data for Vue props
        $initialData = [
            'id' => $stockCount->id,
            'transaction_date' => $stockCount->transaction_date,
            'warehouse_id' => $stockCount->warehouse_id,
            'warehouse_name' => $stockCount->warehouse->name,
            'warehouse_campus' => $stockCount->warehouse->building->campus->short_name ?? '',
            'remarks' => $stockCount->remarks,
            'reference_no' => $stockCount->reference_no,
            'prepared_by' => $stockCount->creator->name,
            'creator_position' => $stockCount->creatorPosition?->title ?? '',
            'creator_profile_picture' => $stockCount->creator->profile_url ?? '',
            'creator_signature' => $stockCount->creator->signature_url ?? '',
            'card_number' => $stockCount->creator->card_number ?? '',
            'items' => $items,
            'approvals' => $approvals,
            'approval_buttons' => $approvalButtons,
        ];

        return view('Inventory.stock-count.show', [
            'initialData' => $initialData
        ]);
    }


    public function getStockCountList(Request $request): JsonResponse
    {
        $this->authorize('viewAny', StockCount::class);

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

        $sortColumn = $validated['sortColumn'] ?? 'id';
        $sortDirection = $validated['sortDirection'] ?? 'desc';
        $limit = $validated['limit'] ?? self::DEFAULT_LIMIT;
        $page = $validated['page'] ?? 1;

        $query = StockCount::with(['warehouse', 'creator'])
            ->when(!$isAdmin, fn($q) => $q->whereHas('warehouse.building.campus', fn($q2) => $q2->whereIn('id', $campusIds)))
            ->when($validated['search'] ?? null, fn($q, $search) => $q->where(function ($subQ) use ($search) {
                $subQ->where('reference_no', 'like', "%{$search}%")
                    ->orWhereHas('warehouse', fn($wQ) => $wQ->where('name', 'like', "%{$search}%"))
                    ->orWhereHas('creator', fn($cQ) => $cQ->where('name', 'like', "%{$search}%"));
            }));

        // Simple relational sorting
        if ($sortColumn === 'warehouse') {
            $query->join('warehouses', 'stock_counts.warehouse_id', '=', 'warehouses.id')
                ->orderBy('warehouses.name', $sortDirection)
                ->select('stock_counts.*');
        } elseif ($sortColumn === 'created_by') {
            $query->join('users', 'stock_counts.created_by', '=', 'users.id')
                ->orderBy('users.name', $sortDirection)
                ->select('stock_counts.*');
        } else {
            $query->orderBy($sortColumn, $sortDirection);
        }

        $stockCounts = $query->paginate($limit, ['*'], 'page', $page);

        $data = $stockCounts->map(fn($sc) => [
            'id' => $sc->id,
            'reference_no' => $sc->reference_no,
            'transaction_date' => $sc->transaction_date,
            'warehouse' => $sc->warehouse->name ?? null,
            'warehouse_campus' => $sc->warehouse->building->campus->short_name ?? null,
            'remarks' => $sc->remarks,
            'total_items' => $sc->items->count(),
            'total_counted' => $sc->items->sum('counted_quantity'),
            'created_by' => $sc->creator->name ?? null,
            'created_at' => $sc->created_at,
            'updated_at' => $sc->updated_at,
            'approval_status' => $sc->approval_status,
        ]);

        return response()->json([
            'data' => $data,
            'recordsTotal' => $stockCounts->total(),
            'recordsFiltered' => $stockCounts->total(),
            'draw' => (int) ($validated['draw'] ?? 1),
        ]);
    }



    public function store(Request $request): JsonResponse
    {
        $this ->authorize('create', StockCount::class);
        $validated = Validator::make(
            $request->all(),
            array_merge(
                $this->stockCountValidationRules(),
                $this->stockCountItemValidationRules(),
                [
                    'approvals' => 'required|array|min:1',
                    'approvals.*.user_id' => 'required|exists:users,id',
                    'approvals.*.request_type' => 'required|string|in:approve,initial',
                ]
            )
        )->validate();

        // Validate approval permissions
        foreach ($validated['approvals'] as $approval) {
            $user = User::find($approval['user_id']);
            $permission = "stockCount.{$approval['request_type']}";
            if (!$user->hasPermissionTo($permission)) {
                return response()->json([
                    'message' => "User ID {$approval['user_id']} does not have permission for {$approval['request_type']}.",
                ], 403);
            }
        }

        try {
            return DB::transaction(function () use ($validated) {

                // Generate reference number
                $referenceNo = $this->generateReferenceNo(
                    $validated['warehouse_id'],
                    $validated['transaction_date']
                );

                // Create stock count
                $stockCount = StockCount::create([
                    'transaction_date' => $validated['transaction_date'],
                    'reference_no' => $referenceNo,
                    'warehouse_id' => $validated['warehouse_id'],
                    'remarks' => $validated['remarks'] ?? null,
                    'approval_status' => 'Pending',
                    'created_by' => auth()->id(),
                    'position_id' => auth()->user()->current_position_id
                ]);

                // Create each stock count item using Eloquent create()
                foreach ($validated['items'] as $item) {
                    $stockCount->items()->create([
                        'product_id'        => $item['product_id'],
                        'ending_quantity'   => $item['ending_quantity'],
                        'counted_quantity'  => $item['counted_quantity'],
                        'remarks'           => $item['remarks'] ?? null,
                        'created_by'        => auth()->id(),
                    ]);
                }

                // Save approvals
                $this->storeApprovals($stockCount, $validated['approvals']);

                return response()->json([
                    'message' => 'Stock count created successfully.',
                    'data' => $stockCount->load('items.product', 'approvals.responder'),
                ], 201);
            });

        } catch (\Exception $e) {
            Log::error('Failed to create stock count', ['error' => $e->getMessage()]);
            return response()->json([
                'message' => 'Failed to create stock count.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
    public function import(Request $request): JsonResponse
    {
        $this->authorize('create', StockCount::class);

        $request->validate([
            'file' => 'required|file|mimes:xlsx,xls,csv|max:2048',
        ]);

        try {
            $import = new StockCountImport();
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
                    'errors' => ['No valid rows found.'],
                ], 422);
            }

            return response()->json([
                'message' => 'Stock count items imported successfully.',
                'data' => [
                    'items' => $data['items'],
                ],
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to import stock count items.',
                'errors' => [$e->getMessage()],
            ], 500);
        }
    }


    public function edit(StockCount $stockCount): View
    {
        $this->authorize('update', $stockCount);

        // Eager load relationships
        $stockCount->load([
            'items.product.product.unit',
            'approvals.responder'
        ]);

        $items = $stockCount->items->map(function ($item) use ($stockCount) {
            $product = $item->product?->product;

            // Compute stock and average price safely
            $stockOnHand = 0;
            $averagePrice = 0;

            if ($item->product_id) {
                try {
                    $stockOnHand = $this->stockLedgerService->getStockOnHand(
                        $item->product_id,
                        $stockCount->warehouse_id,
                        $stockCount->transaction_date
                    );
                } catch (\Exception $e) {}

                try {
                    $averagePrice = $this->stockLedgerService->getAvgPrice(
                        $item->product_id,
                        $stockCount->transaction_date
                    );
                } catch (\Exception $e) {}
            }

            return [
                'id' => $item->id,
                'product_id' => $item->product_id,
                'product_code' => $item->product?->item_code ?? '',
                'description' => trim(($product->name ?? '') . ' ' . ($item->product?->description ?? '')),
                'unit_name' => $product?->unit->name ?? '',
                'ending_quantity' => $item->ending_quantity,
                'counted_quantity' => $item->counted_quantity,
                'remarks' => $item->remarks,
                'stock_on_hand' => $stockOnHand,
                'average_price' => $averagePrice,
            ];
        });

        $approvals = $stockCount->approvals->map(fn($a) => [
            'id' => $a->id,
            'user_id' => $a->responder_id,
            'request_type' => $a->request_type,
            'approval_status' => $a->approval_status,
            'responder_name' => $a->responder?->name ?? '',
        ]);

        // Prepare initial data for Vue props
        $initialData = [
            'id' => $stockCount->id,
            'transaction_date' => $stockCount->transaction_date,
            'warehouse_id' => $stockCount->warehouse_id,
            'remarks' => $stockCount->remarks,
            'reference_no' => $stockCount->reference_no,
            'items' => $items,
            'approvals' => $approvals,
            'buttonSubmitText' => $stockCount->approval_status === 'Returned' ? 'Re-Submit' : 'Update',
        ];

        return view('Inventory.stock-count.form', [
            'initialData' => $initialData,
        ]);
    }

    public function submitApproval(Request $request, StockCount $stockCount, ApprovalService $approvalService): JsonResponse 
    {
        // Validate request
        $validated = $request->validate([
            'request_type' => 'required|string|in:initial,approve',
            'action'       => 'required|string|in:approve,reject,return',
            'comment'      => 'nullable|string|max:1000',
        ]);

        // Check user permission
        $permission = "stockCount.{$validated['request_type']}";
        if (!auth()->user()->can($permission)) {
            return response()->json([
                'message' => "You do not have permission to {$validated['request_type']} this stock count.",
            ], 403);
        }

        // Process approval via ApprovalService
        $result = $approvalService->handleApprovalAction(
            $stockCount,
            $validated['request_type'],
            $validated['action'],
            $validated['comment'] ?? null
        );

        $success = $result['success'] ?? false;

        // Map status
        $statusMap = [
            'initial' => 'Initialed',
            'approve' => 'Approved',
            'reject'  => 'Rejected',
            'return'  => 'Returned',
        ];

        // Determine approval_status
        $stockCount->approval_status = $validated['action'] === 'approve'
            ? ($statusMap[$validated['request_type']] ?? 'Pending')
            : ($statusMap[$validated['action']] ?? 'Pending');

        // Update StockCount if successful
        if ($success) {
            $stockCount->save();
        }

        return response()->json([
            'message'      => $result['message'] ?? 'Action failed',
            'redirect_url' => route('approvals-stock-counts.show', $stockCount->id),
            'approval'     => $result['approval'] ?? null,
        ], $success ? 200 : 400);
    }

    public function reassignResponder(Request $request, StockCount $stockCount): JsonResponse
    {
        $this->authorize('reassign', $stockCount);

        $validated = $request->validate([
            'request_type'   => 'required|string|in:initial,approve',
            'new_user_id'    => 'required|exists:users,id',
            'new_position_id'=> 'nullable|exists:positions,id',
            'comment'        => 'nullable|string|max:1000',
        ]);

        $user = User::findOrFail($validated['new_user_id']);
        $positionId = $validated['new_position_id'] ?? $user->defaultPosition?->id;

        if (!$positionId) {
            return response()->json([
                'success' => false,
                'message' => 'The new user does not have a default position assigned.',
            ], 422);
        }

        if (!$user->hasPermissionTo("stockCount.{$validated['request_type']}")) {
            return response()->json([
                'success' => false,
                'message' => "User {$user->id} does not have permission for {$validated['request_type']}.",
            ], 403);
        }

        $approval = Approval::where([
            'approvable_type' => StockCount::class,
            'approvable_id'   => $stockCount->id,
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
                'is_seen'      => false,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Responder reassigned successfully.',
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to reassign responder', [
                'document_id'  => $stockCount->id,
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

    // public function update(Request $request, StockCount $stockCount): JsonResponse
    // {
    //     $this->authorize('update', $stockCount);
    //     $user = auth()->user();

    //     // 1️⃣ Validate request
    //     $validated = Validator::make(
    //         $request->all(),
    //         array_merge(
    //             $this->stockCountValidationRules(),
    //             $this->stockCountItemValidationRules(),
    //             [
    //                 'approvals' => 'required|array|min:1',
    //                 'approvals.*.user_id' => 'required|exists:users,id',
    //                 'approvals.*.request_type' => 'required|string|in:approve,initial',
    //             ]
    //         )
    //     )->validate();

    //     // 2️⃣ Validate approval permissions
    //     foreach ($validated['approvals'] as $approval) {
    //         $approvalUser = User::find($approval['user_id']);
    //         if (!$approvalUser) {
    //             return response()->json([
    //                 'message' => "User ID {$approval['user_id']} not found."
    //             ], 404);
    //         }

    //         $permission = "stockCount.{$approval['request_type']}";
    //         if (!$approvalUser->hasPermissionTo($permission)) {
    //             return response()->json([
    //                 'message' => "User ID {$approval['user_id']} does not have permission for {$approval['request_type']}.",
    //             ], 403);
    //         }
    //     }

    //     try {
    //         return DB::transaction(function () use ($validated, $stockCount, $user) {

    //             // 3️⃣ Update Stock Count header
    //             $stockCount->update([
    //                 'transaction_date' => $validated['transaction_date'],
    //                 'warehouse_id'     => $validated['warehouse_id'],
    //                 'remarks'          => $validated['remarks'] ?? null,
    //                 'updated_by'       => $user->id,
    //                 'position_id'      => $user->current_position_id,
    //                 'approval_status'  => 'Pending',
    //             ]);

    //             // 4️⃣ Sync Stock Count Items
    //             $existingItems = $stockCount->items()->get()->keyBy('id');
    //             $submittedItemIds = [];

    //             foreach ($validated['items'] as $item) {
    //                 if (!empty($item['id']) && $existingItems->has($item['id'])) {
    //                     // Update existing item
    //                     $existingItem = $existingItems[$item['id']];
    //                     $existingItem->update([
    //                         'product_id'       => $item['product_id'],
    //                         'ending_quantity'  => $item['ending_quantity'],
    //                         'counted_quantity' => $item['counted_quantity'],
    //                         'remarks'          => $item['remarks'] ?? null,
    //                         'updated_by'       => $user->id,
    //                     ]);
    //                     $submittedItemIds[] = $item['id'];
    //                 } else {
    //                     // Create new item
    //                     $newItem = $stockCount->items()->create([
    //                         'product_id'       => $item['product_id'],
    //                         'ending_quantity'  => $item['ending_quantity'],
    //                         'counted_quantity' => $item['counted_quantity'],
    //                         'remarks'          => $item['remarks'] ?? null,
    //                         'created_by'       => $user->id,
    //                     ]);
    //                     $submittedItemIds[] = $newItem->id;
    //                 }
    //             }

    //             // 5️⃣ Soft-delete removed items
    //             $stockCount->items()
    //                 ->whereNotIn('id', $submittedItemIds)
    //                 ->get()
    //                 ->each(function ($item) use ($user) {
    //                     $item->deleted_by = $user->id;
    //                     $item->save();
    //                     $item->delete();
    //                 });

    //             // 6️⃣ Sync approvals
    //             $stockCount->approvals()->delete(); // remove old approvals
    //             $this->storeApprovals($stockCount, $validated['approvals']);

    //             // 7️⃣ Return response with eager loaded relations
    //             return response()->json([
    //                 'message' => 'Stock count updated successfully.',
    //                 'data' => $stockCount->load([
    //                     'items.product', 
    //                     'approvals.responder', 
    //                     'approvals.requester.defaultPosition',
    //                     'approvals.requester.defaultDepartment',
    //                     'approvals.requester.defaultCampus'
    //                 ]),
    //             ], 200);

    //         });
    //     } catch (\Exception $e) {
    //         Log::error('Failed to update Stock Count', [
    //             'error' => $e->getMessage(),
    //             'stock_count_id' => $stockCount->id,
    //         ]);

    //         return response()->json([
    //             'message' => 'Failed to update Stock Count',
    //             'error' => $e->getMessage(),
    //         ], 500);
    //     }
    // }
    public function update(Request $request, StockCount $stockCount): JsonResponse
    {
        $this->authorize('update', $stockCount);
        $user = auth()->user();

        // 1️⃣ Validate request
        $validated = Validator::make(
            $request->all(),
            array_merge(
                $this->stockCountValidationRules(),
                $this->stockCountItemValidationRules(),
                [
                    'approvals' => 'required|array|min:1',
                    'approvals.*.user_id' => 'required|exists:users,id',
                    'approvals.*.request_type' => 'required|string|in:approve,initial',
                ]
            )
        )->validate();

        // 2️⃣ Validate approval permissions
        foreach ($validated['approvals'] as $approval) {
            $approvalUser = User::find($approval['user_id']);
            if (!$approvalUser) {
                return response()->json([
                    'message' => "User ID {$approval['user_id']} not found."
                ], 404);
            }

            $permission = "stockCount.{$approval['request_type']}";
            if (!$approvalUser->hasPermissionTo($permission)) {
                return response()->json([
                    'message' => "User ID {$approval['user_id']} does not have permission for {$approval['request_type']}.",
                ], 403);
            }
        }

        try {
            return DB::transaction(function () use ($validated, $stockCount, $user) {

                // 3️⃣ Update Stock Count header
                $stockCount->update([
                    'transaction_date' => $validated['transaction_date'],
                    'warehouse_id'     => $validated['warehouse_id'],
                    'remarks'          => $validated['remarks'] ?? null,
                    'updated_by'       => $user->id,
                    'position_id'      => $user->current_position_id,
                    'approval_status'  => 'Pending',
                ]);

                // 4️⃣ Delete all old items
                $stockCount->items()->delete();

                // 5️⃣ Create new items
                foreach ($validated['items'] as $item) {
                    $stockCount->items()->create([
                        'product_id'       => $item['product_id'],
                        'ending_quantity'  => $item['ending_quantity'],
                        'counted_quantity' => $item['counted_quantity'],
                        'remarks'          => $item['remarks'] ?? null,
                        'created_by'       => $user->id,
                    ]);
                }

                // 6️⃣ Sync approvals
                $stockCount->approvals()->delete(); // remove old approvals
                $this->storeApprovals($stockCount, $validated['approvals']);

                // 7️⃣ Return response with eager loaded relations
                return response()->json([
                    'message' => 'Stock count updated successfully.',
                    'data' => $stockCount->load([
                        'items.product', 
                        'approvals.responder', 
                        'approvals.requester.defaultPosition',
                        'approvals.requester.defaultDepartment',
                        'approvals.requester.defaultCampus'
                    ]),
                ], 200);

            });
        } catch (\Exception $e) {
            Log::error('Failed to update Stock Count', [
                'error' => $e->getMessage(),
                'stock_count_id' => $stockCount->id,
            ]);

            return response()->json([
                'message' => 'Failed to update Stock Count',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function destroy(StockCount $stockCount): JsonResponse
    {
        $this->authorize('delete', $stockCount);

        try {
            return DB::transaction(function () use ($stockCount) {
                $userId = auth()->id() ?? 1;

                // 1️⃣ Soft-delete related items (sets deleted_by and triggers deleted event in model boot)
                foreach ($stockCount->items as $item) {
                    $item->deleted_by = $userId;
                    $item->save();
                    $item->delete(); // triggers booted()->deleted()
                }

                // 2️⃣ Delete related approvals
                $stockCount->approvals()->delete();

                // 3️⃣ Soft-delete stock count itself (triggers deleted event)
                $stockCount->deleted_by = $userId;
                $stockCount->save();
                $stockCount->delete();

                return response()->json([
                    'message' => 'Stock count deleted successfully.'
                ], 200);
            });

        } catch (\Exception $e) {
            Log::error('Failed to delete stock count', [
                'error' => $e->getMessage(),
                'stock_count_id' => $stockCount->id
            ]);

            return response()->json([
                'message' => 'Failed to delete stock count.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }


    public function getProducts(Request $request): JsonResponse
    {
        $result = $this->productService->getStockProducts($request->all());
        return response()->json($result);
    }

    public function refreshStockData(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'warehouse_id' => 'required|exists:warehouses,id',
            'transaction_date' => 'required|date',
            'product_ids' => 'required|array|min:1',
            'product_ids.*' => 'required|exists:product_variants,id',
        ]);

        $warehouseId = $validated['warehouse_id'];
        $transactionDate = $validated['transaction_date'];
        $productIds = $validated['product_ids'];

        try {
            $items = DB::transaction(function () use ($warehouseId, $transactionDate, $productIds) {
                return collect($productIds)->map(function ($productId) use ($warehouseId, $transactionDate) {
                    $stockOnHand = $this->stockLedgerService->getStockOnHand(
                        $productId,
                        $warehouseId,
                        $transactionDate
                    );

                    $avgPrice = $this->stockLedgerService->getAvgPrice(
                        $productId,
                        $transactionDate
                    );

                    // Return updated stock data for frontend, no DB update needed here
                    return [
                        'product_id' => $productId,
                        'stock_on_hand' => $stockOnHand,
                        'average_price' => $avgPrice,
                    ];
                });
            });

            return response()->json([
                'message' => 'Stock data refreshed successfully.',
                'data' => $items,
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to refresh stock data', [
                'error' => $e->getMessage(),
                'warehouse_id' => $warehouseId,
                'transaction_date' => $transactionDate,
            ]);

            return response()->json([
                'message' => 'Failed to refresh stock data.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }


    public function getApprovalUsers(): JsonResponse
    {
        $users = [
            'initial'       => $this->usersWithPermission('stockCount.initial'),
            'approve'      => $this->usersWithPermission('stockCount.approve'),
        ];

        return response()->json($users);
    }

    private function usersWithPermission(string $permission)
    {
        return User::whereHas('permissions', fn($q) => $q->where('name', $permission))
            ->orWhereHas('roles.permissions', fn($q) => $q->where('name', $permission))
            // ->where('id', '!=', Auth::id())
            ->select('id', 'name', 'card_number')
            ->orderBy('name')
            ->get();
    }


    private function stockCountValidationRules()
    {
        return [
            'transaction_date' => 'required|date',
            'warehouse_id' => 'required|exists:warehouses,id',
            'remarks' => 'nullable|string|max:500',
        ];
    }

    private function stockCountItemValidationRules()
    {
        return [
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|exists:product_variants,id',
            'items.*.ending_quantity' => 'required|numeric|min:0',
            'items.*.counted_quantity' => 'required|numeric|min:0',
            'items.*.remarks' => 'nullable|string|max:255',
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
        return "STC-{$shortName}-{$monthYear}-{$sequence}";
    }

    private function getSequenceNumber(string $shortName, string $monthYear): string
    {
        $prefix = "STC-{$shortName}-{$monthYear}-";

        $count = StockCount::withTrashed()
            ->where('reference_no', 'like', "{$prefix}%")
            ->count();

        return str_pad($count + 1, 2, '0', STR_PAD_LEFT);
    }

    protected function storeApprovals(StockCount $stockCount, array $approvals)
    {
        foreach ($approvals as $approval) {
            $approvalData = [
                'approvable_type' => StockCount::class,
                'approvable_id' => $stockCount->id,
                'document_name' => 'Stock Count',
                'document_reference' => $stockCount->reference_no,
                'request_type' => $approval['request_type'],
                'approval_status' => 'Pending',
                'ordinal' => $this->getOrdinalForRequestType($approval['request_type']),
                'requester_id' => $stockCount->created_by,
                'responder_id' => $approval['user_id'],
                'position_id' => User::find($approval['user_id'])?->current_position_id,
            ];
            $this->approvalService->storeApproval($approvalData);
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

    private function canShowApprovalButton(int $documentId): array
    {
        try {
            $userId = auth()->id();
            if (!$userId) {
                return $this->approvalButtonResponse('User not authenticated.');
            }

            $approvals = Approval::where([
                'approvable_type' => StockCount::class,
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
