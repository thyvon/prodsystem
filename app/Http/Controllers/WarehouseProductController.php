<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\WarehouseProduct;
use App\Models\WarehouseProductReport;
use App\Models\WarehouseProductReportItems;
use App\Models\Warehouse;
use Illuminate\View\View;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Http\JsonResponse;
use App\Imports\WarehouseProductImport;
use Maatwebsite\Excel\Facades\Excel;
use App\Services\WarehouseStockService;
use App\Services\ProductService;
use Illuminate\Support\Facades\Log;
use Spatie\Browsershot\Browsershot;

class WarehouseProductController extends Controller
{

    protected $warehouseStockService;
    protected $productService;

    public function __construct(
        WarehouseStockService $warehouseStockService,
        ProductService $productService
    ) {
        $this->warehouseStockService = $warehouseStockService;
        $this->productService = $productService;
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

    // Reports

    public function showpdf(WarehouseProductReport $warehouseProductReport)
    {
        // (Optional) Authorization
        // $this->authorize('view', $warehouseProductReport);

        // Label mapping (use your 3-step approval)
        $mapLabel = [
            'verify'      => 'Verified By',
            'check'       => 'Checked By',
            'approve'     => 'Approved By',
        ];

        // Transform approvals
        $approvals = $warehouseProductReport->approvals->map(function ($approval) use ($mapLabel) {
            $typeKey = strtolower($approval->request_type);

            return [
                'user_name'          => $approval->responder?->name ?? 'Unknown',
                'position_name'      => $approval->responderPosition?->title ?? null,
                'request_type_label' => $mapLabel[$typeKey] ?? ucfirst($typeKey).' By',
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
            'id'                      => $warehouseProductReport->id,
            'report_date'            => $warehouseProductReport->report_date,
            'reference_no'           => $warehouseProductReport->reference_no,
            'remarks'                => $warehouseProductReport->remarks,
            'warehouse_name'         => $warehouseProductReport->warehouse->name,
            'warehouse_campus'       => $warehouseProductReport->warehouse->building->campus->short_name ?? null,
            'prepared_by'            => $warehouseProductReport->creator->name ?? null,
            'creator_position'       => $warehouseProductReport->creatorPosition?->title ?? null,
            'creator_profile_picture'=> $warehouseProductReport->creator->profile_url ?? null,
            'creator_signature'      => $warehouseProductReport->creator->signature_url ?? null,
            'card_number'            => $warehouseProductReport->creator->card_number ?? null,
            'items'                  => $warehouseProductReport->items->map(function ($item) {
                $product = $item->product?->product;

                return [
                    'product_code'              => $item->product?->item_code ?? '',
                    'description'               => trim(($product->name ?? '') . ' ' . ($item->product?->description ?? '')),
                    'unit_name'                 => $product?->unit->name ?? '',
                    'unit_price'                => $item->unit_price ?? 0,
                    'avg_6_month_usage'        => $item->avg_6_month_usage ?? 0,
                    'last_month_usage'         => $item->last_month_usage ?? 0,
                    'stock_beginning'          => $item->stock_on_hand ?? 0,
                    'order_plan_qty'           => $item->order_plan_quantity ?? 0,
                    'demand_forecast'          => $item->demand_forecast_quantity ?? 0,
                    'stock_ending'             => $item->ending_stock ?? 0,
                    'ending_stock_cover_day'   => $item->ending_stock_cover_day ?? 0,
                    'target_safety_stock_day'  => $item->target_safety_stock_day ?? 0,
                    'stock_value'              => $item->stock_value ?? 0,
                    'inventory_reorder_quantity'    => $item->inventory_reorder_quantity ?? 0,
                    'reorder_level_qty'        => $item->reorder_level_day ?? 0,
                    'max_inventory_level_qty'  => $item->max_inventory_level_quantity ?? 0,
                    'max_inventory_usage_day'  => $item->max_inventory_usage_day ?? 0,
                    'remarks'                  => $item->remarks ?? '',
                ];
            }),
            'approvals' => $approvals,
        ];

        // Render Blade
        $html = view('Inventory.warehouse.warehouse-product.print-report', $data)->render();

        // File name
        $fileName = 'Warehouse_Product_Report_' . now()->format('Ymd_His') . '.pdf';
        $filePath = storage_path('app/public/' . $fileName);

        // Browsershot low-resource settings
        Browsershot::html($html)
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
            ->save($filePath);

        return response()->file($filePath);
    }


    public function showReport(WarehouseProductReport $warehouseProductReport): View
    {
        // $this->authorize('view', $warehouseProductReport);

        return view('Inventory.warehouse.warehouse-product.show-report', [
            'warehouseProductReportId' => $warehouseProductReport->id,
        ]);
    }

    public function getStockReport(WarehouseProductReport $warehouseProductReport): JsonResponse
    {
        // ❌ Approval policy disabled
        // $this->authorize('view', $warehouseProductReport);

        // Eager load only product-related relationships
        $warehouseProductReport->load([
            'items.product.product.unit',
            // 'approvals.responder',            // ❌ approval flow disabled
            'warehouse.building.campus'
        ]);

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
                'stock_ending' => $item->ending_stock ?? 0,
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

        // ❌ Approval mapping disabled
        /*
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
            'label' => ucfirst($a->request_type),
        ]);
        */

        // ❌ Approval button logic disabled
        /*
        $approvalInfo = $this->canShowApprovalButton($warehouseProductReport->id);

        if ($approvalInfo['showButton']) {
            $warehouseProductReport->approvals()
                ->where('responder_id', auth()->id())
                ->where('approval_status', 'Pending')
                ->where('is_seen', false)
                ->update(['is_seen' => true]);
        }
        */

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
                'prepared_by' => $warehouseProductReport->creator->name ?? null,
                'creator_position' => $warehouseProductReport->creatorPosition?->title ?? null,
                'creator_profile_picture' => $warehouseProductReport->creator->profile_url ?? null,
                'creator_signature' => $warehouseProductReport->creator->signature_url ?? null,
                'card_number' => $warehouseProductReport->creator->card_number ?? null,

                'items' => $items,

                // ❌ Approval data disabled
                // 'approvals' => $approvals,
                // 'approval_buttons' => $approvalInfo,
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

    public function reportForm()
    {
        return view('Inventory.warehouse.warehouse-product.report-form');
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
            'items.*.remarks' => 'nullable|string',
        ]);

        DB::beginTransaction();
        try {
            // Generate reference number
            $referenceNo = $this->generateReferenceNo($validated['warehouse_id'], $validated['report_date']);

            // Create main report
            $mainReport = WarehouseProductReport::create([
                'reference_no'    => $referenceNo,
                'report_date'     => $validated['report_date'],
                'warehouse_id'    => $validated['warehouse_id'],
                'approval_status' => 'Pending',
                'created_by'      => Auth::id(),
            ]);

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
                $itemRemarks = $itemsData->get($whProduct->id)['remarks'] ?? null;

                WarehouseProductReportItems::create([
                    'report_id'                    => $mainReport->id,
                    'product_id'                   => $whProduct->product_id,
                    'warehouse_product_id'         => $whProduct->id,
                    'unit_price'                   => $stockData['avg_price'] ?? 0,
                    'avg_6_month_usage'            => $stockData['avg_usage'] ?? 0,
                    'last_month_usage'             => $stockData['avg_daily_use_per_day'] ?? 0,
                    'stock_on_hand'                => $stockData['stock_onhand'] ?? 0,
                    'order_plan_quantity'          => $stockData['order_plan_qty'] ?? 0,
                    'demand_forecast_quantity'     => $stockData['demand_stock_out_forecast_qty'] ?? 0,
                    'ending_stock_cover_day'       => $stockData['ending_stock_cover_days'] ?? 0,
                    'target_safety_stock_day'      => $stockData['target_safety_stock_days'] ?? 0,
                    'stock_value'                  => $stockData['stock_value_usd'] ?? 0,
                    'inventory_reorder_quantity'   => $stockData['inventory_reorder_qty'] ?? 0,
                    'reorder_level_day'            => $stockData['reorder_level_qty'] ?? 0,
                    'max_inventory_level_quantity' => $stockData['max_inventory_level_qty'] ?? 0,
                    'max_inventory_usage_day'      => $stockData['max_usage_days'] ?? 0,
                    'remarks'                      => $itemRemarks,
                    'updated_by'                   => Auth::id(),
                ]);
            }

            DB::commit();

            return response()->json([
                'message'   => '✅ Stock report created successfully.',
                'success'   => true,
                'report_id' => $mainReport->id,
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

}
