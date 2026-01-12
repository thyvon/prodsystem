<?php

namespace App\Http\Controllers;

use App\Models\StockIssue;
use App\Models\StockRequest;
use App\Models\StockIssueItem;
use App\Models\Warehouse;
use App\Models\Campus;
use App\Models\Department;
use App\Models\User;
use App\Models\ProductVariant;
use App\Models\DebitNote;
use App\Models\DebitNoteEmail;
use App\Models\DebitNoteItem;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;
use App\Services\StockRequestService;
use App\Services\StockLedgerService;
use App\Services\ProductService;
use App\Imports\StockIssueImport;
use App\Exports\StockIssueItemsExport;
use Maatwebsite\Excel\Facades\Excel;

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
    protected ProductService $productService;

    public function __construct(
        StockRequestService $stockRequestService,
        StockLedgerService $stockLedgerService,
        ProductService $productService
    )
    {
        $this->stockRequestService = $stockRequestService;
        $this->stockLedgerService = $stockLedgerService;
        $this->productService = $productService;
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
            'requestedBy'
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
            'requester_name' => $issue->requestedBy->name ?? null,
            'requester_campus_name' => optional($issue->requestedBy->defaultCampus())->short_name,
            'warehouse_name' => $issue->warehouse->name ?? null,
            'warehouse_campus_name' => $issue->warehouse->building->campus->short_name ?? null,
            'quantity' => number_format($issue->stockIssueItems->sum('quantity'), 2, '.', ''),
            'total_price' => number_format($issue->stockIssueItems->sum('total_price'), 4, '.', ''),
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


    public function indexItem()
    {
        $this->authorize('viewAny', StockIssue::class);
        return view('Inventory.stockIssue.item-list');
    }

    public function getAllStockIssueItems(Request $request): JsonResponse
    {
        $this->authorize('viewAny', StockIssue::class);

        $user = auth()->user();
        $isAdmin = $user->hasRole('admin');
        $campusIds = $user->campus->pluck('id')->toArray();

        // Validate request
        $validated = $request->validate([
            'search' => 'nullable|string|max:255',
            'sortColumn' => 'nullable|string',
            'sortDirection' => 'nullable|string|in:asc,desc',
            'limit' => 'nullable|integer|min:1|max:' . self::MAX_LIMIT,
            'page' => 'nullable|integer|min:1',
            'draw' => 'nullable|integer',
            'warehouse_ids' => 'nullable|array',
            'warehouse_ids.*' => 'integer|exists:warehouses,id',
            'campus_ids' => 'nullable|array',
            'campus_ids.*' => 'integer|exists:campus,id',
            'department_ids' => 'nullable|array',
            'department_ids.*' => 'integer|exists:departments,id',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date',
            'transaction_type' => 'nullable',
        ]);

        $sortColumn = $validated['sortColumn'] ?? 'stock_issue_items.id';
        $sortDirection = $validated['sortDirection'] ?? 'desc';

        $query = StockIssueItem::with([
            'productVariant.product.unit',
            'stockIssue.warehouse.building.campus',
            'campus',
            'department',
            'department.division',
            'stockIssue.requestedBy'
        ])
            // Restrict by campus if not admin
            ->when(!$isAdmin, fn($q) =>
                $q->whereHas('stockIssue.warehouse.building.campus', fn($q2) =>
                    $q2->whereIn('id', $campusIds)
                )
            )
            // Search filter
            ->when($validated['search'] ?? null, fn($q, $search) =>
                $q->where(fn($subQ) =>
                    $subQ->where('remarks', 'like', "%{$search}%")
                        ->orWhereHas('productVariant', fn($pvQ) =>
                            $pvQ->where('description', 'like', "%{$search}%")
                                ->orWhere('item_code', 'like', "%{$search}%")
                                ->orWhereHas('product', fn($pQ) =>
                                    $pQ->where('name', 'like', "%{$search}%")
                                )
                        )
                        ->orWhereHas('stockIssue', fn($siQ) =>
                            $siQ->where('reference_no', 'like', "%{$search}%")
                        )
                )
            )
            // Multi-warehouse filter
            ->when($validated['warehouse_ids'] ?? null, function ($q, $warehouseIds) {
                $q->whereHas('stockIssue', function ($siQ) use ($warehouseIds) {
                    $siQ->whereIn('warehouse_id', $warehouseIds);
                });
            })
            // Trasaction Type Multi filter
            ->when(!empty($validated['transaction_type'] ?? []), function ($q) use ($validated) {
                $q->whereHas('stockIssue', function ($transQ) use ($validated) {
                    $transQ->whereIn('transaction_type', $validated['transaction_type']);
                });
            })

            // Multi-campus filter
            ->when($validated['campus_ids'] ?? null, function ($q, $campusIds) {
                $q->whereHas('campus', function ($campusQ) use ($campusIds) {
                    $campusQ->whereIn('id', $campusIds);
                });
            })
            // Multi-department filter
            ->when($validated['department_ids'] ?? null, function ($q, $departmentIds) {
                $q->whereHas('department', function ($depQ) use ($departmentIds) {
                    $depQ->whereIn('id', $departmentIds);
                });
            })
            // Date range filter
            ->when($validated['start_date'] ?? null, fn($q, $start) =>
                $q->whereHas('stockIssue', fn($siQ) => $siQ->whereDate('transaction_date', '>=', $start))
            )
            ->when($validated['end_date'] ?? null, fn($q, $end) =>
                $q->whereHas('stockIssue', fn($siQ) => $siQ->whereDate('transaction_date', '<=', $end))
            );

        // Sorting relational columns
        if ($sortColumn === 'product_name') {
            $query->join('product_variants', 'stock_issue_items.product_id', '=', 'product_variants.id')
                ->orderBy('product_variants.name', $sortDirection)
                ->select('stock_issue_items.*');
        } elseif ($sortColumn === 'product_code') {
            $query->join('product_variants', 'stock_issue_items.product_id', '=', 'product_variants.id')
                ->orderBy('product_variants.item_code', $sortDirection)
                ->select('stock_issue_items.*');
        } else {
            $query->orderBy($sortColumn, $sortDirection);
        }

        // Pagination
        $limit = $validated['limit'] ?? self::DEFAULT_LIMIT;
        $page = $validated['page'] ?? 1;
        $items = $query->paginate($limit, ['*'], 'page', $page);

        // Map data for frontend
        $itemsMapped = $items->map(function ($item) {
            $productName = $item->productVariant->product->name ?? '';
            $variantDescription = $item->productVariant->description ?? '';

            return [
                'id' => $item->id,
                'stock_issue_reference' => $item->stockIssue->reference_no ?? null,
                'product_code' => $item->productVariant->item_code ?? null,
                'description' => trim($productName . ' ' . $variantDescription),
                'quantity' => number_format($item->quantity, 2, '.', ''),
                'unit_name' => $item->productVariant->product->unit->name ?? null,
                'unit_price' => number_format($item->unit_price, 4, '.', ''),
                'total_price' => number_format($item->total_price, 4, '.', ''),
                'requester_name' => $item->stockIssue->requestedBy->name ?? null,
                'campus_name' => $item->campus->short_name ?? null,
                'department_name' => $item->department->short_name ?? null,
                'division_name' => $item->department->division->short_name ?? null,
                'purpose' => $item->stockIssue->remarks ?? null,
                'remarks' => $item->remarks,
                'warehouse_name' => $item->stockIssue->warehouse->name ?? null,
                'transaction_type' => $item->stockIssue->transaction_type ?? null,
                'transaction_date' => $item->stockIssue->transaction_date ?? null,
            ];
        });

        return response()->json([
            'data' => $itemsMapped,
            'recordsTotal' => $items->total(),
            'recordsFiltered' => $items->total(),
            'draw' => (int) ($validated['draw'] ?? 1),
        ]);
    }


    public function upsertDebitNote(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'warehouse_id'        => 'required|exists:warehouses,id',
            'department_id'       => 'nullable|exists:departments,id',
            'campus_id'           => 'nullable|exists:campus,id',
            'start_date'          => 'required|date',
            'end_date'            => 'required|date|after_or_equal:start_date',
            'transaction_type'    => 'required|array|min:1',
            'transaction_type.*'  => 'in:Issue,Transfer',
        ]);

        try {
            DB::transaction(function () use ($validated) {

                $startDate = Carbon::parse($validated['start_date']);
                $endDate   = Carbon::parse($validated['end_date']);
                $year      = $startDate->year;
                $month     = $startDate->month;

                // 1. Fetch stock items grouped by department + campus
                $stockItemsGrouped = StockIssueItem::whereHas('stockIssue', function ($q) use ($validated) {
                        $q->where('warehouse_id', $validated['warehouse_id'])
                        ->whereIn('transaction_type', $validated['transaction_type'])
                        ->whereBetween('transaction_date', [
                            $validated['start_date'],
                            $validated['end_date']
                        ]);
                    })
                    ->with(['stockIssue', 'productVariant.product.unit', 'campus', 'department.division', 'stockIssue.requestedBy'])
                    ->get()
                    ->groupBy(fn ($item) => $item->department_id.'-'.$item->campus_id);

                if ($stockItemsGrouped->isEmpty()) {
                    abort(422, 'No stock issue items found for the selected period.');
                }

                // 2. Loop each group (department + campus)
                foreach ($stockItemsGrouped as $groupKey => $items) {

                    [$deptId, $campusId] = explode('-', $groupKey);

                    // 3. Find email config for this department + campus
                    $email = DebitNoteEmail::where('warehouse_id', $validated['warehouse_id'])
                        ->where('department_id', $deptId)
                        ->where('campus_id', $campusId)
                        ->first();

                    // ❌ If email not found, throw error immediately
                    if (!$email) {
                        $warehouseName  = Warehouse::find($validated['warehouse_id'])?->name ?? $validated['warehouse_id'];
                        $departmentName = Department::find($deptId)?->short_name ?? $deptId;
                        $campusName     = Campus::find($campusId)?->short_name ?? $campusId;

                        abort(422, "No Debit Note Email configuration found for Warehouse '{$warehouseName}', Department '{$departmentName}', Campus '{$campusName}'.");
                    }
                    // 4. Create or update Debit Note
                    $debitNote = DebitNote::updateOrCreate(
                        [
                            'warehouse_id'  => $validated['warehouse_id'],
                            'department_id' => $deptId,
                            'campus_id'     => $campusId,
                            'start_date'    => $startDate,
                            'end_date'      => $endDate,
                        ],
                        [
                            'reference_number' => $this->generateDebitNoteNo(
                                $validated['warehouse_id'],
                                $deptId,
                                $year,
                                $month,
                                $campusId
                            ),
                            'debit_note_email_id' => $email->id,
                            'status'     => 'pending',
                            'created_by' => auth()->id(),
                        ]
                    );

                    // 5. Refresh items
                    $debitNote->items()->delete();

                    $itemsData = $items->map(fn ($item) => [
                        'debit_note_id'       => $debitNote->id,
                        'stock_issue_id'      => $item->stock_issue_id,
                        'stock_issue_item_id' => $item->id,
                        'transaction_date'    => $item->stockIssue->transaction_date,
                        'item_code'           => $item->productVariant->item_code,
                        'description'         => trim(
                            ($item->productVariant->product->name ?? '') . ' ' .
                            ($item->productVariant->description ?? '')
                        ),
                        'quantity'        => $item->quantity ?? 0,
                        'uom'             => $item->productVariant->product->unit->name ?? '',
                        'unit_price'      => $item->unit_price ?? 0,
                        'total_price'     => $item->total_price ?? 0,
                        'requester_name'  => $item->stockIssue->requestedBy->name ?? '',
                        'campus_name'     => $item->campus->short_name ?? '',
                        'division_name'   => $item->department->division->short_name ?? '',
                        'department_name' => $item->department->short_name ?? '',
                        'reference_no'    => $item->stockIssue->reference_no ?? '',
                        'remarks'         => $item->remarks,
                        'created_at'      => now(),
                        'updated_at'      => now(),
                    ])->toArray();

                    $debitNote->items()->insert($itemsData);
                }
            });

            return response()->json([
                'message' => 'Debit Notes created or updated successfully.'
            ]);

        } catch (\Throwable $e) {
            return response()->json([
                'message' => 'Failed to create or update Debit Notes.',
                'error'   => $e->getMessage()
            ], 500);
        }
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

    public function import(Request $request)
    {
        $request->validate([
            'file' => ['required', 'file', 'mimes:xlsx,csv'],
        ]);

        try {
            Excel::import(new StockIssueImport, $request->file('file'));

            return response()->json([
                'message' => 'Stock Issues imported successfully.',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function exportItems(Request $request)
    {
        $validated = $request->validate([
            'start_date'      => 'nullable|date',
            'end_date'        => 'nullable|date',
            'warehouse_ids'   => 'nullable|array',
            'warehouse_ids.*' => 'integer|exists:warehouses,id',
            'department_ids'  => 'nullable|array',
            'department_ids.*'=> 'integer|exists:departments,id',
            'campus_ids'      => 'nullable|array',
            'campus_ids.*'    => 'integer|exists:campus,id',
            'transaction_type' => 'nullable',
        ]);

        $filters = [
            'start_date'      => $validated['start_date'] ?? null,
            'end_date'        => $validated['end_date'] ?? null,
            'warehouse_ids'   => $validated['warehouse_ids'] ?? [],
            'department_ids'  => $validated['department_ids'] ?? [],
            'campus_ids'      => $validated['campus_ids'] ?? [],
            'transaction_type' => $validated['transaction_type'] ?? [],
        ];

        $query = StockIssueItem::query();

        return Excel::download(new StockIssueItemsExport($query, $filters), 'stock_issue_items.xlsx');
    }

    public function store(Request $request): JsonResponse
    {
        $this->authorize('create', StockIssue::class);

        // Validate the request
        $validated = Validator::make($request->all(), array_merge(
            $this->stockIssueValidationRule(),
            $this->stockIssueItemValidationRule()
        ))->validate();

        // Fetch the StockRequest and its items (if provided)
        $stockRequest = isset($validated['stock_request_id'])
            ? StockRequest::with('stockRequestItems')->findOrFail($validated['stock_request_id'])
            : null;

        try {
            return DB::transaction(function () use ($validated, $stockRequest) {
                $user = auth()->user();

                // Generate reference number if not provided
                $referenceNo = $validated['reference_no'] ?? $this->generateReferenceNo($stockRequest, $validated['transaction_date']);

                // Create StockIssue
                $stockIssue = StockIssue::create([
                    'transaction_date' => $validated['transaction_date'],
                    'transaction_type' => $validated['transaction_type'],
                    'account_code'     => $validated['account_code'],
                    'reference_no'     => $referenceNo,
                    'remarks'          => $validated['remarks'] ?? null,
                    'warehouse_id'     => $validated['warehouse_id'] ?? $stockRequest?->warehouse_id,
                    'stock_request_id' => $validated['stock_request_id'] ?? null,
                    'requested_by'     => $validated['requested_by'] ?? $user?->id ?? 1,
                    'created_by'       => $user?->id ?? 1,
                    'position_id'      => $user?->current_position_id ?? null,
                    'updated_by'       => $user?->id ?? 1,
                ]);

                $createdItems = [];

                // Create each StockIssueItem via Eloquent to trigger events
                foreach ($validated['items'] as $item) {
                    $qty = $item['quantity'];
                    $unitPrice = $item['unit_price'];

                    // Resolve stock_request_item_id
                    $stockRequestItemId = $item['stock_request_item_id'] ?? null;

                    if ($stockRequestItemId) {
                        $exists = DB::table('stock_request_items')->where('id', $stockRequestItemId)->exists();
                        if (!$exists) $stockRequestItemId = null;
                    }

                    if (!$stockRequestItemId && $stockRequest) {
                        $matched = collect($stockRequest->stockRequestItems)->firstWhere('product_id', $item['product_id']);
                        $stockRequestItemId = $matched->id ?? null;
                    }

                    // Create StockIssueItem (triggers booted() -> stock ledger)
                    $createdItems[] = StockIssueItem::create([
                        'stock_issue_id'       => $stockIssue->id,
                        'stock_request_item_id'=> $stockRequestItemId,
                        'product_id'           => $item['product_id'],
                        'quantity'             => $qty,
                        'unit_price'           => $unitPrice,
                        'total_price'          => bcmul($qty, $unitPrice, 10),
                        'remarks'              => $item['remarks'] ?? null,
                        'campus_id'            => $item['campus_id'],
                        'department_id'        => $item['department_id'],
                        'updated_by'           => $user?->id ?? 1,
                        'deleted_by'           => null,
                    ]);
                }

                return response()->json([
                    'message' => 'Stock Issue created successfully.',
                    'data'    => $stockIssue->load('stockIssueItems', 'stockRequest'),
                ], 201);
            });
        } catch (\Exception $e) {
            Log::error('Failed to create stock issue.', ['error' => $e->getMessage()]);
            return response()->json([
                'message' => 'Failed to create stock issue',
                'error'   => $e->getMessage(),
            ], 500);
        }
    }


    public function edit(StockIssue $stockIssue)
    {
        $this->authorize('update', $stockIssue);

        try {
            $stockIssue->load([
                'stockIssueItems',
                'warehouse',
                'stockRequest',
                'requestedBy'
            ]);

            $warehouseId = $stockIssue->warehouse_id;
            $cutoffDate = $stockIssue->transaction_date;

            $stockIssueData = [
                'id' => $stockIssue->id,
                'stock_request_id' => $stockIssue->stock_request_id,
                'transaction_date' => $stockIssue->transaction_date,
                'transaction_type' => $stockIssue->transaction_type,
                'account_code' => $stockIssue->account_code,
                'reference_no' => $stockIssue->reference_no,
                'warehouse_id' => $stockIssue->warehouse_id,
                'remarks' => $stockIssue->remarks,
                'requested_by' => $stockIssue->requested_by,
                'created_by' => $stockIssue->created_by,
                'position_id' => $stockIssue->position_id,
                'items' => $stockIssue->stockIssueItems->map(function ($item) use ($warehouseId, $cutoffDate) {

                    $productVariant = $item->productVariant;
                    $productName = $productVariant?->product?->name ?? $item->product?->name ?? null;
                    $unitName = $productVariant?->product?->unit?->name ?? $item->product?->unit?->name ?? null;

                    // Get stock on hand using StockLedgerService
                    $stockOnHand = $this->stockLedgerService->getStockOnHand(
                        $item->product_id,
                        $warehouseId,
                        $cutoffDate
                    );

                    // Get average price using StockLedgerService
                    $averagePrice = $this->stockLedgerService->getAvgPrice(
                        $item->product_id,
                        $warehouseId,
                        $cutoffDate
                    );

                    return [
                        'id' => $item->id,
                        'stock_request_item_id' => $item->stock_request_item_id,
                        'product_id' => $item->product_id,
                        'product_code' => $productVariant?->item_code ?? '',
                        'description' => trim(($productVariant?->product?->name ?? '') . ' ' . ($productVariant?->description ?? '')),
                        'variant' => $productVariant?->variant_name ?? null,
                        'unit_name' => $unitName,
                        'quantity' => $item->quantity,
                        'unit_price' => $item->unit_price,
                        'total_price' => round($item->quantity * $averagePrice, 4),
                        'remarks' => $item->remarks,
                        'average_price' => $averagePrice,
                        'stock_on_hand' => $stockOnHand,
                        'campus_id' => $item->campus_id,
                        'department_id' => $item->department_id
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
        $validated = Validator::make(
            $request->all(),
            array_merge(
                $this->stockIssueValidationRule(null, $stockIssue->id),
                $this->stockIssueItemValidationRule()
            )
        )->validate();

        $stockRequest = $validated['stock_request_id'] ?? null
            ? StockRequest::with('stockRequestItems')->findOrFail($validated['stock_request_id'])
            : null;

        try {
            return DB::transaction(function () use ($validated, $stockIssue, $stockRequest) {
                $user = auth()->user();

                // Update StockIssue
                $stockIssue->update([
                    'transaction_date' => $validated['transaction_date'],
                    'transaction_type' => $validated['transaction_type'],
                    'account_code'     => $validated['account_code'],
                    'reference_no'     => $validated['reference_no'] ?? $stockIssue->reference_no,
                    'stock_request_id' => $stockRequest?->id ?? $validated['stock_request_id'] ?? null,
                    'warehouse_id'     => $validated['warehouse_id'] ?? $stockRequest?->warehouse_id,
                    'requested_by'     => $validated['requested_by'] ?? $stockIssue->requested_by,
                    'position_id'      => $user?->current_position_id ?? null,
                    'remarks'          => $validated['remarks'] ?? null,
                    'updated_by'       => $user?->id ?? 1,
                ]);

                // Handle StockIssueItems
                $existingItems = $stockIssue->stockIssueItems->keyBy('id');
                $submittedItemIds = [];

                foreach ($validated['items'] as $item) {
                    $qty = $item['quantity'];
                    $unitPrice = $item['unit_price'];

                    // Resolve stock_request_item_id
                    $stockRequestItemId = $item['stock_request_item_id'] ?? null;
                    if ($stockRequestItemId) {
                        $exists = DB::table('stock_request_items')->where('id', $stockRequestItemId)->exists();
                        if (!$exists) $stockRequestItemId = null;
                    }
                    if (!$stockRequestItemId && $stockRequest) {
                        $matched = collect($stockRequest->stockRequestItems)->firstWhere('product_id', $item['product_id']);
                        $stockRequestItemId = $matched->id ?? null;
                    }

                    if (!empty($item['id']) && $existingItems->has($item['id'])) {
                        // Update existing item (triggers updated event -> stock ledger)
                        $existingItems[$item['id']]->update([
                            'stock_request_item_id' => $stockRequestItemId,
                            'product_id'            => $item['product_id'],
                            'quantity'              => $qty,
                            'unit_price'            => $unitPrice,
                            'total_price'           => bcmul($qty, $unitPrice, 10),
                            'remarks'               => $item['remarks'] ?? null,
                            'campus_id'             => $item['campus_id'],
                            'department_id'         => $item['department_id'],
                            'updated_by'            => $user?->id ?? 1,
                        ]);
                        $submittedItemIds[] = $item['id'];
                    } else {
                        // Create new item (triggers created event -> stock ledger)
                        $newItem = StockIssueItem::create([
                            'stock_issue_id'        => $stockIssue->id,
                            'stock_request_item_id' => $stockRequestItemId,
                            'product_id'            => $item['product_id'],
                            'quantity'              => $qty,
                            'unit_price'            => $unitPrice,
                            'total_price'           => bcmul($qty, $unitPrice, 10),
                            'remarks'               => $item['remarks'] ?? null,
                            'campus_id'             => $item['campus_id'],
                            'department_id'         => $item['department_id'],
                            'updated_by'            => $user?->id ?? 1,
                            'deleted_by'            => null,
                        ]);
                        $submittedItemIds[] = $newItem->id;
                    }
                }

                // Soft-delete removed items (triggers deleted event -> stock ledger)
                $stockIssue->stockIssueItems()
                    ->whereNotIn('id', $submittedItemIds)
                    ->get()
                    ->each(function ($item) use ($user) {
                        $item->deleted_by = $user?->id ?? 1;
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
    private function stockIssueValidationRule(?int $stockRequestId = null, ?int $ignoreId = null): array
    {
        $rules = [
            'transaction_date' => ['required', 'date', 'date_format:' . self::DATE_FORMAT],
            'transaction_type' => ['required', 'string', 'max:50'],
            'account_code'     => ['required', 'string', 'max:50'],
            'reference_no'     => ['nullable', 'string', 'max:50'],
            'stock_request_id' => ['nullable', 'integer', 'exists:stock_requests,id'],
            'warehouse_id'     => ['required', 'integer', 'exists:warehouses,id'],
            'requested_by'     => ['nullable', 'integer', 'exists:users,id'],
            'remarks'          => ['nullable', 'string', 'max:1000'],
        ];

        // If updating, ignore current record for unique reference_no
        if ($ignoreId) {
            $rules['reference_no'][] = 'unique:stock_issues,reference_no,' . $ignoreId;
        } else {
            $rules['reference_no'][] = 'unique:stock_issues,reference_no';
        }

        return $rules;
    }

    private function stockIssueItemValidationRule(): array
    {
        return [
            'items' => ['required', 'array', 'min:1'],
            'items.*.id' => ['nullable', 'integer', 'exists:stock_issue_items,id'],
            'items.*.stock_request_item_id' => ['nullable', 'integer'],
            'items.*.product_id' => ['required', 'integer', 'exists:product_variants,id'],
            'items.*.quantity' => ['required', 'numeric', 'min:0.0000000001'], // allow 10 decimals
            'items.*.unit_price' => ['required', 'numeric', 'min:0'],          // allow 10 decimals
            'items.*.campus_id' => ['required', 'integer', 'exists:campus,id'],
            'items.*.department_id' => ['required', 'integer', 'exists:departments,id'],
            'items.*.remarks' => ['nullable', 'string', 'max:1000'],
        ];
    }

    /**
     * Generate issue reference number using StockRequest -> Warehouse -> Building -> Campus short_name
     */
    private function generateReferenceNo(?StockRequest $stockRequest, string $requestDate): string
    {
        // Determine warehouse shortName; fallback to 'WH' when stockRequest not provided
        $shortName = 'WH';
        if ($stockRequest?->warehouse_id) {
            $warehouse = Warehouse::with('building.campus')->find($stockRequest->warehouse_id);
            $shortName = $warehouse->building?->campus?->short_name ?? $shortName;
        }

        try {
            $date = \Carbon\Carbon::createFromFormat(self::DATE_FORMAT, $requestDate);
            if (!$date || $date->format(self::DATE_FORMAT) !== $requestDate) {
                throw new \InvalidArgumentException('Invalid date format. Expected ' . self::DATE_FORMAT . '.');
            }
        } catch (\Exception $e) {
            throw new \InvalidArgumentException('Invalid date format. Expected ' . self::DATE_FORMAT . '.', 0, $e);
        }

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

    // Helper functions

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
    public function getProducts(Request $request): JsonResponse
    {
        $result = $this->productService->getStockProducts($request->all());
        return response()->json($result);
    }


    public function getCampuses(Request $request)
    {
        $campuses = Campus::where('is_active', 1)->get();

        return $campuses->map(fn($c) => [
            'id'   => $c->id,
            'text' => $c->short_name, // Select2 needs "text"
        ]);
    }

    public function getWarehouses(Request $request)
    {
        $warehouses = Warehouse::where('is_active', 1)->get();

        return $warehouses->map(fn($w) => [
            'id'   => $w->id,
            'text' => $w->name, // Select2 needs "text"
        ]);
    }

    public function getDepartments(Request $request)
    {
        $departments = Department::where('is_active', 1)->get();

        return $departments->map(fn($d) => [
            'id'   => $d->id,
            'text' => $d->short_name, // Select2 needs "text"
        ]);
    }

    public function getRequesters(Request $request)
    {
        $requesters = User::where('is_active', 1)->get();

        return $requesters->map(fn($r) => [
            'id'   => $r->id,
            'text' => $r->name, // Select2 needs "text"
        ]);
    }

    private function generateDebitNoteNo(
        int $warehouseId,
        int $departmentId,
        int $year,
        int $month,
        int $campusId
    ): string {
        $campus = Campus::find($campusId);
        $campusCode = $campus->short_name ?? 'UK'; // shorter default

        // Short format: DNYYMM-W#-D#-CAMP
        return sprintf(
            'DN%02d%02d-W%s-D%s-%s',
            $year % 100, // last 2 digits of year
            $month,
            $warehouseId,
            $departmentId,
            $campusCode
        );
    }


}
