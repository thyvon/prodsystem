<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
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
        return view('Inventory.stock-report.index');
    }

    public function create(): View
    {
        return view('Inventory.stock-report.form');
    }

    public function edit(MonthlyStockReport $monthlyStockReport): View
    {
        return view('Inventory.stock-report.form', [
            'monthlyStockReportId' => $monthlyStockReport->id,
        ]);
    }

    public function monthlyReport(): View
    {
        return view('Inventory.stock-report.monthly-report');
    }

    public function showDetails(MonthlyStockReport $monthlyStockReport): View
    {
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
        $monthlyStockReport->load(['approvals.responder']);
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
        // $this->authorize('update', $monthlyStockReport);

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
        // $this->authorize('delete', $monthlyStockReport);

        if (!in_array($monthlyStockReport->approval_status, ['Draft', 'Pending', 'Rejected'])) {
            return response()->json(['success' => false, 'message' => 'Approved reports cannot be deleted.'], 403);
        }

        $monthlyStockReport->delete();

        return response()->json(['success' => true, 'message' => 'Report deleted successfully.']);
    }

    // ===================================================================
    // View Approved Report as PDF
    // ===================================================================
    public function show(MonthlyStockReport $monthlyStockReport)
    {
        $data = $this->prepareReportData($monthlyStockReport);
        $html = view('Inventory.stock-report.print-report', $data)->render();

        $pdf = Browsershot::html($html)
            ->noSandbox()
            ->landscape()
            ->format('A4')
            ->margins(10, 10, 10, 10)
            ->showBackground()
            ->waitUntilNetworkIdle()
            ->pdf();

        return response($pdf, 200, [
            'Content-Type'        => 'application/pdf',
            'Content-Disposition' => 'inline; filename="Stock_Report_' . $data['end_date'] . '.pdf"',
        ]);
    }

    // ===================================================================
    // Get Report Details (for Vue Show Page)
    // ===================================================================
    public function getDetails(MonthlyStockReport $monthlyStockReport): JsonResponse
    {
        $monthlyStockReport->load('approvals');

        // Map for request types to display labels
        $mapLabel = [
            'check'       => 'Checked By',
            'verify'      => 'Verified By',
            'acknowledge' => 'Acknowledged By',
        ];

        $data = $this->prepareReportData($monthlyStockReport);
        $approvalInfo = $this->canShowApprovalButton($monthlyStockReport->id);

        return response()->json([
            'report'          => $data['report']->values(),
            'start_date'      => $monthlyStockReport->start_date?->format('Y-m-d'),
            'end_date'        => $monthlyStockReport->end_date?->format('Y-m-d'),
            'warehouse_names' => $data['warehouseNames'],
            'reference_no'    => $monthlyStockReport->reference_no,
            'created_by'      => [
                'name'        => $monthlyStockReport->creator?->name ?? 'Unknown',
                'profile_url' => $monthlyStockReport->creator?->profile_url ?? null,
                'position_name'=> $monthlyStockReport->creatorPosition?->title ?? null,
            ],
            'remarks'         => $monthlyStockReport->remarks,
            'status'          => $monthlyStockReport->approval_status,
            'approvalButton'  => $approvalInfo['showButton'],
            'button_label'     => ucfirst($approvalInfo['requestType'] ?? null),
            'request_type'    => $approvalInfo['requestType'] ?? null,
            'responders'      => $monthlyStockReport->approvals->map(function ($approval) use ($mapLabel) {
                $typeKey = strtolower($approval->request_type);

                return [
                    'user_id'         => $approval->responder_id,
                    'user_name'       => $approval->responder?->name ?? 'Unknown',
                    'request_type'    => $approval->request_type,
                    'request_type_label' => $mapLabel[$typeKey] ?? ucfirst($typeKey) . ' By',
                    'approval_status' => $approval->approval_status,
                    'responded_date'    => $approval->responded_date,
                    'comment'         => $approval->comment,
                    'signature_url'   => $approval->responder?->signature_url ?? null,
                    'position_name'   => $approval->responderPosition?->title ?? null,
                    'user_profile_url'=> $approval->responder?->profile_url ?? null,
                ];
            })->toArray() ?? [],
        ]);
    }


    // ===================================================================
    // Generate Ad-hoc Stock Report (Live Search + PDF)
    // ===================================================================
    public function stockReport(Request $request): JsonResponse
    {
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

    public function generateStockReportPdf(Request $request)
    {
        $startDate = $request->input('start_date') ?? now()->startOfMonth()->toDateString();
        $endDate   = $request->input('end_date') ?? now()->endOfMonth()->toDateString();
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

        $html = view('Inventory.stock-report.print-report', [
            'report'         => collect($report),
            'start_date'     => Carbon::parse($startDate)->format('d-m-Y'),
            'end_date'       => Carbon::parse($endDate)->format('d-m-Y'),
            'warehouseNames' => $warehouseNames,
            'reference_no'   => 'DRAFT-' . now()->format('YmdHis'),
            'report_date'    => Carbon::parse($endDate)->format('d-m-Y'),
        ])->render();

        $pdf = Browsershot::html($html)
            ->noSandbox()->landscape()->format('A4')
            ->margins(10, 10, 10, 10)->showBackground()
            ->waitUntilNetworkIdle()->pdf();

        return response($pdf, 200, [
            'Content-Type'        => 'application/pdf',
            'Content-Disposition' => 'inline; filename="Stock_Report_' . Carbon::parse($endDate)->format('M-Y') . '.pdf"',
        ]);
    }

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

    private function prepareReportData(MonthlyStockReport $report): array
    {
        $startDate = $report->start_date?->format('Y-m-d') ?? now()->startOfMonth()->toDateString();
        $endDate   = $report->end_date?->format('Y-m-d') ?? now()->toDateString();
        $warehouseIds = $this->parseIntArray($report->warehouse_ids);

        $reportData = $this->calculateStockReport($startDate, $endDate, $warehouseIds, [], '', 'item_code', 'asc', false);

        return [
            'report'         => $reportData,
            'start_date'     => Carbon::parse($startDate)->format('d-m-Y'),
            'end_date'       => Carbon::parse($endDate)->format('d-m-Y'),
            'warehouseNames' => $this->getWarehouseNames($warehouseIds),
            'reference_no'   => $report->reference_no,
            'report_date'    => Carbon::parse($endDate)->format('d-m-Y'),
            'msr'            => $report,
            'created_by'     => $report->creator?->name ?? 'Unknown',
            'remarks'        => $report->remarks,
            'status'         => $report->approval_status,
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

    // Core stock calculation (unchanged logic, just cleaner)
    private function calculateStockReport(
        $startDate, $endDate, array $warehouseIds = [], array $productIds = [],
        ?string $search = '', string $sortColumn = 'item_code', string $sortDirection = 'asc',
        bool $paginate = false, int $perPage = 50, int $page = 1
    ) {
        $query = ProductVariant::with('product.unit')
            ->whereNull('deleted_at')
            ->whereHas('product', fn($q) => $q->where('manage_stock', 1))
            ->when($productIds, fn($q) => $q->whereIn('id', $productIds))
            ->when($search, fn($q) => $q->where(function ($sq) use ($search) {
                $sq->where('item_code', 'like', "%{$search}%")
                   ->orWhere('description', 'like', "%{$search}%")
                   ->orWhereHas('product', fn($pq) => $pq->where('name', 'like', "%{$search}%"));
            }));

        if (in_array($sortColumn, ['item_code', 'description'])) {
            $query->orderBy($sortColumn === 'description' ? 'description' : 'item_code', $sortDirection);
        }

        $collection = $paginate ? $query->paginate($perPage, ['*'], 'page', $page)->getCollection()
                                : $query->get();

        $report = $collection->map(fn($variant) =>
            $this->calculateRow($variant, $warehouseIds, $startDate, $endDate)
        );

        if ($paginate && in_array($sortColumn, ['beginning_quantity', 'ending_quantity', 'average_price'])) {
            $report = $report->sortBy($sortColumn, SORT_REGULAR, $sortDirection === 'desc');
        }

        if ($paginate) {
            $paginated = $query->paginate($perPage, ['*'], 'page', $page);
            $paginated->setCollection($report->values());
            return $paginated;
        }

        return $report->values();
    }

    private function calculateRow($variant, array $warehouseIds, $startDate, $endDate)
    {
        $productId = $variant->id;
        $warehouses = empty($warehouseIds)
            ? StockLedger::where('product_id', $productId)->distinct()->pluck('parent_warehouse')->toArray()
            : $warehouseIds;

        $beginQty = $beginTotal = $inQty = $inTotal = $outQty = $outTotal = 0;

        foreach ($warehouses as $wid) {
            $begin = $this->getBeginEnd($productId, $wid, $startDate);
            $in    = $this->getStockMovement($productId, $wid, $startDate, $endDate, 'in');
            $out   = $this->getStockMovement($productId, $wid, $startDate, $endDate, 'out');

            $beginQty += $begin['quantity'];     $beginTotal += $begin['total_price'];
            $inQty    += $in['quantity'];        $inTotal    += $in['total_price'];
            $outQty   += $out['quantity'];       $outTotal   += $out['total_price'];
        }

        $endingQty = $beginQty + $inQty + $outQty;
        $avgPrice  = $this->avgPrice($productId, $endDate);
        $endingTotal = $endingQty * $avgPrice;

        return [
            'product_id'          => $productId,
            'item_code'           => $variant->item_code,
            'description'         => trim($variant->product->name . ' ' . $variant->description),
            'unit_name'           => $variant->product->unit->name ?? '',
            'beginning_quantity'  => round($beginQty, 6),
            'beginning_total'     => round($beginTotal, 6),
            'stock_in_quantity'   => round($inQty, 6),
            'stock_in_total'      => round($inTotal, 6),
            'available_quantity'  => round($beginQty + $inQty, 6),
            'available_total'     => round($beginTotal + $inTotal, 6),
            'stock_out_quantity'  => round(abs($outQty), 6),
            'stock_out_total'     => round(abs($outTotal), 6),
            'ending_quantity'     => round($endingQty, 6),
            'ending_total'        => round($endingTotal, 6),
            'average_price'       => round($avgPrice, 6),
        ];
    }

    private function getBeginEnd($productId, $warehouseId, $startDate)
    {
        return $this->sumLedger($productId, $warehouseId, '<', $startDate);
    }

    private function getStockMovement($productId, $warehouseId, $startDate, $endDate, $type)
    {
        $query = StockLedger::where('product_id', $productId)
            ->where('parent_warehouse', $warehouseId)
            ->whereBetween('transaction_date', [$startDate, $endDate]);

        if ($type === 'in')  $query->where('quantity', '>', 0);
        if ($type === 'out') $query->where('quantity', '<', 0);

        return $this->sumLedgerQuery($query);
    }

    private function sumLedger($productId, $warehouseId, $operator, $date)
    {
        $query = StockLedger::where('product_id', $productId)
            ->where('parent_warehouse', $warehouseId);
        if ($date) $query->whereDate('transaction_date', $operator, $date);
        return $this->sumLedgerQuery($query);
    }

    private function sumLedgerQuery($query)
    {
        $rows = $query->selectRaw('SUM(quantity) as qty, SUM(total_price) as price')->first();
        return [
            'quantity'    => round($rows->qty ?? 0, 6),
            'total_price' => round($rows->price ?? 0, 6),
        ];
    }

    private function avgPrice($productId, $endDate = null)
    {
        $query = StockLedger::where('product_id', $productId);
        if ($endDate) $query->whereDate('transaction_date', '<=', $endDate);

        $totalQty   = $query->sum('quantity');
        $totalPrice = $query->sum('total_price');
        $outQty     = $query->clone()->where('quantity', '<', 0)->sum('quantity');
        $outPrice   = $query->clone()->where('quantity', '<', 0)->sum('total_price');

        $balanceQty   = $totalQty + abs($outQty);
        $balancePrice = $totalPrice + abs($outPrice);

        return $balanceQty > 0 ? round($balancePrice / $balanceQty, 6) : 0;
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
        return ['check' => 1, 'verify' => 2, 'acknowledge' => 3][$type] ?? 1;
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