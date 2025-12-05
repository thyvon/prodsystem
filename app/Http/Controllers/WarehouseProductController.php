<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\WarehouseProduct;
use Illuminate\Support\Facades\DB;

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
}
