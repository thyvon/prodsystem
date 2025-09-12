<?php

namespace App\Http\Controllers;

use App\Models\StockIssue;
use App\Models\StockRequest;
use App\Models\StockIssueItem;
use App\Models\Warehouse;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;
use App\Services\StockRequestService;
use App\Services\StockLedgerService;

class StockIssueController extends Controller
{
    private const ALLOWED_SORT_COLUMNS = [
        'transaction_date', 
        'reference_no', 
        'created_at', 
        'updated_at', 
        'created_by', 
        'updated_by',
        'warehouse_name',     // added
        'request_number',     // added
        'requester_name'      // added (createdBy)
    ];
    private const DEFAULT_SORT_DIRECTION = 'desc';
    private const DEFAULT_LIMIT = 10;
    private const MAX_LIMIT = 1000;
    private const DATE_FORMAT = 'Y-m-d';

    protected StockRequestService $stockRequestService;
    protected StockLedgerService $stockLedgerService;

    public function __construct(
        StockRequestService $stockRequestService,
        StockLedgerService $stockLedgerService
    )
    {
        $this->stockRequestService = $stockRequestService;
        $this->stockLedgerService = $stockLedgerService;
    }

    public function index()
    {
        $this->authorize('viewAny', StockIssue::class);
        return view('Inventory.stockIssue.index');
    }

    public function getStockIssues(Request $request): JsonResponse
    {
        $this->authorize('viewAny', StockIssue::class);

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

        $sortColumn = $validated['sortColumn'] ?? 'stock_issues.id';
        $sortDirection = $validated['sortDirection'] ?? 'desc';

        $query = StockIssue::with([
            'stockRequest.warehouse.building.campus',
            'createdBy.campus',
            'updatedBy',
        ])
        ->when(!$isAdmin, fn($q) => $q->whereHas('stockRequest.warehouse.building.campus', fn($q2) => $q2->whereIn('id', $campusIds)))
        ->when($validated['search'] ?? null, fn($q, $search) => $q->where(fn($subQ) =>
            $subQ->where('reference_no', 'like', "%{$search}%")
                ->orWhereHas('stockRequest', fn($srQ) => $srQ->where('request_number', 'like', "%{$search}%"))
                ->orWhereHas('stockRequest.warehouse', fn($wQ) => $wQ->where('name', 'like', "%{$search}%"))
                ->orWhereHas('createdBy', fn($cQ) => $cQ->where('name', 'like', "%{$search}%"))
        ));

        // Sorting via join for relational columns
        if ($sortColumn === 'warehouse_name') {
            $query->join('stock_requests', 'stock_issues.stock_request_id', '=', 'stock_requests.id')
                ->join('warehouses', 'stock_requests.warehouse_id', '=', 'warehouses.id')
                ->orderBy('warehouses.name', $sortDirection)
                ->select('stock_issues.*');
        } elseif ($sortColumn === 'request_number') {
            $query->join('stock_requests', 'stock_issues.stock_request_id', '=', 'stock_requests.id')
                ->orderBy('stock_requests.request_number', $sortDirection)
                ->select('stock_issues.*');
        } elseif ($sortColumn === 'created_by') {
            $query->join('users', 'stock_issues.created_by', '=', 'users.id')
                ->orderBy('users.name', $sortDirection)
                ->select('stock_issues.*');
        } else {
            $query->orderBy($sortColumn, $sortDirection);
        }

        $stockIssues = $query->paginate(
            $validated['limit'] ?? self::DEFAULT_LIMIT,
            ['*'],
            'page',
            $validated['page'] ?? 1
        );

        $stockIssuesMapped = $stockIssues->map(fn($issue) => [
            'id' => $issue->id,
            'reference_no' => $issue->reference_no,
            'request_number' => $issue->stockRequest->request_number ?? null,
            'transaction_date' => $issue->transaction_date,
            'requester_name' => $issue->stockRequest->createdBy->name ?? null,
            'requester_campus_name' => $issue->stockRequest->campus->short_name ?? null,
            'warehouse_name' => $issue->stockRequest->warehouse->name ?? null,
            'warehouse_campus_name' => $issue->stockRequest->warehouse->building->campus->short_name ?? null,
            'quantity' => $issue->stockIssueItems->sum('quantity'),
            'total_price' => $issue->stockIssueItems->sum('total_price'),
            'created_by' => $issue->createdBy->name ?? null,
            'created_at' => $issue->created_at,
            'updated_at' => $issue->updated_at,
        ]);

        return response()->json([
            'data' => $stockIssuesMapped,
            'recordsTotal' => $stockIssues->total(),
            'recordsFiltered' => $stockIssues->total(),
            'draw' => (int) ($validated['draw'] ?? 1),
        ]);
    }

    public function create()
    {
        $this->authorize('create', StockIssue::class);

        // show only approved stock requests
        $stockRequests = StockRequest::with(['stockRequestItems.product', 'warehouse'])
            ->where('approval_status', 'Approved')
            ->get();
        return view('Inventory.stockIssue.form', compact('stockRequests'));
    }

    public function store(Request $request): JsonResponse
    {
        $this->authorize('create', StockIssue::class);

        // Validate the request (approvals removed)
        $validated = Validator::make($request->all(), array_merge(
            $this->stockIssueValidationRule(),
            $this->stockIssueItemValidationRule()
        ))->validate();

        // Fetch the StockRequest and verify it exists
        $stockRequest = StockRequest::with('stockRequestItems')->findOrFail($validated['stock_request_id']);

        try {
            return DB::transaction(function () use ($validated, $stockRequest) {
                $user = auth()->user();
                $userPosition = $user?->defaultPosition();
                if (!$userPosition) {
                    return response()->json([
                        'message' => 'No default position assigned to this user.',
                    ], 404);
                }

                // Generate issue number using StockRequest (uses warehouse relation)
                $issueNumber = $this->generateReferenceNo($stockRequest, $validated['transaction_date']);

                // Create StockIssue using warehouse_id from StockRequest
                $stockIssue = StockIssue::create([
                    'transaction_date' => $validated['transaction_date'],
                    'reference_no'     => $issueNumber,
                    'stock_request_id' => $validated['stock_request_id'],
                    'warehouse_id'     => $stockRequest->warehouse_id,
                    'remarks'          => $validated['remarks'] ?? null,
                    'created_by'       => $user?->id ?? 1,
                    'position_id'      => $userPosition->id,
                ]);

                // Prepare items (using unit_price + total_price)
                $items = array_map(function ($item) use ($stockIssue) {
                    $avg = $item['unit_price'];
                    $qty = $item['quantity'];
                    return [
                        'stock_issue_id'        => $stockIssue->id,
                        'stock_request_item_id' => $item['stock_request_item_id'],
                        'product_id'            => $item['product_id'],
                        'quantity'              => $qty,
                        'unit_price'         => $avg,
                        'total_price'           => $qty * $avg,
                        'remarks'               => $item['remarks'] ?? null,
                        'created_by'            => auth()->id() ?? 1,
                        'updated_by'            => auth()->id() ?? 1,
                        'created_at'            => now(),
                        'updated_at'            => now(),
                    ];
                }, $validated['items']);

                if (!empty($items)) {
                    StockIssueItem::insert($items);
                }

                return response()->json([
                    'message' => 'Stock Issue created successfully.',
                    'data' => $stockIssue->load('stockIssueItems', 'stockRequest'),
                ], 201);
            });
        } catch (\Exception $e) {
            Log::error('Failed to create stock issue.', ['error' => $e->getMessage()]);
            return response()->json([
                'message' => 'Failed to create stock issue',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function edit(StockIssue $stockIssue)
    {
        $this->authorize('update', $stockIssue);

        try {
            $stockIssue->load([
                'stockIssueItems.productVariant.product.unit',
                'warehouse',
                'stockRequest',
            ]);

            $stockIssueData = [
                'id' => $stockIssue->id,
                'stock_request_id' => $stockIssue->stock_request_id,
                'transaction_date' => $stockIssue->transaction_date,
                'reference_no' => $stockIssue->reference_no,
                'warehouse_id' => $stockIssue->warehouse_id,
                'remarks' => $stockIssue->remarks,
                'created_by' => $stockIssue->created_by,
                'position_id' => $stockIssue->position_id,
                'items' => $stockIssue->stockIssueItems->map(function ($item) use ($stockIssue) {
                        $productName = $item->productVariant?->product?->name ?? $item->product?->name ?? null;
                        $unitName = $item->productVariant?->product?->unit?->name ?? $item->product?->unit?->name ?? null;

                        $warehouseId = $stockIssue->warehouse_id;
                        $cutoffDate = $stockIssue->transaction_date;
                        $stockMovements = $this->stockLedgerService->recalcProduct($item->product_id, $warehouseId, $cutoffDate);
                        $stockOnHand = $stockMovements->last()->running_qty ?? 0;
                        $averagePrice = $this->stockLedgerService->getGlobalAvgPrice($item->product_id, $cutoffDate);

                        return [
                            'id' => $item->id,
                            'stock_request_item_id' => $item->stock_request_item_id,
                            'product_id' => $item->product_id,
                            'product_code' => $item->productVariant?->item_code ?? '',
                            'product_name' => trim(
                                ($item->productVariant?->product?->name ?? '') . ' ' . 
                                ($item->productVariant?->description ?? '')
                            ),
                            'variant' => $item->productVariant?->variant_name ?? null,
                            'unit_name' => $unitName,
                            'quantity' => $item->quantity,
                            'unit_price' => $averagePrice,
                            'total_price' => round($item->quantity * $averagePrice, 4),
                            'remarks' => $item->remarks,
                            'stock_on_hand' => $stockOnHand,
                        ];
                })->toArray(),
            ];

            return view('Inventory.stockIssue.form', compact('stockIssue', 'stockIssueData'));
        } catch (\Exception $e) {
            Log::error('Error fetching stock issue for editing', [
                'error_message' => $e->getMessage(),
                'stock_issue_id' => $stockIssue->id,
            ]);

            return response()->view('errors.500', [
                'message' => 'Failed to fetch stock issue',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function update(Request $request, StockIssue $stockIssue): JsonResponse
    {
        $this->authorize('update', $stockIssue);

        // Validate the request
        $validated = Validator::make($request->all(), array_merge(
            $this->stockIssueValidationRule(),
            $this->stockIssueItemValidationRule()
        ))->validate();

        // Fetch the StockRequest and ensure it exists
        $stockRequest = StockRequest::with('stockRequestItems')->findOrFail($validated['stock_request_id']);

        try {
            return DB::transaction(function () use ($validated, $stockIssue, $stockRequest) {
                $user = auth()->user();
                $userPosition = $user?->defaultPosition();

                if (!$userPosition) {
                    return response()->json([
                        'message' => 'No default position assigned to this user.',
                    ], 404);
                }

                // Update StockIssue main details
                $stockIssue->update([
                    'stock_request_id' => $stockRequest->id,
                    'warehouse_id' => $stockRequest->warehouse_id,
                    'transaction_date' => $validated['transaction_date'],
                    'position_id' => $userPosition->id,
                    'remarks' => $validated['remarks'] ?? null,
                    'updated_by' => $user?->id ?? 1,
                ]);

                // Handle StockIssueItems
                $existingItems = $stockIssue->stockIssueItems->keyBy('id');
                $submittedItemIds = [];

                foreach ($validated['items'] as $item) {
                    $qty = $item['quantity'];
                    $avg = $item['unit_price'];

                    if (!empty($item['id']) && $existingItems->has($item['id'])) {
                        // Update existing item
                        $existingItems[$item['id']]->update([
                            'stock_request_item_id' => $item['stock_request_item_id'],
                            'product_id' => $item['product_id'],
                            'quantity' => $qty,
                            'unit_price' => $avg,
                            'total_price' => $qty * $avg,
                            'remarks' => $item['remarks'] ?? null,
                            'updated_by' => auth()->id() ?? 1,
                        ]);
                        $submittedItemIds[] = $item['id'];
                    } else {
                        // Insert new item
                        $newItem = StockIssueItem::create([
                            'stock_issue_id' => $stockIssue->id,
                            'stock_request_item_id' => $item['stock_request_item_id'],
                            'product_id' => $item['product_id'],
                            'quantity' => $qty,
                            'unit_price' => $avg,
                            'total_price' => $qty * $avg,
                            'remarks' => $item['remarks'] ?? null,
                            'created_by' => auth()->id() ?? 1,
                            'updated_by' => auth()->id() ?? 1,
                        ]);
                        $submittedItemIds[] = $newItem->id; // Track new item
                    }
                }

                // Soft-delete items not submitted
                $stockIssue->stockIssueItems()
                    ->whereNotIn('id', $submittedItemIds)
                    ->each(function ($item) {
                        $item->deleted_by = auth()->id() ?? 1;
                        $item->save();
                        $item->delete();
                    });

                return response()->json([
                    'message' => 'Stock Issue updated successfully.',
                    'data' => $stockIssue->load('stockIssueItems', 'stockRequest'),
                ], 200);
            });
        } catch (\Exception $e) {
            Log::error('Error updating stock issue', [
                'error_message' => $e->getMessage(),
                'stock_issue_id' => $stockIssue->id,
            ]);

            return response()->json([
                'message' => 'Failed to update stock issue',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Validation rule methods (note: singular names to match calls in store/update)
     */
    private function stockIssueValidationRule(?int $stockRequestId = null): array
    {
        return [
            'transaction_date' => ['required', 'date', 'date_format:' . self::DATE_FORMAT],
            'stock_request_id' => ['required', 'integer', 'exists:stock_requests,id'],
            'remarks' => ['nullable', 'string', 'max:1000'],
        ];
    }

    private function stockIssueItemValidationRule(): array
    {
        return [
            'items' => ['required', 'array', 'min:1'],
            'items.*.id' => ['nullable', 'integer', 'exists:stock_issue_items,id'],
            'items.*.stock_request_item_id' => ['required', 'integer', 'exists:stock_request_items,id'],
            'items.*.product_id' => ['required', 'integer', 'exists:product_variants,id'],
            'items.*.quantity' => ['required', 'numeric', 'min:0.01'],
            'items.*.unit_price' => ['required', 'numeric', 'min:0'],
            'items.*.remarks' => ['nullable', 'string', 'max:1000'],
        ];
    }

    /**
     * Generate issue reference number using StockRequest -> Warehouse -> Building -> Campus short_name
     */
    private function generateReferenceNo(StockRequest $stockRequest, string $requestDate): string
    {
        // Load warehouse by id with building.campus
        $warehouse = Warehouse::with('building.campus')->findOrFail($stockRequest->warehouse_id);

        try {
            $date = \Carbon\Carbon::createFromFormat(self::DATE_FORMAT, $requestDate);
            if (!$date || $date->format(self::DATE_FORMAT) !== $requestDate) {
                throw new \InvalidArgumentException('Invalid date format. Expected ' . self::DATE_FORMAT . '.');
            }
        } catch (\Exception $e) {
            throw new \InvalidArgumentException('Invalid date format. Expected ' . self::DATE_FORMAT . '.', 0, $e);
        }

        $shortName = $warehouse->building?->campus?->short_name ?? 'WH';
        $monthYear = $date->format('my'); // e.g. 0925

        // Sequence number for this shortName + month
        $sequence = $this->getSequenceNumber($shortName, $monthYear);

        return "STI-{$shortName}-{$monthYear}-{$sequence}";
    }

    private function getSequenceNumber(string $shortName, string $monthYear): string
    {
        $prefix = "STI-{$shortName}-{$monthYear}-";

        $count = StockIssue::withTrashed()
            ->where('reference_no', 'like', "{$prefix}%")
            ->count();

        return str_pad($count + 1, 2, '0', STR_PAD_LEFT);
    }

    public function destroy(StockIssue $stockIssue): JsonResponse
    {
        $this->authorize('delete', $stockIssue);

        try {
            DB::transaction(function () use ($stockIssue) {
                $userId = auth()->id() ?? 1;

                // Soft delete related stock issue items
                foreach ($stockIssue->stockIssueItems as $stockIssueItem) {
                    $stockIssueItem->deleted_by = $userId;
                    $stockIssueItem->save();
                    $stockIssueItem->delete();
                }

                // Soft delete the main stock issue
                $stockIssue->deleted_by = $userId;
                $stockIssue->save();
                $stockIssue->delete();
            });

            return response()->json([
                'message' => 'Stock issue deleted successfully.',
            ], 200);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to delete stock issue', [
                'error' => $e->getMessage(),
                'id' => $stockIssue->id
            ]);

            return response()->json([
                'message' => 'Failed to delete stock issue',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function getStockRequests(Request $request): JsonResponse
    {
        $this->authorize('viewAny', StockIssue::class);
        $user = auth()->user();
        $stockRequests = $this->stockRequestService->getStockRequests($request);
        if (!$user->hasRole('admin')) {
            $defaultWarehouseId = $user->defaultWarehouse()?->id;
            if (!$defaultWarehouseId) {
                return response()->json(['message' => 'No default warehouse assigned for this user.'], 404);
            }
            $stockRequests['data'] = collect($stockRequests['data'])
                ->where('warehouse_id', $defaultWarehouseId)
                ->where('approval_status', 'Approved')
                ->values();
        }
        return response()->json($stockRequests);
    }


    public function getStockRequestItems(StockRequest $stockRequest, Request $request): JsonResponse
    {
        $this->authorize('viewAny', StockIssue::class);
        $stockRequestsData = $this->stockRequestService->getStockRequests(
            $request,
            $request->query('cutoff_date') ?? $stockRequest->transaction_date
        );
        $items = collect($stockRequestsData['data'])
            ->firstWhere('id', $stockRequest->id)['items'] ?? [];
        return response()->json(['items' => $items]);
    }

}
