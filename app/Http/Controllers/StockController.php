<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;
use App\Models\StockLedger;
use App\Models\ProductVariant;
use App\Models\Warehouse;
use App\Models\MonthlyStockReport;
use App\Models\User;
use Carbon\Carbon;
use Spatie\Browsershot\Browsershot;

use App\Services\ApprovalService;

class StockController extends Controller
{
    protected $approvalService;
    protected $productService;
    protected $warehouseService;

    public function __construct(
        ApprovalService $approvalService,
    ) {
        $this->middleware('auth'); // Ensure authentication for all methods
        $this->approvalService = $approvalService;
    }

    public function index(Request $request)
    {
        return view('Inventory.stock-report.index');
    }

    public function showDetails(MonthlyStockReport $monthlyStockReport): View
    {
        // $this->authorize('view', $purchaseRequest);

        return view('Inventory.stock-report.show', [
            'monthlyStockReportId' => $monthlyStockReport->id,
            'referenceNo' => $monthlyStockReport->reference_no,
        ]);
    }

    public function monthlyReport(Request $request)
    {
        return view('Inventory.stock-report.monthly-report');
    }

    public function getMonthlyStockReport(Request $request)
    {
        $validated = $request->validate([
            'search'        => 'nullable|string|max:255',
            'page'          => 'nullable|integer|min:1',
            'limit'         => 'nullable|integer|min:1|max:200',
            'sortColumn'    => 'nullable|string',
            'sortDirection' => 'nullable|string|in:asc,desc',
            'start_date'    => 'nullable|date',
            'end_date'      => 'nullable|date',
            'warehouse_ids' => 'nullable|array',
        ]);

        $search        = $validated['search'] ?? '';
        $sortColumn    = $validated['sortColumn'] ?? 'created_at';
        $sortDirection = $validated['sortDirection'] ?? 'desc';
        $limit         = $validated['limit'] ?? 10;

        $query = MonthlyStockReport::with('creator:id,name');

        /**
         * 🔍 Search
         */
        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('reference_no', 'like', "%{$search}%")
                ->orWhere('warehouse_names', 'like', "%{$search}%")
                ->orWhere('approval_status', 'like', "%{$search}%");
            });
        }

        /**
         * 📅 Date filter
         */
        if (!empty($validated['start_date'])) {
            $query->whereDate('report_date', '>=', $validated['start_date']);
        }

        if (!empty($validated['end_date'])) {
            $query->whereDate('report_date', '<=', $validated['end_date']);
        }

        /**
         * 🏬 Warehouse filter
         */
        if (!empty($validated['warehouse_ids'])) {
            $query->whereJsonContains('warehouse_ids', $validated['warehouse_ids']);
        }

        /**
         * 🔽 Sorting
         * To protect from invalid columns, whitelist them.
         */
        $allowedSort = [
            'reference_no', 'report_date', 'created_at', 'approval_status'
        ];

        if (!in_array($sortColumn, $allowedSort)) {
            $sortColumn = 'created_at';
        }

        $query->orderBy($sortColumn, $sortDirection);

        /**
         * 📄 Pagination
         */
        $paginated = $query->paginate($limit);

        return response()->json([
            'data'            => $paginated->items(),
            'recordsTotal'    => $paginated->total(),
            'recordsFiltered' => $paginated->total(),
            'page'            => $paginated->currentPage(),
        ]);
    }


    public function create(Request $request)
    {
        return view('Inventory.stock-report.form');
    }

public function store(Request $request): JsonResponse
{
    // $this->authorize('create', MonthlyStockReport::class);

    $validated = $request->validate([
        'start_date'     => 'required|date',
        'end_date'       => 'required|date|after_or_equal:start_date',
        'warehouse_ids'  => 'required|array|min:1',
        'warehouse_ids.*'=> 'exists:warehouses,id',
        'remarks'        => 'nullable|string|max:2000',
        'approvals'      => 'required|array|min:3', // usually 3 steps
        'approvals.*.user_id'      => 'required|exists:users,id',
        'approvals.*.request_type' => 'required|in:check,verify,acknowledge',
    ]);

    // Ensure we have exactly one of each approval type
    $types = collect($validated['approvals'])->pluck('request_type');
    if ($types->count() !== 3 || $types->unique()->count() !== 3) {
        return response()->json([
            'success' => false,
            'message' => 'You must assign exactly one user for Check, Verify, and Acknowledge.',
        ], 422);
    }

    return DB::transaction(function () use ($validated) {
        // Fetch warehouse names for storage
        $warehouses = Warehouse::whereIn('id', $validated['warehouse_ids'])
            ->pluck('name', 'id')
            ->toArray();

        $warehouseNames = collect($validated['warehouse_ids'])
            ->map(fn($id) => $warehouses[$id] ?? 'Unknown')
            ->values()
            ->toArray();

        $report = MonthlyStockReport::create([
            'reference_no'     => '125d3', // your logic
            'report_date'      => $validated['end_date'], // or Carbon::today()
            'created_by'       => Auth::id(),
            'position_id'      => Auth::user()->defaultPosition()->id,
            'start_date'       => $validated['start_date'],
            'end_date'         => $validated['end_date'],
            'warehouse_ids'    => $validated['warehouse_ids'],        // stored as JSON array
            'warehouse_names'  => $warehouseNames,                    // stored as JSON array
            'remarks'          => $validated['remarks'] ?? null,
            'approval_status'  => 'Pending',
        ]);

        // Store approval assignments
        $this->storeApprovals($report, $validated['approvals']);

        return response()->json([
            'success'      => true,
            'message'      => 'Monthly Stock Report has been submitted for approval.',
            'reference_no' => $report->reference_no,
            // 'redirect'     => route('stock-reports.show', $report->id),
        ], 201);
    });
}

    // ===================================================================
    // UPDATE — Only Draft or Pending reports
    // ===================================================================
    public function update(Request $request, MonthlyStockReport $monthlyStockReport): JsonResponse
    {
        $this->authorize('update', $monthlyStockReport);

        if (!in_array($monthlyStockReport->approval_status, ['Draft', 'Pending'])) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot edit approved or rejected report.'
            ], 403);
        }

        $validated = $request->validate([
            'start_date'     => 'required|date',
            'end_date'       => 'required|date|after_or_equal:start_date',
            'warehouse_ids'  => 'required|array|min:1',
            'warehouse_ids.*'=> 'exists:warehouses,id',
            'remarks'        => 'nullable|string|max:2000',
            'approvals'      => 'required|array|min:3', // must have 3 steps
            'approvals.*.user_id'      => 'required|exists:users,id',
            'approvals.*.request_type' => 'required|in:check,verify,acknowledge',
        ]);

        // Ensure exactly one of each approval type
        $types = collect($validated['approvals'])->pluck('request_type');
        if ($types->count() !== 3 || $types->unique()->count() !== 3) {
            return response()->json([
                'success' => false,
                'message' => 'You must assign exactly one user for Check, Verify, and Acknowledge.',
            ], 422);
        }

        return DB::transaction(function () use ($validated, $monthlyStockReport) {

            // Update basic info
            $monthlyStockReport->update([
                'start_date'      => $validated['start_date'],
                'end_date'        => $validated['end_date'],
                'remarks'         => $validated['remarks'] ?? null,
                'approval_status' => 'Pending', // reset status to Pending on update
            ]);

            // Sync warehouses
            $warehouses = Warehouse::whereIn('id', $validated['warehouse_ids'])
                ->pluck('name', 'id')
                ->toArray();

            $warehouseNames = collect($validated['warehouse_ids'])
                ->map(fn($id) => $warehouses[$id] ?? 'Unknown')
                ->values()
                ->toArray();

            $monthlyStockReport->update([
                'warehouse_ids'   => $validated['warehouse_ids'],
                'warehouse_names' => $warehouseNames,
            ]);

            // Delete old approvals and store new ones
            $monthlyStockReport->approvals()->delete();
            $this->storeApprovals($monthlyStockReport, $validated['approvals']);

            return response()->json([
                'success'      => true,
                'message'      => 'Monthly Stock Report has been updated and submitted for approval.',
                'reference_no' => $monthlyStockReport->reference_no,
            ]);
        });
    }


    // ===================================================================
    // DESTROY — Soft delete
    // ===================================================================
    public function destroy(MonthlyStockReport $monthlyStockReport): JsonResponse
    {
        $this->authorize('delete', $monthlyStockReport);

        if (!in_array($monthlyStockReport->approval_status, ['Draft', 'Pending', 'Rejected'])) {
            return response()->json(['success' => false, 'message' => 'Approved reports cannot be deleted.'], 403);
        }

        $monthlyStockReport->delete();

        return response()->json(['success' => true, 'message' => 'Report deleted successfully.']);
    }

    // ===================================================================
    // SHOW — View Saved Report (uses your existing print-report.blade.php)
    // ===================================================================
    public function show(MonthlyStockReport $monthlyStockReport)
    {
        // --- Parse parameters from the DB ---
        $startDate = $monthlyStockReport->start_date?->format('Y-m-d') ?: now()->startOfMonth()->toDateString();
        $endDate   = $monthlyStockReport->end_date?->format('Y-m-d') ?: now()->endOfMonth()->toDateString();

        $warehouseIds = $monthlyStockReport->warehouse_ids ?? [];
        $warehouseIds = is_array($warehouseIds) ? array_map('intval', $warehouseIds) : [];

        // --- Fetch stock report using the same logic as generateStockReportPdf ---
        $reportData = $this->calculateStockReport(
            $startDate,
            $endDate,
            $warehouseIds,
            [],                     // Product filter empty = all products
            '',                     // No search
            'item_code',            // Default sort
            'asc',                  // Sort direction
            false                   // Do not paginate
        );

        // --- Prepare warehouse names ---
        $warehouseNames = $monthlyStockReport->warehouse_names ?? [];
        $warehouseNamesStr = is_array($warehouseNames) ? implode(', ', $warehouseNames) : $warehouseNames;
        if (empty($warehouseNamesStr) && !empty($warehouseIds)) {
            $warehouses = Warehouse::whereIn('id', $warehouseIds)->pluck('name')->toArray();
            $warehouseNamesStr = implode(', ', $warehouses);
        }
        if (empty($warehouseNamesStr)) {
            $warehouseNamesStr = 'All Warehouses';
        }

        // --- Render PDF HTML ---
        $html = view('Inventory.stock-report.print-report', [
            'report'         => collect($reportData),
            'start_date'     => Carbon::parse($startDate)->format('d-m-Y'),
            'end_date'       => Carbon::parse($endDate)->format('d-m-Y'),
            'warehouseNames' => $warehouseNamesStr,
            'reference_no'   => $monthlyStockReport->reference_no,
            'report_date'    => Carbon::parse($endDate)->format('d-m-Y'),
            'msr'            => $monthlyStockReport,
            'created_by'     => $monthlyStockReport->creator?->name ?? 'Unknown',
            'remarks'        => $monthlyStockReport->remarks,
            'status'         => $monthlyStockReport->approval_status,
        ])->render();

        // --- Generate PDF with Browsershot ---
        $pdf = Browsershot::html($html)
            ->noSandbox()
            ->landscape()
            ->format('A4')
            ->margins(10, 10, 10, 10)
            ->showBackground()
            ->waitUntilNetworkIdle()
            ->pdf();

        // --- Return PDF inline for preview ---
        return response()->make($pdf, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="Stock_Report_' . Carbon::parse($endDate)->format('M-Y') . '.pdf"',
        ]);
    }

    /**
     * Return Monthly Stock Report data for Vue Show Page
     */
    /**
     * Return Monthly Stock Report data for Vue Show Page
     * Fully supports JSON fields from DB
     */
    public function getDetails(MonthlyStockReport $monthlyStockReport)
    {
        // --- Parse report parameters ---
        $startDate = $monthlyStockReport->start_date?->format('Y-m-d') ?: now()->startOfMonth()->toDateString();
        $endDate   = $monthlyStockReport->end_date?->format('Y-m-d') ?: now()->endOfMonth()->toDateString();

        // --- Ensure warehouse_ids is an array from JSON ---
        $warehouseIds = $monthlyStockReport->warehouse_ids ?? [];
        if (is_string($warehouseIds)) {
            $warehouseIds = json_decode($warehouseIds, true) ?: [];
        }
        $warehouseIds = is_array($warehouseIds) ? array_map('intval', $warehouseIds) : [];

        // --- Fetch stock report (without pagination for show page) ---
        $reportData = $this->calculateStockReport(
            $startDate,
            $endDate,
            $warehouseIds,
            [],            // All products
            '',            // No search
            'item_code',   // Default sort
            'asc',         // Sort direction
            false          // Do not paginate
        );

        // --- Prepare warehouse names from JSON field if exists ---
        $warehouseNames = $monthlyStockReport->warehouse_names ?? [];
        if (is_string($warehouseNames)) {
            $warehouseNames = json_decode($warehouseNames, true) ?: [];
        }

        $warehouseNamesStr = is_array($warehouseNames) ? implode(', ', $warehouseNames) : $warehouseNames;
        if (empty($warehouseNamesStr) && !empty($warehouseIds)) {
            $warehouses = Warehouse::whereIn('id', $warehouseIds)->pluck('name')->toArray();
            $warehouseNamesStr = implode(', ', $warehouses);
        }
        if (empty($warehouseNamesStr)) {
            $warehouseNamesStr = 'All Warehouses';
        }

        // --- Return JSON for Vue ---
        return response()->json([
            'report'          => $reportData->values(),   // Stock report rows
            'start_date'      => $startDate,
            'end_date'        => $endDate,
            'warehouse_ids'   => $warehouseIds,
            'warehouse_names' => $warehouseNamesStr,
            'reference_no'    => $monthlyStockReport->reference_no,
            'report_date'     => now()->format('Y-m-d'),
            'created_by'      => $monthlyStockReport->creator?->name ?? 'Unknown',
            'remarks'         => $monthlyStockReport->remarks,
            'status'          => $monthlyStockReport->approval_status,
        ]);
    }



    // ===================================================================
    // PRINT PDF — From show page
    // ===================================================================
    public function printPdf(MonthlyStockReport $monthlyStockReport)
    {
        $this->authorize('view', $monthlyStockReport);

        $html = $this->show($monthlyStockReport)->render();

        $pdf = Browsershot::html($html)
            ->noSandbox()
            ->landscape()
            ->format('A4')
            ->margins(10, 10, 10, 10)
            ->showBackground()
            ->pdf();

        return response($pdf)
            ->header('Content-Type', 'application/pdf')
            ->header('Content-Disposition', 'inline; filename="' . $monthlyStockReport->reference_no . '.pdf"');
    }

    // ===================================================================
    // MAIN STOCK REPORT — SEARCH + SORT + PAGINATION + PRINT (NO AUTO-SAVE)
    // ===================================================================
    public function stockReport(Request $request)
    {
        // --- Log raw request from frontend ---
        \Log::info('Stock Report Request Input:', $request->all());

        $startDate    = $request->input('start_date');
        $endDate      = $request->input('end_date') ?? \Carbon\Carbon::today()->toDateString();
        $warehouseIds = $this->normalizeArray($request->input('warehouse_ids', []));
        $productIds   = $this->normalizeArray($request->input('product_ids', []));

        // --- Log parsed parameters ---
        \Log::info('Stock Report Parsed Params:', [
            'start_date'    => $startDate,
            'end_date'      => $endDate,
            'warehouse_ids' => $warehouseIds,
            'product_ids'   => $productIds,
        ]);

        $validated = $request->validate([
            'search'        => 'nullable|string|max:255',
            'sortColumn'    => 'nullable|string',
            'sortDirection' => 'nullable|string|in:asc,desc',
            'limit'         => 'nullable|integer|min:1',
            'page'          => 'nullable|integer|min:1',
            'draw'          => 'nullable|integer',
        ]);

        $search        = $validated['search'] ?? '';
        $sortColumn    = $validated['sortColumn'] ?? 'item_code';
        $sortDirection = $validated['sortDirection'] ?? 'asc';
        $perPage       = $validated['limit'] ?? 10;
        $page          = $validated['page'] ?? 1;

        $result = $this->calculateStockReport(
            $startDate,
            $endDate,
            $warehouseIds,
            $productIds,
            $search,
            $sortColumn,
            $sortDirection,
            true, // paginate
            $perPage,
            $page
        );

        return response()->json([
            'data'            => $result->items(),
            'recordsTotal'    => $result->total(),
            'recordsFiltered' => $result->total(),
            'draw'            => (int) ($validated['draw'] ?? 1),
        ]);
    }

    // ===================================================================
    // GENERATE STOCK REPORT PDF
    // ===================================================================
    public function generateStockReportPdf(Request $request)
    {
        // --- Parse filter inputs ---
        $startDate = $request->input('start_date') ?: now()->startOfMonth()->toDateString();
        $endDate   = $request->input('end_date') ?: now()->endOfMonth()->toDateString();

        $warehouseIds = $request->input('warehouse_ids', []);
        $productIds   = $request->input('product_ids', []);

        $warehouseIds = is_array($warehouseIds) ? array_map('intval', $warehouseIds) : [];
        $productIds   = is_array($productIds) ? array_map('intval', $productIds) : [];

        // --- Fetch stock report using your existing logic ---
        $report = $this->calculateStockReport(
            $startDate,
            $endDate,
            $warehouseIds,
            $productIds,
            $request->input('search', ''),
            $request->input('sortColumn', 'item_code'),
            $request->input('sortDirection', 'asc'),
            false
        );

        // --- Warehouse names ---
        $warehouseNames = 'All Warehouses';
        if (!empty($warehouseIds)) {
            $warehouses = Warehouse::whereIn('id', $warehouseIds)->pluck('name')->toArray();
            $warehouseNames = implode(', ', $warehouses);
        }

        // --- Render HTML for PDF ---
        $html = view('Inventory.stock-report.print-report', [
            'report' => collect($report),
            'start_date' => Carbon::parse($startDate)->format('d-m-Y'),
            'end_date' => Carbon::parse($endDate)->format('d-m-Y'),
            'warehouseNames' => $warehouseNames,
            'reference_no' => 'DRAFT-' . now()->format('Ymd-His'),
            'report_date' => Carbon::parse($endDate)->format('d-m-Y'),
        ])->render();

        // --- Generate PDF with Browsershot ---
        $pdf = Browsershot::html($html)
            ->noSandbox()
            ->landscape()
            ->format('A4')
            ->margins(10, 10, 10, 10)
            ->showBackground()
            ->waitUntilNetworkIdle()
            ->pdf();

        // --- Return PDF response ---
        return response()->make($pdf, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="Stock_Report_' . Carbon::parse($endDate)->format('M-Y') . '.pdf"',
        ]);
    }

    // ===================================================================
    // CORE CALCULATION — FULLY RESTORED SEARCH + SORT
    // ===================================================================
    private function calculateStockReport(
        $startDate, $endDate, $warehouseIds = [], $productIds = [],
        $search = '', $sortColumn = 'item_code', $sortDirection = 'asc',
        $paginate = false, $perPage = 10, $page = 1
    ) {
        $warehouseIds = is_array($warehouseIds) ? $warehouseIds : [];
        $productIds   = is_array($productIds) ? $productIds : [];

        $query = ProductVariant::query()
            ->with('product.unit')
            ->whereNull('deleted_at')
            ->whereHas('product', fn($q) => $q->where('manage_stock', 1))
            ->when(!empty($productIds), fn($q) => $q->whereIn('id', $productIds))
            ->when($search !== '', function ($q) use ($search) {
                $q->where(function ($sq) use ($search) {
                    $sq->where('item_code', 'like', "%{$search}%")
                       ->orWhere('description', 'like', "%{$search}%")
                       ->orWhereHas('product', fn($pq) => $pq->where('name', 'like', "%{$search}%"));
                });
            });

        // DB-level sorting for fast columns
        if (in_array($sortColumn, ['item_code', 'description'])) {
            $query->orderBy($sortColumn === 'description' ? 'description' : 'item_code', $sortDirection);
        } else {
            $query->orderBy('item_code', $sortDirection);
        }

        if (!$paginate) {
            $products = $query->get();
        } else {
            $paginated = $query->paginate($perPage, ['*'], 'page', $page);
            $products = $paginated->getCollection();
        }

        $report = $products->map(fn($product) => $this->calculateRow($product, $warehouseIds, $startDate, $endDate));

        // In-memory sort for calculated columns (only current page — very fast)
        if ($paginate && in_array($sortColumn, ['beginning_quantity', 'ending_quantity', 'average_price', 'stock_in_quantity', 'stock_out_quantity'])) {
            $report = $report->sortBy([
                [$sortColumn, $sortDirection === 'asc' ? 'asc' : 'desc']
            ]);
        }

        if ($paginate) {
            $paginated->setCollection($report->values());
            return $paginated;
        }

        return $report;
    }

    // ===================================================================
    // ROW CALCULATION
    // ===================================================================
    private function calculateRow($product, $warehouseIds, $startDate, $endDate)
    {
        $productId = $product->id;

        $beginQty = $beginTotal = $stockInQty = $stockInTotal = $stockOutQty = $stockOutTotal = 0;

        $warehousesToLoop = !empty($warehouseIds)
            ? $warehouseIds
            : StockLedger::where('product_id', $productId)
                ->distinct()
                ->pluck('parent_warehouse')
                ->toArray();

        foreach ($warehousesToLoop as $warehouseId) {
            $begin    = $this->getBeginEnd($productId, $warehouseId, $startDate, $endDate);
            $stockIn  = $this->getStockIn($productId, $warehouseId, $startDate, $endDate);
            $stockOut = $this->getStockOut($productId, $warehouseId, $startDate, $endDate);

            $beginQty      += $begin['quantity'];
            $beginTotal    += $begin['total_price'];
            $stockInQty    += $stockIn['quantity'];
            $stockInTotal  += $stockIn['total_price'];
            $stockOutQty   += $stockOut['quantity'];
            $stockOutTotal += $stockOut['total_price'];
        }

        $endingQty   = $beginQty + $stockInQty + $stockOutQty;
        $avgPrice    = $this->avgPrice($productId, [], $endDate);
        $endingTotal = $endingQty * $avgPrice;

        return [
            'product_id'         => $productId,
            'item_code'          => $product->item_code,
            'description'        => trim(($product->product->name ?? '') . ' ' . ($product->description ?? '')),
            'unit_name'          => $product->product->unit->name ?? '',
            'beginning_quantity' => round($beginQty, 6),
            'beginning_total'    => round($beginTotal, 6),
            'stock_in_quantity'  => round($stockInQty, 6),
            'stock_in_total'     => round($stockInTotal, 6),
            'available_quantity' => round($beginQty + $stockInQty, 6),
            'available_total'    => round($beginTotal + $stockInTotal, 6),
            'stock_out_quantity' => round(abs($stockOutQty), 6),
            'stock_out_total'    => round(abs($stockOutTotal), 6),
            'ending_quantity'    => round($endingQty, 6),
            'ending_total'       => round($endingTotal, 6),
            'average_price'      => round($avgPrice, 6),
        ];
    }

    // ===================================================================
    // HELPERS (unchanged)
    // ===================================================================
    private function normalizeArray($value)
    {
        if (is_string($value)) {
            return array_filter(array_map('trim', explode(',', $value)));
        }
        return is_array($value) ? $value : [];
    }

    private function getBeginEnd($productId, $warehouseId, $startDate, $endDate)
    {
        $query = StockLedger::where('product_id', $productId)
            ->where('parent_warehouse', $warehouseId);

        if ($startDate) {
            $query->whereDate('transaction_date', '<', $startDate);
        } else {
            $query->whereDate('transaction_date', '<', $endDate);
        }

        $rows = $query->get();

        return [
            'quantity'    => round($rows->sum('quantity'), 6),
            'total_price' => round($rows->sum('total_price'), 6),
        ];
    }

    private function getStockIn($productId, $warehouseId, $startDate, $endDate)
    {
        $query = StockLedger::where('product_id', $productId)
            ->where('parent_warehouse', $warehouseId)
            ->where('quantity', '>', 0);

        if ($startDate) {
            $query->whereDate('transaction_date', '>=', $startDate)
                  ->whereDate('transaction_date', '<=', $endDate);
        } else {
            $query->whereDate('transaction_date', $endDate);
        }

        $rows = $query->get();

        return [
            'quantity'    => round($rows->sum('quantity'), 6),
            'total_price' => round($rows->sum('total_price'), 6),
        ];
    }

    private function getStockOut($productId, $warehouseId, $startDate, $endDate)
    {
        $query = StockLedger::where('product_id', $productId)
            ->where('parent_warehouse', $warehouseId)
            ->where('quantity', '<', 0);

        if ($startDate) {
            $query->whereDate('transaction_date', '>=', $startDate)
                  ->whereDate('transaction_date', '<=', $endDate);
        } else {
            $query->whereDate('transaction_date', $endDate);
        }

        $rows = $query->get();

        return [
            'quantity'    => round($rows->sum('quantity'), 6),
            'total_price' => round($rows->sum('total_price'), 6),
        ];
    }

    private function avgPrice($productId, array $warehouseIds = [], $endDate = null)
    {
        $ledger = StockLedger::where('product_id', $productId)
            ->when($endDate, fn($q) => $q->whereDate('transaction_date', '<=', $endDate))
            ->get();

        $totalQty   = $ledger->sum('quantity');
        $totalPrice = $ledger->sum('total_price');
        $outQty     = $ledger->where('quantity', '<', 0)->sum('quantity');
        $outPrice   = $ledger->where('quantity', '<', 0)->sum('total_price');

        $balanceQty   = $totalQty + abs($outQty);
        $balancePrice = $totalPrice + abs($outPrice);

        return $balanceQty ? round($balancePrice / $balanceQty, 6) : 0;
    }

    public function getWarehouses()
    {
        return Warehouse::where('is_active', 1)
            ->get()
            ->map(fn($w) => ['id' => $w->id, 'text' => $w->name]);
    }

    public function getApprovalUsers(): JsonResponse
    {
        // $this->authorize('create', MonthlyStockReport::class);

        $users = [
            'check'       => $this->getUsersByPermission('stockReport.check'),
            'verify'      => $this->getUsersByPermission('stockReport.verify'),
            'acknowledge' => $this->getUsersByPermission('stockReport.acknowledge'),
        ];
        return response()->json($users);
    }

    private function getUsersByPermission(string $permission)
    {
        return User::whereHas('permissions', fn($q) => $q->where('name', $permission))
            ->orWhereHas('roles.permissions', fn($q) => $q->where('name', $permission))
            ->where('id', '!=', auth()->id()) // ← Exclude the authenticated user
            ->select('id', 'name', 'card_number')
            ->orderBy('name')
            ->get();
    }

    protected function storeApprovals(MonthlyStockReport $monthlyStockReport, array $approvals)
    {
        foreach ($approvals as $approval) {
            $approvalData = [
                'approvable_type' => MonthlyStockReport::class,
                'approvable_id' => $monthlyStockReport->id,
                'document_name' => 'Stock Transfer',
                'document_reference' => $monthlyStockReport->reference_no,
                'request_type' => $approval['request_type'],
                'approval_status' => 'Pending',
                'ordinal' => $this->getOrdinalForRequestType($approval['request_type']),
                'requester_id' => $monthlyStockReport->created_by,
                'responder_id' => $approval['user_id'],
                'position_id' => User::find($approval['user_id'])?->defaultPosition()?->id,
            ];
            $this->approvalService->storeApproval($approvalData);
        }
    }

    protected function getOrdinalForRequestType($requestType)
    {
        $ordinals = [
            'check' => 1,
            'verify' => 2,
            'acknowledge' => 3,
        ];
        return $ordinals[$requestType] ?? 1;
    }
}