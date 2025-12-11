<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\WarehouseProduct;
use App\Models\WarehouseProductReport;
use App\Models\WarehouseProductReportItems;
use App\Models\Warehouse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Http\JsonResponse;
use App\Imports\WarehouseProductImport;
use Maatwebsite\Excel\Facades\Excel;
use App\Services\WarehouseStockService;
use App\Services\ProductService;
use Illuminate\Support\Facades\Log;

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

    public function getProducts(Request $request): JsonResponse
    {
        $params = $request->all();

        // Use ProductService to fetch warehouse stock products
        $result = $this->productService->getWarehouseStockProductsWithReport($params);

        return response()->json($result);
    }

    public function reportForm()
    {
        return view('inventory.warehouse.warehouse-product.report-form');
    }
    public function storeReport(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'warehouse_id' => 'required|exists:warehouses,id',
            'report_date'  => 'required|date',
            'remarks'      => 'nullable|string',
            'items'        => 'required|array|min:1',
            'items.*.warehouse_product_id' => 'required|exists:warehouse_products,id',

        ]);

        DB::beginTransaction();
        try {
            $mainReport = WarehouseProductReport::create([
                'reference_no'    => $this->generateReferenceNo($validated['warehouse_id'], $validated['report_date']),
                'report_date'     => $validated['report_date'],
                'warehouse_id'    => $validated['warehouse_id'],
                'approval_status' => 'Pending',
                'created_by'      => Auth::id(),
            ]);

            $warehouseProductIds = collect($validated['items'])->pluck('warehouse_product_id');

            $warehouseProducts = WarehouseProduct::whereIn('id', $warehouseProductIds)
                ->where('warehouse_id', $validated['warehouse_id'])
                ->get();

            foreach ($warehouseProducts as $whProduct) {
                $result = $this->warehouseStockService->getStockReportByProduct(
                    $validated['warehouse_id'],
                    $whProduct->product_id
                );

                if (!$result) continue;

                WarehouseProductReportItems::create([
                    'report_id'                    => $mainReport->id,
                    'product_id'                   => $whProduct->product_id,
                    'warehouse_product_id'         => $whProduct->id,
                    'unit_price'                   => $result['avg_price'] ?? 0,
                    'avg_6_month_usage'            => $result['avg_usage'] ?? 0,
                    'last_month_usage'             => $result['avg_daily_use_per_day'] ?? 0,
                    'stock_on_hand'                => $result['stock_onhand'] ?? 0,
                    'order_plan_quantity'          => $result['order_plan_qty'] ?? 0,
                    'demand_forecast_quantity'     => $result['demand_stock_out_forecast_qty'] ?? 0,
                    'ending_stock_cover_day'       => $result['ending_stock_cover_days'] ?? 0,
                    'target_safety_stock_day'      => $result['target_safety_stock_days'] ?? 0,
                    'stock_value'                  => $result['stock_value_usd'] ?? 0,
                    'inventory_reorder_quantity'   => $result['inventory_reorder_qty'] ?? 0,
                    'reorder_level_day'            => $result['reorder_level_qty'] ?? 0,
                    'max_inventory_level_quantity' => $result['max_inventory_level_qty'] ?? 0,
                    'max_inventory_usage_day'      => $result['max_usage_days'] ?? 0,
                    'remarks'                      => $validated['remarks'] ?? null,
                    'updated_by'                   => Auth::id(),
                ]);
            }

            DB::commit();

            return response()->json([
                'message'   => 'Stock report created successfully.',
                'success'   => true,
                'report_id' => $mainReport->id,
            ]);

        } catch (\Throwable $e) {
            DB::rollBack();

            return response()->json([
                'message' => 'Failed to create stock report.',
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
