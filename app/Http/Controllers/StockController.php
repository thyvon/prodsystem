<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Carbon\Carbon;
use Spatie\Browsershot\Browsershot;

use App\Models\StockLedger;
use App\Models\ProductVariant;
use App\Models\Warehouse;
use App\Models\MonthlyStockReport;
use App\Models\User;
use App\Services\ApprovalService;
use App\Models\Approval;

class StockController extends Controller
{
    protected $approvalService;

    public function __construct(ApprovalService $approvalService)
    {
        $this->middleware('auth');
        $this->approvalService = $approvalService;
    }

    // ===================================================================
    // Views
    // ===================================================================
    public function index(): View
    {
        $this->authorize('viewAny', MonthlyStockReport::class);

        return view('Inventory.stock-report.index');
    }

    public function create(): View
    {
        $this->authorize('create', MonthlyStockReport::class);

        return view('Inventory.stock-report.form');
    }

    public function edit(MonthlyStockReport $monthlyStockReport): View
    {
        $this->authorize('update', $monthlyStockReport);

        return view('Inventory.stock-report.form', [
            'monthlyStockReportId' => $monthlyStockReport->id,
        ]);
    }

    public function monthlyReport(): View
    {
        $this->authorize('viewAny', MonthlyStockReport::class);
        return view('Inventory.stock-report.monthly-report');
    }

    public function showDetails(MonthlyStockReport $monthlyStockReport): View
    {
        $this->authorize('view', $monthlyStockReport);

        $approvalButtonData = $this->canShowApprovalButton($monthlyStockReport->id);
        return view('Inventory.stock-report.show', [
            'monthlyStockReportId' => $monthlyStockReport->id,
            'referenceNo' => $monthlyStockReport->reference_no,
            'approvalRequestType' => $approvalButtonData['requestType'],
        ]);
    }

    // ===================================================================
    // API: List Monthly Reports (DataTable)
    // ===================================================================
    public function getMonthlyStockReport(Request $request): JsonResponse
    {
        $this->authorize('viewAny', MonthlyStockReport::class);

        $request->validate([
            'search'        => 'nullable|string|max:255',
            'page'          => 'nullable|integer|min:1',
            'limit'         => 'nullable|integer|min:1|max:200',
            'sortColumn'    => 'nullable|string|in:reference_no,report_date,created_at,approval_status',
            'sortDirection' => 'nullable|string|in:asc,desc',
            'start_date'    => 'nullable|date',
            'end_date'      => 'nullable|date',
            'warehouse_ids' => 'nullable|array',
        ]);

        $query = MonthlyStockReport::with('creator:id,name');

        if ($search = $request->search) {
            $query->where(function ($q) use ($search) {
                $q->where('reference_no', 'like', "%{$search}%")
                  ->orWhere('warehouse_names', 'like', "%{$search}%")
                  ->orWhere('approval_status', 'like', "%{$search}%");
            });
        }

        if ($request->filled('start_date')) {
            $query->whereDate('report_date', '>=', $request->start_date);
        }

        if ($request->filled('end_date')) {
            $query->whereDate('report_date', '<=', $request->end_date);
        }

        if ($request->filled('warehouse_ids')) {
            foreach ($request->warehouse_ids as $id) {
                $query->whereJsonContains('warehouse_ids', $id);
            }
        }

        $sortColumn = $request->sortColumn ?? 'created_at';
        $sortDirection = $request->sortDirection ?? 'desc';

        $query->orderBy($sortColumn, $sortDirection);

        $paginated = $query->paginate($request->limit ?? 10);

        return response()->json([
            'data'            => $paginated->items(),
            'recordsTotal'    => $paginated->total(),
            'recordsFiltered' => $paginated->total(),
            'page'            => $paginated->currentPage(),
        ]);
    }

    // ===================================================================
    // Store New Monthly Stock Report
    // ===================================================================
    public function store(Request $request): JsonResponse
    {
        $this->authorize('create', MonthlyStockReport::class);

        $validated = $this->validateReportRequest($request);

        return DB::transaction(function () use ($validated) {
            $report = $this->createMonthlyReport($validated);
            $this->storeApprovals($report, $validated['approvals']);

            return response()->json([
                'success'      => true,
                'message'      => 'Monthly Stock Report submitted for approval.',
                'reference_no' => $report->reference_no,
                'id'           => $report->id,
            ], 201);
        });
    }


    // ===================================================================
    // Get Edit Data for Monthly Stock Report

    // ===================================================================
    public function getEditData(MonthlyStockReport $monthlyStockReport): JsonResponse
    {
        $this->authorize('update', $monthlyStockReport);

        // $monthlyStockReport->load(['approvals.responder']);
        $warehouseIds = $monthlyStockReport->warehouse_ids ?? [];

        return response()->json([
            'success' => true,
            'data' => [
                'id'              => $monthlyStockReport->id,
                'warehouse_ids'   => is_array($warehouseIds) ? $warehouseIds : json_decode($warehouseIds, true),
                'start_date'      => $monthlyStockReport->start_date?->format('Y-m-d'),
                'end_date'        => $monthlyStockReport->end_date?->format('Y-m-d'),
                'reference_no'    => $monthlyStockReport->reference_no,
                'report_date'     => $monthlyStockReport->report_date?->format('Y-m-d'),
                'warehouse_names' => $monthlyStockReport->warehouse_names ?? [],
                'approval_status' => $monthlyStockReport->approval_status,
                'remarks'         => $monthlyStockReport->remarks,

                'approvals' => $monthlyStockReport->approvals->map(function ($approval) {
                    return [
                        'id'              => $approval->id,
                        'user_id'         => $approval->responder_id,
                        'user_name'       => $approval->responder?->name ?? '',
                        'request_type'    => $approval->request_type,
                    ];
                })->toArray(),
            ],
        ]);
    }

    // ===================================================================
    // Update Existing Report (Only Draft/Pending)
    // ===================================================================
    public function update(Request $request, MonthlyStockReport $monthlyStockReport): JsonResponse
    {
        $this->authorize('update', $monthlyStockReport);

        if (!in_array($monthlyStockReport->approval_status, ['Pending','Returned'])) {
            return response()->json(['success' => false, 'message' => 'Cannot edit approved or rejected reports.'], 403);
        }

        $validated = $this->validateReportRequest($request);

        return DB::transaction(function () use ($validated, $monthlyStockReport) {
            $this->updateMonthlyReport($monthlyStockReport, $validated);
            $monthlyStockReport->approvals()->delete();
            $this->storeApprovals($monthlyStockReport, $validated['approvals']);

            return response()->json([
                'success'      => true,
                'message'      => 'Report updated and re-submitted for approval.',
                'reference_no' => $monthlyStockReport->reference_no,
                'id'           => $monthlyStockReport->id,
            ]);
        });
    }

    // ===================================================================
    // Delete Report (Only Draft/Pending/Rejected)
    // ===================================================================
    public function destroy(MonthlyStockReport $monthlyStockReport): JsonResponse
    {
        $this->authorize('delete', $monthlyStockReport);

        if (!in_array($monthlyStockReport->approval_status, ['Returned', 'Pending', 'Rejected'])) {
            return response()->json(['success' => false, 'message' => 'Approved reports cannot be deleted.'], 403);
        }
        $monthlyStockReport->approvals()->delete();
        $monthlyStockReport->delete();

        return response()->json(['success' => true, 'message' => 'Report deleted successfully.']);
    }

    // ===================================================================
    // View Approved Report as PDF
    // ===================================================================
public function showpdf(MonthlyStockReport $monthlyStockReport)
{
    $this->authorize('view', $monthlyStockReport);

    // Label mapping
    $mapLabel = [
        'verify'       => 'Verified By',
        'check'        => 'Checked By',
        'acknowledge'  => 'Acknowledged By',
    ];

    // Transform approvals
    $approvals = $monthlyStockReport->approvals->map(function ($approval) use ($mapLabel) {
        $typeKey = strtolower($approval->request_type);

        return [
            'user_name'          => $approval->responder?->name ?? 'Unknown',
            'position_name'      => $approval->responderPosition?->title ?? null,
            'request_type_label' => $mapLabel[$typeKey] ?? ucfirst($typeKey).' By',
            'approval_status'    => $approval->approval_status,
            'responded_date' => $approval->responded_date 
                                ? \Carbon\Carbon::parse($approval->responded_date)->format('M d, Y h:i A') 
                                : null,
            'comment'            => $approval->comment,
            'signature_url'      => $approval->responder?->signature_url ?? null,
        ];
    })->toArray();

    // Prepare PDF data
    $data = $this->prepareReportData($monthlyStockReport);
    $data['approvals'] = $approvals;

    // Render HTML
    $html = view('Inventory.stock-report.print-report', $data)->render();

    $fileName = 'Stock_Report.pdf';
    $filePath = storage_path('app/public/' . $fileName);

    // ⚡ ULTRA LOW CPU + LOW RAM CONFIG ⚡
    Browsershot::html($html)
        ->noSandbox()

        // ✓ Reduce CPU by skipping heavy rendering
        ->emulateMedia('print')
        ->showBackground()

        // ✓ Reduce CPU & RAM by disabling expensive features
        ->addChromiumArguments([
            '--disable-gpu',
            '--blink-settings=imagesEnabled=true',
            '--disable-extensions',
            '--disable-dev-shm-usage',
            '--disable-software-rasterizer',
            '--single-process',            // MOST IMPORTANT FOR LOW CPU
            '--no-zygote',
            '--no-sandbox',
            '--disable-setuid-sandbox',
            '--no-first-run',
            '--no-default-browser-check',

            // ↓↓↓ These reduce CPU load drastically ↓↓↓
            '--disable-features=IsolateOrigins,site-per-process,AudioServiceOutOfProcess',
            '--disable-background-networking',
            '--disable-background-timer-throttling',
            '--disable-backgrounding-occluded-windows',
            '--disable-breakpad',
            '--disable-ipc-flooding-protection',
            '--disable-renderer-backgrounding',
            '--disable-client-side-phishing-detection',
            '--disable-hang-monitor',
            '--disable-popup-blocking',
            '--disable-sync',
            '--metrics-recording-only',
            '--mute-audio',
        ])

        // ✓ Lower delay (CPU-friendly)
        ->setDelay(20)

        ->format('A4')
        ->landscape()
        ->margins(5, 3, 5, 3)
        ->timeout(40)
        ->setTemporaryFolder('/tmp/chromium')

        ->save($filePath);

    return response()->file($filePath);
}

    public function generateStockReportPdf(Request $request)
    {
        $this->authorize('viewAny', MonthlyStockReport::class);

        $startDate    = $request->input('start_date') ?? now()->startOfMonth()->toDateString();
        $endDate      = $request->input('end_date') ?? now()->endOfMonth()->toDateString();
        $warehouseIds = $this->parseIntArray($request->input('warehouse_ids', []));
        $productIds   = $this->parseIntArray($request->input('product_ids', []));

        $report = $this->calculateStockReport(
            $startDate, $endDate, $warehouseIds, $productIds,
            $request->input('search', ''),
            $request->input('sortColumn', 'item_code'),
            $request->input('sortDirection', 'asc'),
            paginate: false
        );

        $warehouseNames = $this->getWarehouseNames($warehouseIds);

        // Render HTML from Blade
        $html = view('Inventory.stock-report.print-report', [
            'report'         => collect($report),
            'approvals'      => [],
            'start_date'     => Carbon::parse($startDate)->format('d-m-Y'),
            'end_date'       => Carbon::parse($endDate)->format('d-m-Y'),
            'warehouseNames' => $warehouseNames,
            'reference_no'   => 'DRAFT-'.now()->format('YmdHis'),
            'report_date'    => Carbon::parse($endDate)->format('d-m-Y'),
            'preparedBy'    => Auth::user()->name,
            'preparedByPosition' => Auth::user()->defaultPosition()?->title,
            'preparedDate'  => Carbon::now()->format('d-m-Y'),
        ])->render();

        $fileName = 'Stock_Report_' . Carbon::parse($endDate)->format('M-Y') . '.pdf';
        $filePath = storage_path('app/public/' . $fileName);

        // ---- Browsershot Ultra Low RAM / Fast Setup ----
        Browsershot::html($html)
            ->noSandbox()
            ->setDelay(80)                  // small delay for table rendering
            ->setTemporaryFolder('/tmp/chromium')
            ->emulateMedia('print')
            ->format('A4')
            ->landscape()
            ->timeout(40)
            ->showBackground()
            ->addChromiumArguments([
                '--disable-gpu',
                '--disable-dev-shm-usage',
                '--no-zygote',
                '--single-process',           // <-- low memory
                '--no-sandbox',
                '--disable-setuid-sandbox',
                '--disable-software-rasterizer',
                '--disable-extensions',
                '--blink-settings=imagesEnabled=true',
                '--font-render-hinting=none',
                '--no-first-run',
                '--no-default-browser-check',
            ])
            ->save($filePath);

        // Return PDF inline
        return response()->file($filePath);
    }


    public function getDetails(MonthlyStockReport $monthlyStockReport): JsonResponse
    {
        $this->authorize('view', $monthlyStockReport);

        // Preload relationships ONCE
        $monthlyStockReport->load([
            'creator',
            'creatorPosition',
            'approvals.responder',
            'approvals.responderPosition'
        ]);

        $labelMap = [
            'verify'       => 'Verified By',
            'check'        => 'Checked By',
            'acknowledge'  => 'Acknowledged By',
        ];

        $data = $this->prepareReportData($monthlyStockReport);

        // Approval button logic
        $approvalInfo = $this->canShowApprovalButton($monthlyStockReport->id);

        if ($approvalInfo['showButton']) {
            $monthlyStockReport->approvals()
                ->where('responder_id', auth()->id())
                ->where('approval_status', 'Pending')
                ->where('is_seen', false)
                ->update(['is_seen' => true]);
        }

        return response()->json([
            'report'          => $data['report']->values(),
            'start_date'      => $monthlyStockReport->start_date?->format('Y-m-d'),
            'end_date'        => $monthlyStockReport->end_date?->format('Y-m-d'),
            'warehouse_names' => $data['warehouseNames'],
            'reference_no'    => $monthlyStockReport->reference_no,
            'pdf_file_path'   => $monthlyStockReport->pdf_file_path,
            'report_date'     => $monthlyStockReport->report_date,
            'created_by'      => [
                'name'           => $monthlyStockReport->creator?->name ?? 'Unknown',
                'profile_url'    => $monthlyStockReport->creator?->profile_url ?? null,
                'position_name'  => $monthlyStockReport->creatorPosition?->title ?? null,
            ],
            'remarks'         => $monthlyStockReport->remarks,
            'status'          => $monthlyStockReport->approval_status,
            'approvalButton'  => $approvalInfo['showButton'],
            'button_label'    => ucfirst($approvalInfo['requestType'] ?? ''),
            'request_type'    => $approvalInfo['requestType'] ?? null,

            'responders' => $monthlyStockReport->approvals->map(function ($a) use ($labelMap) {
                $key = strtolower($a->request_type);
                return [
                    'user_id'            => $a->responder_id,
                    'user_name'          => $a->responder?->name ?? 'Unknown',
                    'request_type'       => $a->request_type,
                    'request_type_label' => $labelMap[$key] ?? ucfirst($key).' By',
                    'approval_status'    => $a->approval_status,
                    'responded_date'     => $a->responded_date,
                    'comment'            => $a->comment,
                    'signature_url'      => $a->responder?->signature_url ?? null,
                    'position_name'      => $a->responderPosition?->title ?? null,
                    'user_profile_url'   => $a->responder?->profile_url ?? null,
                ];
            })->toArray(),
        ]);
    }


    // ===================================================================
    // Generate Ad-hoc Stock Report (Live Search + PDF)
    // ===================================================================
    public function stockReport(Request $request): JsonResponse
    {
        $this->authorize('viewAny', MonthlyStockReport::class);
        $startDate = $request->input('start_date') ?? now()->startOfMonth()->toDateString();
        $endDate   = $request->input('end_date') ?? now()->toDateString();
        $warehouseIds = $this->parseIntArray($request->input('warehouse_ids', []));
        $productIds   = $this->parseIntArray($request->input('product_ids', []));

        $request->validate([
            'search'        => 'nullable|string',
            'sortColumn'    => 'nullable|string',
            'sortDirection' => 'nullable|in:asc,desc',
            'limit'         => 'nullable|integer|min:1|max:500',
            'page'          => 'nullable|integer|min:1',
            'draw'          => 'nullable|integer',
        ]);

        $result = $this->calculateStockReport(
            startDate: $startDate,
            endDate: $endDate,
            warehouseIds: $warehouseIds,
            productIds: $productIds,
            search: $request->search ?? '',
            sortColumn: $request->sortColumn ?? 'item_code',
            sortDirection: $request->sortDirection ?? 'asc',
            paginate: true,
            perPage: $request->limit ?? 50,
            page: $request->page ?? 1
        );

        return response()->json([
            'draw'            => (int) ($request->draw ?? 1),
            'recordsTotal'    => $result->total(),
            'recordsFiltered' => $result->total(),
            'data'            => $result->items(),
        ]);
    }

    // ──────────────────────────────────────────────────────────────
    // 2. Ad-hoc / Live Stock Report PDF
    // ──────────────────────────────────────────────────────────────


    // ===================================================================
    // Helpers & Core Logic
    // ===================================================================
    private function validateReportRequest(Request $request)
    {
        $validated = $request->validate([
            'start_date'     => 'required|date',
            'end_date'       => 'required|date|after_or_equal:start_date',
            'warehouse_ids'  => 'required|array|min:1',
            'warehouse_ids.*'=> 'exists:warehouses,id',
            'remarks'        => 'nullable|string|max:2000',
            'approvals'      => 'required|array|min:3',
            'approvals.*.user_id'      => 'required|exists:users,id',
            'approvals.*.request_type' => 'required|in:check,verify,acknowledge',
        ]);

        $types = collect($validated['approvals'])->pluck('request_type')->unique();
        if ($types->count() !== 3) {
            abort(422, 'Must assign exactly one user each for Check, Verify, and Acknowledge.');
        }

        return $validated;
    }

    private function createMonthlyReport(array $data): MonthlyStockReport
    {
        $warehouseNames = Warehouse::whereIn('id', $data['warehouse_ids'])->pluck('name', 'id')->values()->toArray();

        return MonthlyStockReport::create([
            'report_date'      => $data['end_date'],
            'start_date'       => $data['start_date'],
            'end_date'         => $data['end_date'],
            'created_by'       => Auth::id(),
            'position_id'      => Auth::user()->defaultPosition()?->id,
            'warehouse_ids'    => $data['warehouse_ids'],
            'warehouse_names'  => $warehouseNames,
            'remarks'          => $data['remarks'] ?? null,
            'approval_status'  => 'Pending',
        ]);
    }

    private function updateMonthlyReport(MonthlyStockReport $report, array $data): void
    {
        $warehouseNames = Warehouse::whereIn('id', $data['warehouse_ids'])->pluck('name')->toArray();

        $report->update([
            'start_date'       => $data['start_date'],
            'end_date'         => $data['end_date'],
            'warehouse_ids'    => $data['warehouse_ids'],
            'warehouse_names'  => $warehouseNames,
            'remarks'          => $data['remarks'] ?? null,
            'approval_status'  => 'Pending',
        ]);
    }


    public function prepareReportData(MonthlyStockReport $report): array
    {
        $startDate = $report->start_date?->format('Y-m-d') 
            ?? now()->startOfMonth()->toDateString();

        $endDate = $report->end_date?->format('Y-m-d') 
            ?? now()->toDateString();

        $warehouseIds = $this->parseIntArray($report->warehouse_ids);

        // Heavy function only runs once
        $reportData = $this->calculateStockReport(
            $startDate,
            $endDate,
            $warehouseIds,
            [],
            '',
            'item_code',
            'asc',
            false
        );

        return [
            'report'          => $reportData,
            'start_date'      => Carbon::parse($startDate)->format('d-m-Y'),
            'end_date'        => Carbon::parse($endDate)->format('d-m-Y'),
            'warehouseNames'  => $this->getWarehouseNames($warehouseIds),
            'reference_no'    => $report->reference_no,
            'report_date'     => Carbon::parse($endDate)->format('d-m-Y'),
            'msr'             => $report,
            'created_by'      => $report->creator?->name ?? 'Unknown',
            'signature_url'   => $report->creator?->signature_url ?? null,
            'creator_position'=> $report->creatorPosition?->title ?? null,
            'created_at'      => $report->report_date
                                    ? Carbon::parse($report->report_date)->format('M d, Y')
                                    : null,
            'remarks'         => $report->remarks,
            'status'          => $report->approval_status,
        ];
    }

    private function getWarehouseNames(array $ids): string
    {
        if (empty($ids)) return 'All Warehouses';
        return Warehouse::whereIn('id', $ids)->pluck('name')->implode(', ');
    }

    private function parseIntArray($input): array
    {
        if (empty($input)) return [];
        return array_map('intval', is_array($input) ? $input : json_decode($input, true) ?? []);
    }


    private function calculateStockReport(
        $startDate, $endDate, array $warehouseIds = [], array $productIds = [],
        ?string $search = '', string $sortColumn = 'item_code', string $sortDirection = 'asc',
        bool $paginate = false, int $perPage = 50, int $page = 1
    ) {
        $query = ProductVariant::with('product.unit')
            ->whereNull('deleted_at')
            ->where('is_active', 1)
            ->whereHas('product', fn($q) => $q->where('manage_stock', 1))
            ->when($productIds, fn($q) => $q->whereIn('id', $productIds))
            ->when($search, fn($q) => $q->where(function ($sq) use ($search) {
                $sq->where('item_code', 'like', "%{$search}%")
                ->orWhere('description', 'like', "%{$search}%")
                ->orWhereHas('product', fn($pq) => $pq->where('name', 'like', "%{$search}%"));
            }));

        // Sort by item_code at DB level
        if ($sortColumn === 'item_code') {
            $query->orderBy('item_code', $sortDirection);
        } else {
            $query->orderBy('item_code', 'asc');
        }

        // Fetch collection (paginated or not)
        $collection = $paginate ? $query->paginate($perPage, ['*'], 'page', $page)->getCollection()
                                : $query->get();

        // Map each variant to calculated stock row
        $report = $collection->map(fn($variant) =>
            $this->calculateRow($variant, $warehouseIds, $startDate, $endDate)
        );

        // Sort after mapping if needed

        // Apply pagination
        if ($paginate) {
            $paginated = $query->paginate($perPage, ['*'], 'page', $page);
            $paginated->setCollection($report->values());
            return $paginated;
        }

        return $report->values();
    }

    private function calculateRow($variant, array $warehouseIds, $startDate, $endDate)
    {
        $productId   = $variant->id;
        $product     = $variant->product;
        $unitName    = $product->unit->name ?? '';
        $description = trim($product->name . ' ' . $variant->description);

        // Auto-detect warehouses if not provided
        if (empty($warehouseIds)) {
            $warehouseIds = StockLedger::where('product_id', $productId)
                ->distinct()
                ->pluck('parent_warehouse')
                ->toArray();
        }

        // Conditional aggregation with Stock_Begin included in begin sums
        $totals = StockLedger::where('product_id', $productId)
            ->whereIn('parent_warehouse', $warehouseIds)
            ->selectRaw("
                SUM(CASE WHEN transaction_date < ? AND transaction_type IN ('Stock_Begin','Stock_In') THEN quantity ELSE 0 END) AS begin_qty,
                SUM(CASE WHEN transaction_date < ? AND transaction_type IN ('Stock_Begin','Stock_In') THEN total_price ELSE 0 END) AS begin_total,
                SUM(CASE WHEN transaction_date BETWEEN ? AND ? AND transaction_type = 'Stock_Count' THEN quantity ELSE 0 END) AS counted_quantity,
                SUM(CASE WHEN transaction_date BETWEEN ? AND ? AND transaction_type = 'Stock_In' THEN quantity ELSE 0 END) AS in_qty,
                SUM(CASE WHEN transaction_date BETWEEN ? AND ? AND transaction_type = 'Stock_Out' THEN quantity ELSE 0 END) AS out_qty
            ", [
                $startDate, $startDate,
                $startDate, $endDate,
                $startDate, $endDate, 
                $startDate, $endDate
                ])
            ->first();

        $beginQty = (float)($totals->begin_qty ?? 0);
        $inQty    = (float)($totals->in_qty ?? 0);
        $outQty   = (float)($totals->out_qty ?? 0);
        $countedQty = (float)($totals->counted_quantity ?? 0);

        $beginAvg = (float)$this->beginAvg($productId, $startDate);
        $avgPrice = (float)$this->avgPrice($productId, $endDate);

        $beginTotal     = round($beginQty * $beginAvg, 4);
        $inTotal        = round($inQty * $avgPrice, 4);
        $outTotal       = round($outQty * $avgPrice, 4);
        $availableQty   = $beginQty + $inQty;
        $availableTotal = round($availableQty * $avgPrice, 4);
        $endingQty      = $availableQty - abs($outQty);
        $endingTotal    = round($endingQty * $avgPrice, 4);

        return [
            'product_id'         => $productId,
            'item_code'          => $variant->item_code,
            'description'        => $description,
            'unit_name'          => $unitName,
            'beginning_quantity' => $beginQty,
            'beginning_price'    => $beginAvg,
            'beginning_total'    => $beginTotal,
            'stock_in_quantity'  => $inQty,
            'stock_in_total'     => $inTotal,
            'available_quantity' => $availableQty,
            'available_total'    => $availableTotal,
            'stock_out_quantity' => abs($outQty),
            'stock_out_total'    => abs($outTotal),
            'ending_quantity'    => $endingQty,
            'counted_quantity'   => $countedQty,
            'variance_quantity' => $countedQty - $endingQty,
            'ending_total'       => $endingTotal,
            'average_price'      => $avgPrice,
        ];
    }

    private function avgPrice($productId, $endDate = null)
    {
        $query = StockLedger::where('product_id', $productId);
        if ($endDate) $query->whereDate('transaction_date', '<=', $endDate);

        $totalQty   = $query->whereIn('transaction_type', ['Stock_In', 'Stock_Out', 'Stock_Begin'])->sum('quantity');
        $totalPrice = $query->whereIn('transaction_type', ['Stock_In', 'Stock_Out', 'Stock_Begin'])->sum('total_price');

        return $totalQty != 0 ? round($totalPrice / $totalQty, 4) : 0;
    }

    private function beginAvg($productId, $startDate = null)
    {
        $query = StockLedger::where('product_id', $productId);
        if ($startDate) $query->whereDate('transaction_date', '<', $startDate);

        $totalQty   = $query->whereIn('transaction_type', ['Stock_Begin','Stock_Out','Stock_In'])->sum('quantity');
        $totalPrice = $query->whereIn('transaction_type', ['Stock_Begin','Stock_Out','Stock_In'])->sum('total_price');

        return $totalQty != 0 ? round($totalPrice / $totalQty, 4) : 0;
    }


    // Approval Helpers
    protected function storeApprovals(MonthlyStockReport $report, array $approvals)
    {
        foreach ($approvals as $approval) {
            $this->approvalService->storeApproval([
                'approvable_type'     => MonthlyStockReport::class,
                'approvable_id'       => $report->id,
                'document_name'       => 'Monthly Stock Report',
                'document_reference'  => $report->reference_no,
                'request_type'        => $approval['request_type'],
                'approval_status'     => 'Pending',
                'ordinal'             => $this->ordinal($approval['request_type']),
                'requester_id'        => $report->created_by,
                'responder_id'        => $approval['user_id'],
                'position_id'         => User::find($approval['user_id'])?->defaultPosition()?->id,
            ]);
        }
    }

    private function ordinal($type)
    {
        return ['verify' => 1, 'check' => 2, 'acknowledge' => 3][$type] ?? 1;
    }

    private function canShowApprovalButton(int $documentId): array
    {
        try {
            $userId = auth()->id();
            if (!$userId) {
                return $this->approvalButtonResponse('User not authenticated.');
            }

            $approvals = Approval::where([
                'approvable_type' => MonthlyStockReport::class,
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

    public function submitApproval(Request $request, MonthlyStockReport $monthlyStockReport, ApprovalService $approvalService): JsonResponse 
    {
        // Validate request
        $validated = $request->validate([
            'request_type' => 'required|string|in:check,verify,acknowledge',
            'action'       => 'required|string|in:approve,reject,return',
            'comment'      => 'nullable|string|max:1000',
        ]);

        // Check user permission
        $permission = "monthlyStockReport.{$validated['request_type']}";
        if (!auth()->user()->can($permission)) {
            return response()->json([
                'message' => "You do not have permission to {$validated['request_type']} this stock transfer.",
            ], 403);
        }

        // Process approval via ApprovalService
        $result = $approvalService->handleApprovalAction(
            $monthlyStockReport,
            $validated['request_type'],
            $validated['action'],
            $validated['comment'] ?? null
        );

        // Ensure $result has 'success' key
        $success = $result['success'] ?? false;

        // Update StockTransfer approval_status if successful
        if ($success) {
            $statusMap = [
                'check' => 'Checked',
                'verify' => 'Verified',
                'acknowledge' => 'Acknowledged',
                'reject'  => 'Rejected',
                'return'  => 'Returned',
            ];

            $monthlyStockReport->approval_status =
                $statusMap[$validated['action']] ??
                ($statusMap[$validated['request_type']] ?? 'Pending');

            $monthlyStockReport->save();
        }

        return response()->json([
            'message'      => $result['message'] ?? 'Action failed',
            'redirect_url' => route('stock-reports.monthly-report.show', $monthlyStockReport->id),
            'approval'     => $result['approval'] ?? null,
        ], $success ? 200 : 400);
    }

    public function reassignResponder(Request $request, MonthlyStockReport $monthlyStockReport): JsonResponse
    {
        // $this->authorize('reassign', $monthlyStockReport);

        $validated = $request->validate([
            'request_type'   => 'required|string|in:check,verify,acknowledge',
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

        if (!$user->hasPermissionTo("monthlyStockReport.{$validated['request_type']}")) {
            return response()->json([
                'success' => false,
                'message' => "User {$user->id} does not have permission for {$validated['request_type']}.",
            ], 403);
        }

        $approval = Approval::where([
            'approvable_type' => MonthlyStockReport::class,
            'approvable_id'   => $monthlyStockReport->id,
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
                'document_id'  => $monthlyStockReport->id,
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

    public function getApprovalUsers(): JsonResponse
    {
        $users = [
            'check'       => $this->usersWithPermission('monthlyStockReport.check'),
            'verify'      => $this->usersWithPermission('monthlyStockReport.verify'),
            'acknowledge' => $this->usersWithPermission('monthlyStockReport.acknowledge'),
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
}