<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\WarehouseProduct;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Http\JsonResponse;
use App\Imports\WarehouseProductImport;
use Maatwebsite\Excel\Facades\Excel;
use App\Services\WarehouseStockService;
use Illuminate\Support\Facades\Log;

class WarehouseProductController extends Controller
{

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

    public function getStockReportByProduct(Request $request, WarehouseStockService $warehouseStockService): JsonResponse
    {
        // Validate input
        $validated = $request->validate([
            'warehouse_id' => 'required|exists:warehouses,id',
            'product_id'   => 'required|exists:warehouse_products,product_id',
        ]);

        $warehouseId = $validated['warehouse_id'];
        $productId = $validated['product_id'];

        // Get stock report from the service
        $stockReport = $warehouseStockService->getStockReportByProduct($warehouseId, $productId);

        if (!$stockReport) {
            return response()->json(['message' => 'Product not found in this warehouse'], 404);
        }

        return response()->json([
            'warehouseProductReport' => $stockReport,
        ]);
    }

}
