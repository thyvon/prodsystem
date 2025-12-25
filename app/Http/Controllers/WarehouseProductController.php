<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\WarehouseProduct;
use App\Models\WarehouseProductReport;
use App\Models\WarehouseProductReportItems;
use App\Models\Warehouse;
use App\Models\Approval;
use App\Models\User;
use Illuminate\View\View;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Http\JsonResponse;
use App\Imports\WarehouseProductImport;
use Maatwebsite\Excel\Facades\Excel;
use App\Services\WarehouseStockService;
use App\Services\ApprovalService;
use App\Services\ProductService;
use Illuminate\Support\Facades\Log;
use Spatie\Browsershot\Browsershot;
use Carbon\Carbon;
use App\Exports\WarehouseStockPivotExport;

class WarehouseProductController extends Controller
{

    protected $warehouseStockService;
    protected $productService;
    protected $approvalService;

    public function __construct(
        WarehouseStockService $warehouseStockService,
        ProductService $productService,
        ApprovalService $approvalService
    ) {
        $this->warehouseStockService = $warehouseStockService;
        $this->productService = $productService;
        $this->approvalService = $approvalService;
    }

    private const DATE_FORMAT = 'Y-m-d';

    public function index()
    {
        // $this->authorize('viewAny', WarehouseProduct::class);
        return view('Inventory.warehouse.warehouse-product.index');
    }
    /**
     * Get warehouse-product list based on variants (simplified & performant)
     */
    public function getWarehouseProducts(Request $request)
    {
        // $this->authorize('viewAny', WarehouseProduct::class);

        $validated = $request->validate([
            'search' => 'nullable|string|max:255',
            'sortColumn' => 'nullable|string',
            'sortDirection' => 'nullable|string|in:asc,desc',
            'limit' => 'nullable|integer|min:1|max:100',
            'draw' => 'nullable|integer',
            'page' => 'nullable|integer|min:1',
        ]);

        $limit = $validated['limit'] ?? 10;
        $draw = $validated['draw'] ?? 1;
        $page = $validated['page'] ?? 1;
        $search = $validated['search'] ?? '';
        $sortColumn = $validated['sortColumn'] ?? 'warehouse_products.created_at';
        $sortDirection = $validated['sortDirection'] ?? 'desc';

        $query = DB::table('warehouse_products')
            ->join('product_variants', 'warehouse_products.product_id', '=', 'product_variants.id')
            ->join('products', 'product_variants.product_id', '=', 'products.id')
            ->join('warehouses', 'warehouse_products.warehouse_id', '=', 'warehouses.id')
            ->select(
                'warehouse_products.id',
                'warehouse_products.product_id as variant_id',
                'product_variants.item_code as variant_item_code',
                'products.id as product_id',
                'products.name as product_name',
                'warehouses.id as warehouse_id',
                'warehouses.name as warehouse_name',
                'warehouse_products.alert_quantity',
                'warehouse_products.is_active',
                'warehouse_products.created_at',
                'warehouse_products.updated_at'
            );

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('product_variants.item_code', 'like', "%$search%")
                  ->orWhere('products.name', 'like', "%$search%")
                  ->orWhere('warehouses.name', 'like', "%$search%");
            });
        }

        $total = $query->count();

        // Handle sorting
        $allowedSort = [
            'variant_item_code' => 'product_variants.item_code',
            'product_name' => 'products.name',
            'warehouse_name' => 'warehouses.name',
            'alert_quantity' => 'warehouse_products.alert_quantity',
            'is_active' => 'warehouse_products.is_active',
            'created_at' => 'warehouse_products.created_at',
            'updated_at' => 'warehouse_products.updated_at',
        ];

        $sortColumn = $allowedSort[$sortColumn] ?? 'warehouse_products.created_at';

        $data = $query->orderBy($sortColumn, $sortDirection)
            ->skip(($page - 1) * $limit)
            ->take($limit)
            ->get();

        return response()->json([
            'data' => $data,
            'recordsTotal' => $total,
            'recordsFiltered' => $total,
            'draw' => $draw,
        ]);
    }

    public function editData(WarehouseProduct $warehouseProduct): JsonResponse
    {
        // Authorize if needed
        // $this->authorize('view', $warehouseProduct);

        // Load related product and warehouse for display
        $warehouseProduct->load(['variant:id,item_code,product_id', 'variant.product:id,name', 'warehouse:id,name']);

        return response()->json([
            'data' => [
                'id' => $warehouseProduct->id,
                'variant_id' => $warehouseProduct->product_id,
                'variant_item_code' => $warehouseProduct->variant->item_code,
                'product_id' => $warehouseProduct->variant->product->id ?? null,
                'product_name' => $warehouseProduct->variant->description . ' ' . $warehouseProduct->variant-> product->name ?? null,
                'warehouse_id' => $warehouseProduct->warehouse_id,
                'warehouse_name' => $warehouseProduct->warehouse->name ?? null,
                'alert_quantity' => $warehouseProduct->alert_quantity,
                'order_leadtime_days' => $warehouseProduct->order_leadtime_days,
                'stock_out_forecast_days' => $warehouseProduct->stock_out_forecast_days,
                'target_inv_turnover_days' => $warehouseProduct->target_inv_turnover_days,
                'is_active' => $warehouseProduct->is_active,
            ]
        ]);
    }

    public function import(Request $request)
    {
        $request->validate([
            'file' => 'required|file|mimes:xlsx,csv,xls|max:2048'
        ]);

        $import = new WarehouseProductImport();

        try {
            // Run the import
            Excel::import($import, $request->file('file'));

            return response()->json([
                'message' => "✅ Warehouse Products imported successfully.",
            ]);
        } catch (\Exception $e) {
            // Stop on first error, return row number and message
            return response()->json([
                'message' => "❌ Import failed: " . $e->getMessage(),
            ], 422); // Use 422 for validation/import errors
        }
    }


    public function update(Request $request, WarehouseProduct $warehouseProduct): JsonResponse
    {
        // Authorize if needed
        // $this->authorize('update', $warehouseProduct);

        // Validation rules
        $validated = Validator::make($request->all(), [
            'product_id' => 'nullable|exists:product_variants,id',
            'warehouse_id' => 'nullable|exists:warehouses,id',
            'alert_quantity' => 'nullable|numeric|min:0',
            'order_leadtime_days' => 'nullable|integer|min:0',
            'stock_out_forecast_days' => 'nullable|integer|min:0',
            'target_inv_turnover_days' => 'nullable|integer|min:0',
            'is_active' => 'nullable|boolean',
        ])->validate();

        DB::beginTransaction();
        try {
            $warehouseProduct->update([
                'product_id' => $validated['product_id'] ?? $warehouseProduct->product_id,
                'warehouse_id' => $validated['warehouse_id'] ?? $warehouseProduct->warehouse_id,
                'alert_quantity' => $validated['alert_quantity'] ?? $warehouseProduct->alert_quantity,
                'order_leadtime_days' => $validated['order_leadtime_days'] ?? $warehouseProduct->order_leadtime_days,
                'stock_out_forecast_days' => $validated['stock_out_forecast_days'] ?? $warehouseProduct->stock_out_forecast_days,
                'target_inv_turnover_days' => $validated['target_inv_turnover_days'] ?? $warehouseProduct->target_inv_turnover_days,
                'is_active' => $validated['is_active'] ?? $warehouseProduct->is_active,
                'updated_by' => Auth::id(),
            ]);

            DB::commit();

            return response()->json([
                'message' => 'Warehouse product updated successfully.',
                'data' => $warehouseProduct,
                'success' => true
            ]);
        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'message' => 'Failed to update warehouse product.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function getStockReportByProduct(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'warehouse_id' => 'required|exists:warehouses,id',
            'product_id'   => 'required|exists:warehouse_products,product_id',
        ]);

        $stockReport = $this->warehouseStockService->getStockReportByProduct(
            $validated['warehouse_id'],
            $validated['product_id']
        );

        if (!$stockReport) {
            return response()->json(['message' => 'Product not found in this warehouse'], 404);
        }

        return response()->json(['warehouseProductReport' => $stockReport]);
    }

    // Reports Stock

    public function reportIndex ()
    {
        return view('Inventory.warehouse.warehouse-product.report-index');
    }

    public function getReportList(Request $request): JsonResponse
    {
        // $this->authorize('viewAny', WarehouseProductReport::class);

        $request->validate([
            'search'        => 'nullable|string|max:255',
            'page'          => 'nullable|integer|min:1',
            'limit'         => 'nullable|integer|min:1|max:200',
            'sortColumn'    => 'nullable|string|in:reference_no,report_date,created_at,approval_status',
            'sortDirection' => 'nullable|string|in:asc,desc',
            'warehouse_ids' => 'nullable|array',
        ]);

        // Select only required columns
        $query = WarehouseProductReport::select([
                'id',
                'reference_no',
                'report_date',
                'warehouse_id',
                'approval_status',
                'created_by',
                'created_at'
            ])
            ->with([
                'creater:id,name',
                'warehouse:id,name'
            ]);

        // Search
        if ($search = $request->search) {
            $query->where(function ($q) use ($search) {
                $q->where('reference_no', 'like', "%{$search}%")
                ->orWhere('approval_status', 'like', "%{$search}%")
                ->orWhereHas('warehouse', function ($sub) use ($search) {
                    $sub->where('name', 'like', "%{$search}%");
                });
            });
        }

        // Warehouse filter
        if ($request->filled('warehouse_ids')) {
            $query->whereIn('warehouse_id', $request->warehouse_ids);
        }

        // Sorting
        $sortColumn    = $request->sortColumn ?? 'created_at';
        $sortDirection = $request->sortDirection ?? 'desc';

        $query->orderBy($sortColumn, $sortDirection);

        // Pagination
        $paginated = $query->paginate($request->limit ?? 10);

        return response()->json([
            'data'            => $paginated->items(),
            'recordsTotal'    => $paginated->total(),
            'recordsFiltered' => $paginated->total(),
            'page'            => $paginated->currentPage(),
        ]);
    }

    public function stockOnhandByWarehouseIndex()
    {
        return view('Inventory.warehouse.warehouse-product.stock-onhand-wh');
    }

    public function getWarehouseStockPivot(Request $request)
    {
        $cutoffDate = $request->input('cutoff_date', now()->format('Y-m-d'));
        $search = $request->input('search', '');
        $warehouseIds = $request->input('warehouse_ids', []);
        $limit = intval($request->input('limit', 50));
        $page = intval($request->input('page', 1));
        $offset = ($page - 1) * $limit;

        $transactionDate = Carbon::parse($cutoffDate);

        // ------------------- 1️⃣ Fetch warehouses -------------------
        $warehouses = Warehouse::select('id', 'name')
            ->when($warehouseIds, fn($q) => $q->whereIn('id', $warehouseIds))
            ->orderBy('name')
            ->get();

        $slugify = fn($str) => strtolower(preg_replace('/[^\w]+/', '_', trim($str)));

        // ------------------- 2️⃣ Prepare product query -------------------
        $productQuery = WarehouseProduct::with([
            'variant:id,item_code,product_id,description',
            'variant.product:id,name,unit_id',
            'variant.product.unit:id,name'
        ])->where('is_active', 1);

        if ($search) {
            $productQuery->where(function ($q) use ($search) {
                $q->whereHas('variant', fn($v) =>
                    $v->where('item_code', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%")
                )->orWhereHas('variant.product', fn($p) =>
                    $p->where('name', 'like', "%{$search}%")
                );
            });
        }

        $totalRows = $productQuery->count(); // total before filtering

        // ------------------- 3️⃣ Get all products -------------------
        $allWarehouseProducts = $productQuery->get()->groupBy('product_id');
        $stockLedgerService = app()->make(\App\Services\StockLedgerService::class);

        // ------------------- 4️⃣ Build pivot rows -------------------
        $allRows = $allWarehouseProducts->map(function ($items, $productId) use ($warehouses, $transactionDate, $stockLedgerService, $slugify) {
            $variant = $items->first()->variant;

            $row = [
                'item_code'   => $variant->item_code,
                'description' => $variant->product->name . ' ' . $variant->description,
                'unit'        => $variant->product->unit->name,
            ];

            $total = 0;
            foreach ($warehouses as $wh) {
                $qty = $stockLedgerService->getStockOnHand($productId, $wh->id, $transactionDate->format('Y-m-d'));
                $colKey = $slugify($wh->name);
                $row[$colKey] = $qty;
                $total += $qty;
            }

            $row['total'] = $total;
            return $row;
        })
        ->filter(fn($row) => $row['total'] > 0) // keep only rows with total > 0
        ->values();

        // ------------------- 5️⃣ Apply pagination manually -------------------
        $paginatedRows = $allRows->slice($offset, $limit)->values();
        $recordsFiltered = $allRows->count(); // total after filtering

        // ------------------- 6️⃣ Response -------------------
        return response()->json([
            'warehouses' => $warehouses->map(fn($wh) => [
                'id' => $wh->id,
                'name' => $wh->name,
                'slug' => $slugify($wh->name)
            ]),
            'data' => $paginatedRows,
            'recordsTotal' => $totalRows,
            'recordsFiltered' => $recordsFiltered,
        ]);
    }


    public function exportWarehouseStockPivot(Request $request)
    {
        $cutoffDate = $request->input('cutoff_date', now()->format('Y-m-d'));
        $search = $request->input('search', '');
        $warehouseIds = $request->input('warehouse_ids', []);

        $transactionDate = Carbon::parse($cutoffDate);

        // ------------------- 1️⃣ Fetch warehouses -------------------
        $warehouses = Warehouse::select('id','name')
            ->when($warehouseIds, fn($q) => $q->whereIn('id', $warehouseIds))
            ->orderBy('name')
            ->get();

        $slugify = fn($str) => strtolower(preg_replace('/[^\w]+/', '_', trim($str)));

        // ------------------- 2️⃣ Prepare product query -------------------
        $productQuery = WarehouseProduct::with([
            'variant:id,item_code,product_id,description',
            'variant.product:id,name,unit_id',
            'variant.product.unit:id,name'
        ])->where('is_active', 1);

        if ($search) {
            $productQuery->where(function($q) use ($search) {
                $q->whereHas('variant', fn($v) =>
                    $v->where('item_code', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%")
                )->orWhereHas('variant.product', fn($p) =>
                    $p->where('name', 'like', "%{$search}%")
                );
            });
        }

        $warehouseProducts = $productQuery->get()->groupBy('product_id');
        $stockLedgerService = app()->make(\App\Services\StockLedgerService::class);

        // ------------------- 3️⃣ Build pivot rows -------------------
        $rows = $warehouseProducts->map(function($items, $productId) use ($warehouses, $transactionDate, $stockLedgerService, $slugify) {
            $variant = $items->first()->variant;
            $row = [
                'item_code' => $variant->item_code,
                'description' => $variant->product->name . ' ' . $variant->description,
                'unit' => $variant->product->unit->name,
            ];

            $total = 0;
            foreach ($warehouses as $wh) {
                $qty = $stockLedgerService->getStockOnHand($productId, $wh->id, $transactionDate->format('Y-m-d'));
                $colKey = $slugify($wh->name);
                $row[$colKey] = $qty;
                $total += $qty;
            }

            $row['total'] = $total;
            return $row;
        })
        // Filter only rows with total > 0
        ->filter(fn($row) => $row['total'] > 0)
        ->values()
        ->toArray();

        // ------------------- 4️⃣ Format warehouses for headings -------------------
        $warehousesFormatted = $warehouses->map(fn($wh) => [
            'name' => $wh->name,
            'slug' => $slugify($wh->name)
        ])->toArray();

        // ------------------- 5️⃣ Export to Excel -------------------
        return Excel::download(new WarehouseStockPivotExport($warehousesFormatted, $rows), 'warehouse_stock_report.xlsx');
    }


    public function showpdf(WarehouseProductReport $warehouseProductReport)
    {
        // Label mapping (use your 3-step approval)
        $mapLabel = [
            'check'   => ['en' => 'Checked By', 'kh' => 'ត្រួតពិនិត្យដោយ'],
            'approve' => ['en' => 'Approved By', 'kh' => 'អនុម័តដោយ'],
        ];

        // Transform approvals
        $approvals = $warehouseProductReport->approvals->map(function ($approval) use ($mapLabel) {
            $typeKey = strtolower($approval->request_type);

            return [
                'user_name'          => $approval->responder?->name ?? 'Unknown',
                'position_name'      => $approval->responderPosition?->title ?? null,
                'request_type'       => $approval->request_type,
                'request_type_label_en' => $mapLabel[$typeKey]['en'] ?? ucfirst($typeKey).' By',
                'request_type_label_kh' => $mapLabel[$typeKey]['kh'] ?? ucfirst($typeKey).' ដោយ',
                'approval_status'    => $approval->approval_status,
                'responded_date'     => $approval->responded_date
                                        ? \Carbon\Carbon::parse($approval->responded_date)->format('M d, Y h:i A')
                                        : null,
                'comment'            => $approval->comment,
                'signature_url'      => $approval->responder?->signature_url ?? null,
            ];
        })->toArray();

        // Prepare report data
        $data = [
            'id'                        => $warehouseProductReport->id,
            'report_date'               => $warehouseProductReport->report_date,
            'reference_no'              => $warehouseProductReport->reference_no,
            'remarks'                   => $warehouseProductReport->remarks,
            'warehouse_name'            => $warehouseProductReport->warehouse->name,
            'warehouse_campus'          => $warehouseProductReport->warehouse->building->campus->short_name ?? null,
            'prepared_by'               => $warehouseProductReport->creater->name ?? null,
            'creator_position'          => $warehouseProductReport->createrPosition?->title ?? null,
            'creator_profile_picture'   => $warehouseProductReport->creater->profile_url ?? null,
            'creator_signature'         => $warehouseProductReport->creater->signature_url ?? null,
            'card_number'               => $warehouseProductReport->creater->card_number ?? null,

            'items' => $warehouseProductReport->items->map(function ($item) {
                $product = $item->product?->product;

                return [
                    'product_code'              => $item->product?->item_code ?? '',
                    'description'               => trim(($product->name ?? '') . ' ' . ($item->product?->description ?? '')),
                    'unit_name'                 => $product?->unit->name ?? '',
                    'unit_price'                => $item->unit_price ?? 0,
                    'avg_6_month_usage'         => $item->avg_6_month_usage ?? 0,
                    'last_month_usage'          => $item->last_month_usage ?? 0,
                    'stock_beginning'           => $item->stock_on_hand ?? 0,
                    'order_plan_qty'            => $item->order_plan_quantity ?? 0,
                    'demand_forecast'           => $item->demand_forecast_quantity ?? 0,
                    'stock_ending'              => $item->stock_ending_quantity ?? 0,
                    'ending_stock_cover_day'    => $item->ending_stock_cover_day ?? 0,
                    'target_safety_stock_day'   => $item->target_safety_stock_day ?? 0,
                    'stock_value'               => $item->stock_value ?? 0,
                    'inventory_reorder_quantity'=> $item->inventory_reorder_quantity ?? 0,
                    'reorder_level_day'         => $item->reorder_level_day ?? 0,
                    'reorder_level_qty'         => $item->reorder_level_qty ?? 0,
                    'max_inventory_level_qty'   => $item->max_inventory_level_quantity ?? 0,
                    'max_inventory_usage_day'   => $item->max_inventory_usage_day ?? 0,
                    'remarks'                   => $item->remarks ?? '',
                ];
            }),

            'approvals' => $approvals,
        ];

        // Render Blade HTML
        $html = view('Inventory.warehouse.warehouse-product.print-report', $data)->render();

        // Generate PDF bytes directly (NO file saved)
        $pdf = Browsershot::html($html)
            ->noSandbox()
            ->emulateMedia('print')
            ->showBackground()
            ->addChromiumArguments([
                '--disable-gpu',
                '--blink-settings=imagesEnabled=true',
                '--disable-extensions',
                '--disable-dev-shm-usage',
                '--disable-software-rasterizer',
                '--single-process',
                '--no-zygote',
                '--no-sandbox',
                '--disable-setuid-sandbox',
                '--no-first-run',
                '--no-default-browser-check',
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
            ->setDelay(20)
            ->format('A4')
            ->landscape()
            ->margins(5, 3, 5, 3)
            ->timeout(40)
            ->setTemporaryFolder('/tmp/chromium')
            ->pdf(); // return raw bytes

        // Return PDF directly to browser
        return response($pdf)
            ->header('Content-Type', 'application/pdf')
            ->header('Content-Disposition', 'inline; filename="Warehouse_Product_Report.pdf"');
    }

    public function showReport(WarehouseProductReport $warehouseProductReport): View
    {
        // $this->authorize('view', $warehouseProductReport);

        return view('Inventory.warehouse.warehouse-product.show-report', [
            'warehouseProductReportId' => $warehouseProductReport->id,
        ]);
    }

    public function getStockReportShow(WarehouseProductReport $warehouseProductReport): JsonResponse
    {
        // ❌ Approval policy disabled
        // $this->authorize('view', $warehouseProductReport);

        // Eager load only product-related relationships
        $warehouseProductReport->load([
            'items.product.product.unit',
            'approvals.responder',
            'warehouse.building.campus'
        ]);

        $mapLabel = [
            'initial' => ['en' => 'Initialed By', 'kh' => 'រៀបចំដោយ'],
            'check'   => ['en' => 'Checked By', 'kh' => 'ត្រួតពិនិត្យដោយ'],
            'approve' => ['en' => 'Approved By', 'kh' => 'អនុម័តដោយ'],
        ];

        $items = $warehouseProductReport->items->map(function ($item) {
            $product = $item->product?->product;

            return [
                'id' => $item->id,
                'product_id' => $item->product_id,
                'product_code' => $item->product?->item_code ?? '',
                'description' => trim(($product->name ?? '') . ' ' . ($item->product?->description ?? '')),
                'unit_name' => $product?->unit->name ?? '',

                'unit_price' => $item->unit_price ?? 0,
                'avg_6_month_usage' => $item->avg_6_month_usage ?? 0,
                'last_month_usage' => $item->last_month_usage ?? 0,
                'stock_beginning' => $item->stock_on_hand ?? 0,
                'order_plan_qty' => $item->order_plan_quantity ?? 0,
                'demand_forecast' => $item->demand_forecast_quantity ?? 0,
                'stock_ending' => $item->stock_ending_quantity ?? 0,
                'ending_stock_cover_day' => $item->ending_stock_cover_day ?? 0,
                'target_safety_stock_day' => $item->target_safety_stock_day ?? 0,
                'stock_value' => $item->stock_value ?? 0,
                'inventory_reorder_qty' => $item->inventory_reorder_quantity ?? 0,
                'reorder_level_qty' => $item->reorder_level_day ?? 0,
                'max_inventory_level_qty' => $item->max_inventory_level_quantity ?? 0,
                'max_inventory_usage_day' => $item->max_inventory_usage_day ?? 0,
                'remarks' => $item->remarks,
            ];
        });

        $approvals = $warehouseProductReport->approvals->map(fn($a) => [
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
            'request_type_label_en' => $mapLabel[$a->request_type]['en'] ?? ucfirst($a->request_type).' By',
            'request_type_label_kh' => $mapLabel[$a->request_type]['kh'] ?? ucfirst($a->request_type).' ដោយ',
        ]);

        $approvalInfo = $this->canShowApprovalButton($warehouseProductReport->id);

        if ($approvalInfo['showButton']) {
            $warehouseProductReport->approvals()
                ->where('responder_id', auth()->id())
                ->where('approval_status', 'Pending')
                ->where('is_seen', false)
                ->update(['is_seen' => true]);
        }


        return response()->json([
            'message' => 'Stock report retrieved successfully.',
            'data' => [
                'id' => $warehouseProductReport->id,
                'report_date' => $warehouseProductReport->report_date,
                'warehouse_id' => $warehouseProductReport->warehouse_id,
                'warehouse_name' => $warehouseProductReport->warehouse->name,
                'warehouse_campus' => $warehouseProductReport->warehouse->building->campus->short_name ?? null,
                'reference_no' => $warehouseProductReport->reference_no,
                'remarks' => $warehouseProductReport->remarks,
                'prepared_by' => $warehouseProductReport->creater->name ?? null,
                'creator_position' => $warehouseProductReport->createrPosition?->title ?? null,
                'creator_profile_picture' => $warehouseProductReport->creater->profile_url ?? null,
                'creator_signature' => $warehouseProductReport->creater->signature_url ?? null,
                'card_number' => $warehouseProductReport->creater->card_number ?? null,

                'items' => $items,

                'approvals' => $approvals,
                'approval_buttons' => $approvalInfo,
            ],
        ]);
    }


    public function getProducts(Request $request): JsonResponse
    {
        $params = $request->all();

        // Use ProductService to fetch warehouse stock products
        $result = $this->productService->getWarehouseStockProductsWithReport($params);

        return response()->json($result);
    }

    public function createReport()
    {
        return view('Inventory.warehouse.warehouse-product.report-form');
    }

    public function editReport(WarehouseProductReport $warehouseProductReport): View
    {
        return view('Inventory.warehouse.warehouse-product.report-form', [
            'warehouseProductReportId' => $warehouseProductReport->id,
        ]);
    }
    public function storeReport(Request $request): JsonResponse
    {
        // Validate input
        $validated = $request->validate([
            'warehouse_id' => 'required|exists:warehouses,id',
            'report_date'  => 'required|date',
            'remarks'      => 'nullable|string',
            'items'        => 'required|array|min:1',
            'items.*.warehouse_product_id' => 'required|exists:warehouse_products,id',
            'items.*.remarks'              => 'nullable|string',
            'items.*.unit_price'           => 'nullable|numeric',
            'items.*.avg_6_month_usage'    => 'nullable|numeric',
            'items.*.last_month_usage'     => 'nullable|numeric',
            'items.*.stock_on_hand'        => 'nullable|numeric',
            'items.*.order_plan_qty'       => 'nullable|numeric',
            'items.*.demand_forecast_quantity' => 'nullable|numeric',
            'items.*.ending_stock_cover_day'   => 'nullable|numeric',
            'items.*.target_safety_stock_day'  => 'nullable|numeric',
            'items.*.stock_ending_quantity'             => 'nullable|numeric',
            'items.*.stock_value'               => 'nullable|numeric',
            'items.*.inventory_reorder_quantity' => 'nullable|numeric',
            'items.*.reorder_level_day'          => 'nullable|numeric',
            'items.*.reorder_level_qty' => 'nullable|numeric',
            'items.*.max_inventory_level_quantity' => 'nullable|numeric',
            'items.*.max_inventory_usage_day'      => 'nullable|numeric',
            'approvals'      => 'required|array|min:2',
            'approvals.*.user_id'      => 'required|exists:users,id',
            'approvals.*.request_type' => 'required|in:initial,check,approve',
        ]);

        DB::beginTransaction();
        try {
            // Generate reference number
            $referenceNo = $this->generateReferenceNo($validated['warehouse_id'], $validated['report_date']);

            // Create main report
            $warehouseProductReport = WarehouseProductReport::create([
                'reference_no'    => $referenceNo,
                'report_date'     => $validated['report_date'],
                'warehouse_id'    => $validated['warehouse_id'],
                'approval_status' => 'Pending',
                'remarks'         => $validated['remarks'],
                'created_by'      => Auth::id(),
                'position_id'     => Auth::user()->current_position_id,
            ]);

            $this->storeApprovals($warehouseProductReport, $validated['approvals']);

            // Map items by warehouse_product_id for easy lookup
            $itemsData = collect($validated['items'])->keyBy('warehouse_product_id');

            // Get warehouse products
            $warehouseProducts = WarehouseProduct::whereIn('id', $itemsData->keys())
                ->where('warehouse_id', $validated['warehouse_id'])
                ->get();

            foreach ($warehouseProducts as $whProduct) {
                $stockData = $this->warehouseStockService->getStockReportByProduct(
                    $validated['warehouse_id'],
                    $whProduct->product_id
                );

                if (!$stockData) continue;

                // Get remarks from request items
                WarehouseProductReportItems::create([
                    'report_id'                    => $warehouseProductReport->id,
                    'product_id'                   => $whProduct->product_id,
                    'warehouse_product_id'         => $whProduct->id,
                    'unit_price'                   => $itemsData->get($whProduct->id)['unit_price'] ?? $stockData['avg_price'] ?? 0,
                    'avg_6_month_usage'            => $itemsData->get($whProduct->id)['avg_6_month_usage'] ?? $stockData['avg_usage'] ?? 0,
                    'last_month_usage'             => $itemsData->get($whProduct->id)['last_month_usage'] ?? $stockData['avg_daily_use_per_day'] ?? 0,
                    'stock_on_hand'                => $itemsData->get($whProduct->id)['stock_on_hand'] ?? $stockData['stock_onhand'] ?? 0,
                    'order_plan_quantity'          => $itemsData->get($whProduct->id)['order_plan_qty'] ?? $stockData['order_plan_qty'] ?? 0,
                    'demand_forecast_quantity'     => $itemsData->get($whProduct->id)['demand_forecast_quantity'] ?? $stockData['demand_stock_out_forecast_qty'] ?? 0,
                    'ending_stock_cover_day'       => $itemsData->get($whProduct->id)['ending_stock_cover_day'] ?? $stockData['ending_stock_cover_days'] ?? 0,
                    'target_safety_stock_day'      => $itemsData->get($whProduct->id)['target_safety_stock_day'] ?? $stockData['target_safety_stock_days'] ?? 0,
                    'stock_ending_quantity'        => $itemsData->get($whProduct->id)['stock_ending_quantity'] ?? $stockData['ending_stock_qty'] ?? 0,
                    'stock_value'                  => $itemsData->get($whProduct->id)['stock_value'] ?? $stockData['stock_value_usd'] ?? 0,
                    'inventory_reorder_quantity'   => $itemsData->get($whProduct->id)['inventory_reorder_quantity'] ?? $stockData['inventory_reorder_qty'] ?? 0,
                    'reorder_level_day'            => $itemsData->get($whProduct->id)['reorder_level_day'] ?? $stockData['reorder_level_days'] ?? 0,
                    'reorder_level_qty'            => $itemsData->get($whProduct->id)['reorder_level_qty'] ?? $stockData['reorder_level_qty'] ?? 0,
                    'max_inventory_level_quantity' => $itemsData->get($whProduct->id)['max_inventory_level_quantity'] ?? $stockData['max_inventory_level_qty'] ?? 0,
                    'max_inventory_usage_day'      => $itemsData->get($whProduct->id)['max_inventory_usage_day'] ?? $stockData['max_usage_days'] ?? 0,
                    'remarks'                      => $itemsData->get($whProduct->id)['remarks'] ?? null,
                    'updated_by'                   => Auth::id(),
                ]);
            }

            DB::commit();

            return response()->json([
                'message'   => '✅ Stock report created successfully.',
                'success'   => true,
                'report_id' => $warehouseProductReport->id,
                'reference_no' => $referenceNo,
            ]);
        } catch (\Throwable $e) {
            DB::rollBack();
            return response()->json([
                'message' => '❌ Failed to create stock report.',
                'error'   => $e->getMessage(),
            ], 500);
        }
    }
   
    public function updateReport(Request $request, WarehouseProductReport $warehouseProductReport): JsonResponse
    {
        $validated = $request->validate([
            'report_date'  => 'required|date',
            'remarks'      => 'nullable|string',
            'items'        => 'required|array|min:1',
            'items.*.warehouse_product_id' => 'required|exists:warehouse_products,id',
            'items.*.remarks' => 'nullable|string',
            'items.*.unit_price' => 'nullable|numeric',
            'items.*.avg_6_month_usage' => 'nullable|numeric',
            'items.*.last_month_usage' => 'nullable|numeric',
            'items.*.stock_on_hand' => 'nullable|numeric',
            'items.*.order_plan_qty' => 'nullable|numeric',
            'items.*.stock_ending_quantity' => 'nullable|numeric',
            'items.*.demand_forecast_quantity' => 'nullable|numeric',
            'items.*.ending_stock_cover_day' => 'nullable|numeric',
            'items.*.target_safety_stock_day' => 'nullable|numeric',
            'items.*.stock_value' => 'nullable|numeric',
            'items.*.inventory_reorder_quantity' => 'nullable|numeric',
            'items.*.reorder_level_day' => 'nullable|numeric',
            'items.*.reorder_level_qty' => 'nullable|numeric',
            'items.*.max_inventory_level_quantity' => 'nullable|numeric',
            'items.*.max_inventory_usage_day' => 'nullable|numeric',
            'approvals'    => 'required|array|min:2',
            'approvals.*.user_id'      => 'required|exists:users,id',
            'approvals.*.request_type' => 'required|in:initial,check,approve',
        ]);

        DB::beginTransaction();

        try {
            // Update main report
            $warehouseProductReport->update([
                'report_date' => $validated['report_date'],
                'remarks'     => $validated['remarks'] ?? null,
                'updated_by'  => Auth::id(),
                'position_id' => Auth::user()->current_position_id,
            ]);

            // Reset approvals
            $warehouseProductReport->approvals()->delete();
            $this->storeApprovals($warehouseProductReport, $validated['approvals']);

            // Map items by warehouse_product_id for easy lookup
            $itemsData = collect($validated['items'])->keyBy('warehouse_product_id');

            // Get current warehouse products
            $warehouseProducts = WarehouseProduct::whereIn('id', $itemsData->keys())
                ->where('warehouse_id', $warehouseProductReport->warehouse_id)
                ->get();

            foreach ($warehouseProducts as $whProduct) {
                $stockData = $this->warehouseStockService->getStockReportByProduct(
                    $warehouseProductReport->warehouse_id,
                    $whProduct->product_id
                );

                if (!$stockData) continue;

                $item = $itemsData->get($whProduct->id);

                WarehouseProductReportItems::updateOrCreate(
                    [
                        'report_id' => $warehouseProductReport->id,
                        'warehouse_product_id' => $whProduct->id
                    ],
                    [
                        'product_id' => $whProduct->product_id,
                        'unit_price' => $item['unit_price'] ?? $stockData['avg_price'] ?? 0,
                        'avg_6_month_usage' => $item['avg_6_month_usage'] ?? $stockData['avg_usage'] ?? 0,
                        'last_month_usage' => $item['last_month_usage'] ?? $stockData['avg_daily_use_per_day'] ?? 0,
                        'stock_on_hand' => $item['stock_on_hand'] ?? $stockData['stock_onhand'] ?? 0,
                        'order_plan_quantity' => $item['order_plan_qty'] ?? $stockData['order_plan_qty'] ?? 0,
                        'demand_forecast_quantity' => $item['demand_forecast_quantity'] ?? $stockData['demand_stock_out_forecast_qty'] ?? 0,
                        'ending_stock_cover_day' => $item['ending_stock_cover_day'] ?? $stockData['ending_stock_cover_days'] ?? 0,
                        'stock_ending_quantity' => $item['stock_ending_quantity'] ?? $stockData['ending_stock_qty'] ?? 0,
                        'target_safety_stock_day' => $item['target_safety_stock_day'] ?? $stockData['target_safety_stock_days'] ?? 0,
                        'stock_value' => $item['stock_value'] ?? $stockData['stock_value_usd'] ?? 0,
                        'inventory_reorder_quantity' => $item['inventory_reorder_quantity'] ?? $stockData['inventory_reorder_qty'] ?? 0,
                        'reorder_level_day' => $item['reorder_level_day'] ?? $stockData['reorder_level_days'] ?? 0,
                        'reorder_level_qty' => $item['reorder_level_qty'] ?? $stockData['reorder_level_qty'] ?? 0,
                        'max_inventory_level_quantity' => $item['max_inventory_level_quantity'] ?? $stockData['max_inventory_level_qty'] ?? 0,
                        'max_inventory_usage_day' => $item['max_inventory_usage_day'] ?? $stockData['max_usage_days'] ?? 0,
                        'remarks' => $item['remarks'] ?? null,
                        'updated_by' => Auth::id(),
                    ]
                );
            }

            DB::commit();

            return response()->json([
                'message' => '✅ Stock report updated successfully.',
                'success' => true,
            ]);
        } catch (\Throwable $e) {
            DB::rollBack();
            return response()->json([
                'message' => '❌ Failed to update stock report.',
                'error'   => $e->getMessage(),
            ], 500);
        }
    }


    // Fetch data for editing a stock report
    public function getReportEditData(WarehouseProductReport $warehouseProductReport): JsonResponse
    {
        // Load related items and product info
        $warehouseProductReport->load([
            'items.product.product.unit',
            'warehouse.building.campus',
            'approvals.responder'
        ]);

        $items = $warehouseProductReport->items->map(function ($item) {
            $product = $item->product?->product;

            return [
                'warehouse_product_id' => $item->warehouse_product_id,
                'product_id' => $item->product_id,
                'item_code' => $item->product?->item_code ?? '',
                'product_name' => $product?->name ?? '',
                'description' => $item->product?->description ?? '',
                'unit_name' => $product?->unit->name ?? '',
                'unit_price' => $item->unit_price ?? 0,
                'avg_6_month_usage' => $item->avg_6_month_usage ?? 0,
                'last_month_usage' => $item->last_month_usage ?? 0,
                'stock_beginning' => $item->stock_on_hand ?? 0,
                'order_plan_qty' => $item->order_plan_quantity ?? 0,
                'demand_forecast' => $item->demand_forecast_quantity ?? 0,
                'stock_ending_quantity' => $item->stock_ending_quantity ?? 0, // fallback if ending stock not stored
                'stock_ending_cover_day' => $item->ending_stock_cover_day ?? 0,
                'target_safety_stock_day' => $item->target_safety_stock_day ?? 0,
                'stock_value' => $item->stock_value ?? 0,
                'inv_reorder_qty' => $item->inventory_reorder_quantity ?? 0,
                'reoder_level_qty' => $item->reorder_level_day ?? 0,
                'max_inv_level_qty' => $item->max_inventory_level_quantity ?? 0,
                'max_inv_usage_day' => $item->max_inventory_usage_day ?? 0,
                'remarks' => $item->remarks ?? '',
            ];
        });

        // Map approvals
        $approvals = $warehouseProductReport->approvals->map(function ($approval) {
            return [
                'id' => $approval->id,
                'request_type' => $approval->request_type,
                'user_id' => $approval->responder_id ?? null,
                'user_name' => $approval->responder?->name ?? null,
            ];
        });

        return response()->json([
            'message' => 'Stock report data retrieved for edit.',
            'data' => [
                'id' => $warehouseProductReport->id,
                'reference_no' => $warehouseProductReport->reference_no,
                'report_date' => $warehouseProductReport->report_date,
                'warehouse_id' => $warehouseProductReport->warehouse_id,
                'remarks' => $warehouseProductReport->remarks,
                'items' => $items,
                'approvals' => $approvals,
            ],
        ]);
    }

    // Delete a stock report and its items
    public function destroyReport(WarehouseProductReport $warehouseProductReport): JsonResponse
    {
        DB::beginTransaction();

        try {
            // Delete related items first
            $warehouseProductReport->items()->delete();
            $warehouseProductReport->approvals()->delete();
            $warehouseProductReport->delete();

            DB::commit();

            return response()->json([
                'message' => '✅ Stock report deleted successfully.',
                'success' => true,
            ]);
        } catch (\Throwable $e) {
            DB::rollBack();
            return response()->json([
                'message' => '❌ Failed to delete stock report.',
                'error'   => $e->getMessage(),
            ], 500);
        }
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
        return "RPT-{$shortName}-{$monthYear}-{$sequence}";
    }

    private function getSequenceNumber(string $shortName, string $monthYear): string
    {
        $prefix = "RPT-{$shortName}-{$monthYear}-";

        $count = WarehouseProductReport::withTrashed()
            ->where('reference_no', 'like', "{$prefix}%")
            ->count();

        return str_pad($count + 1, 2, '0', STR_PAD_LEFT);
    }

    protected function storeApprovals(WarehouseProductReport $warehouseProductReport, array $approvals)
    {
        foreach ($approvals as $approval) {
            $this->approvalService->storeApproval([
                'approvable_type'     => WarehouseProductReport::class,
                'approvable_id'       => $warehouseProductReport->id,
                'document_name'       => 'Stock Report Attachment',
                'document_reference'  => $warehouseProductReport->reference_no,
                'request_type'        => $approval['request_type'],
                'approval_status'     => 'Pending',
                'ordinal'             => $this->ordinal($approval['request_type']),
                'requester_id'        => $warehouseProductReport->created_by,
                'responder_id'        => $approval['user_id'],
                'position_id'         => User::find($approval['user_id'])?->defaultPosition?->id,
            ]);
        }
    }

    private function ordinal($type)
    {
        return ['initial'=> 1,'check' => 2, 'approve' => 3][$type] ?? 1;
    }

    private function canShowApprovalButton(int $documentId): array
    {
        try {
            $userId = auth()->id();
            if (!$userId) {
                return $this->approvalButtonResponse('User not authenticated.');
            }

            $approvals = Approval::where([
                'approvable_type' => WarehouseProductReport::class,
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

    public function submitApproval(Request $request, WarehouseProductReport $warehouseProductReport, ApprovalService $approvalService): JsonResponse 
    {
        // Validate request
        $validated = $request->validate([
            'request_type' => 'required|string|in:initial,check,approve',
            'action'       => 'required|string|in:approve,reject,return',
            'comment'      => 'nullable|string|max:1000',
        ]);

        // Check user permission
        $permission = "warehouseProductReport.{$validated['request_type']}";
        if (!auth()->user()->can($permission)) {
            return response()->json([
                'message' => "You do not have permission to {$validated['request_type']} this stock report.",
            ], 403);
        }

        // Process approval via ApprovalService
        $result = $approvalService->handleApprovalAction(
            $warehouseProductReport,
            $validated['request_type'],
            $validated['action'],
            $validated['comment'] ?? null
        );

        // Ensure $result has 'success' key
        $success = $result['success'] ?? false;

        // Update Stock Report approval_status if successful
        if ($success) {

            $statusByRequestType = [
                'initial' => 'Initialed',
                'check'   => 'Checked',
                'approve' => 'Approved',
            ];

            $statusByAction = [
                'reject' => 'Rejected',
                'return' => 'Returned',
            ];

            if ($validated['action'] === 'approve') {
                // ✅ Approve → use request_type
                $warehouseProductReport->approval_status =
                    $statusByRequestType[$validated['request_type']] ?? 'Approved';
            } else {
                // ❌ Reject / Return → use action
                $warehouseProductReport->approval_status =
                    $statusByAction[$validated['action']] ?? 'Pending';
            }

            $warehouseProductReport->save();
        }


        return response()->json([
            'message'      => $result['message'] ?? 'Action failed',
            'redirect_url' => route('approvals-stock-reports.show', $warehouseProductReport->id),
            'approval'     => $result['approval'] ?? null,
        ], $success ? 200 : 400);
    }

    public function reassignResponder(Request $request, WarehouseProductReport $warehouseProductReport): JsonResponse
    {
        // $this->authorize('reassign', $warehouseProductReport);

        $validated = $request->validate([
            'request_type'   => 'required|string|in:initial,check,approve',
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

        if (!$user->hasPermissionTo("warehouseProductReport.{$validated['request_type']}")) {
            return response()->json([
                'success' => false,
                'message' => "User {$user->id} does not have permission for {$validated['request_type']}.",
            ], 403);
        }

        $approval = Approval::where([
            'approvable_type' => WarehouseProductReport::class,
            'approvable_id'   => $warehouseProductReport->id,
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
                'document_id'  => $warehouseProductReport->id,
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
            'initial'   => $this->usersWithPermission('warehouseProductReport.initial'),
            'check'       => $this->usersWithPermission('warehouseProductReport.check'),
            'approve' => $this->usersWithPermission('warehouseProductReport.approve'),
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
