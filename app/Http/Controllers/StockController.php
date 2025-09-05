<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Services\StockLedgerService;
use App\Models\Warehouse;
use App\Models\ProductVariant;
use App\Models\Product;
use App\Services\ProductService;
use Illuminate\Support\Facades\Log;

class StockController extends Controller
{

    protected $productService;
    protected $ledgerService;

    public function __construct(
        ProductService $productService,
        StockLedgerService $ledgerService
    ) {
        $this->productService = $productService;
        $this->ledgerService = $ledgerService;
    }

    public function stockList()
    {
        $this->authorize('viewAny', Product::class);
        return view('Inventory.Items.index');
    }

    public function stockMovement()
    {
        $this->authorize('viewAny', Product::class);
        return view('Inventory.Items.movement');
    }

    public function getStockManagedVariants(Request $request)
    {
        $this->authorize('viewAny', Product::class);

        $validated = $request->validate([
            'search' => 'nullable|string|max:255',
            'sortColumn' => 'nullable|string',
            'sortDirection' => 'nullable|string|in:asc,desc',
            'limit' => 'nullable|integer|min:1|max:100',
            'draw' => 'nullable|integer',
            'date' => 'nullable|date',
            'page' => 'nullable|integer|min:1',
        ]);

        $search = $validated['search'] ?? null;
        $limit = $validated['limit'] ?? 10;
        $draw = $validated['draw'] ?? 1;
        $page = $validated['page'] ?? 1;
        $date = $validated['date'] ?? now()->toDateString();

        // Fetch variants with product relations
        $variants = ProductVariant::with(['product.category','product.subCategory','product.unit','values.attribute'])
            ->whereHas('product', fn($q) => $q->where('manage_stock', 1))
            ->get();

        $data = collect();

        foreach ($variants as $variant) {
            $detail = $this->productService->getStockDetailByCampus($variant->id, $date);

            $data->push([
                'id' => $variant->id,
                'item_code' => $variant->item_code,
                'estimated_price' => $variant->estimated_price,
                'average_price' => $detail['global_average_price'],
                'stock_by_campus' => $detail['stock_by_campus'],
                'total_stock' => $detail['total_stock'],
                'description' => $variant->description,
                'image' => $variant->image ?: $variant->product->image ?? null,
                'is_active' => (int) $variant->is_active,
                'image_url' => $variant->image 
                    ? asset('storage/' . $variant->image) 
                    : ($variant->product->image ? asset('storage/' . $variant->product->image) : null),
                'product_id' => $variant->product->id ?? null,
                'product_name' => $variant->product->name 
                ? $variant->product->name . ($variant->description ? ' - ' . $variant->description : '')
                : null,
                'product_khmer_name' => $variant->product->khmer_name ?? null,
                'category_name' => $variant->product->category->name ?? null,
                'sub_category_name' => $variant->product->subCategory->name ?? null,
                'unit_name' => $variant->product->unit->name ?? null,
                'created_by' => $variant->product->createdBy ? $variant->product->createdBy->name : null,
                'created_at' => $variant->created_at?->toDateTimeString(),
                'updated_at' => $variant->updated_at?->toDateTimeString(),
            ]);
        }

        // 🔍 Apply search filtering
        if ($search) {
            $searchLower = strtolower($search);
            $data = $data->filter(function ($row) use ($searchLower) {
                return str_contains(strtolower($row['item_code']), $searchLower)
                    || str_contains(strtolower($row['description'] ?? ''), $searchLower)
                    || str_contains(strtolower($row['product_name'] ?? ''), $searchLower)
                    || str_contains(strtolower($row['product_khmer_name'] ?? ''), $searchLower)
                    || str_contains(strtolower($row['category_name'] ?? ''), $searchLower)
                    || str_contains(strtolower($row['sub_category_name'] ?? ''), $searchLower)
                    || str_contains(strtolower($row['unit_name'] ?? ''), $searchLower)
                    || str_contains(strtolower($row['created_by'] ?? ''), $searchLower);
            })->values();
        }

        // 🔃 Sorting
        $sortColumn = $validated['sortColumn'] ?? 'created_at';
        $sortDirection = strtolower($validated['sortDirection'] ?? 'desc');

        if ($data->isNotEmpty() && array_key_exists($sortColumn, $data->first())) {
            $data = $sortDirection === 'asc'
                ? $data->sortBy($sortColumn)->values()
                : $data->sortByDesc($sortColumn)->values();
        }

        // 📄 Manual pagination
        $total = $data->count();
        $paginated = $data->slice(($page - 1) * $limit, $limit)->values();

        return response()->json([
            'data' => $paginated,
            'recordsTotal' => $total,
            'recordsFiltered' => $total,
            'draw' => $draw,
        ]);
    }

    public function getStockMovements(Request $request)
    {
        $this->authorize('viewAny', Product::class);

        $validated = $request->validate([
            'search' => 'nullable|string|max:255',
            'sortColumn' => 'nullable|string',
            'sortDirection' => 'nullable|string|in:asc,desc',
            'limit' => 'nullable|integer|min:1|max:100',
            'draw' => 'nullable|integer',
            'cutoff_date' => 'nullable|date',
            'product_id' => 'nullable|integer|exists:products,id',
            'page' => 'nullable|integer|min:1',
        ]);

        $search = $validated['search'] ?? null;
        $limit = $validated['limit'] ?? 10;
        $draw = $validated['draw'] ?? 1;
        $cutoffDate = $validated['cutoff_date'] ?? null;
        $productId = $validated['product_id'] ?? null;
        $page = $validated['page'] ?? 1;

        // Fetch all variants (with relationships)
        $variants = ProductVariant::with(['product.category','product.subCategory','product.unit'])
            ->when($productId, fn($q) => $q->where('product_id', $productId))
            ->get();

        // Fetch all warehouses
        $warehouses = Warehouse::pluck('name','id');

        $data = collect();

        // Build movements collection
        foreach ($variants as $variant) {
            $movements = $this->ledgerService->recalcProduct($variant->id, null, $cutoffDate);

            foreach ($movements as $movements) {
                $data->push([
                    'variant_id' => $variant->id,
                    'item_code' => $variant->item_code,
                    'product_name' => $variant->product->name 
                                    ? $variant->product->name . ' - ' . ($variant->description ?? '') 
                                    : null,
                    'product_khmer_name' => $variant->product->khmer_name ?? null,
                    'category_name' => $variant->product->category->name ?? null,
                    'sub_category_name' => $variant->product->subCategory->name ?? null,
                    'unit_name' => $variant->product->unit->name ?? null,
                    'movement_date' => $movements->transaction_date,
                    'movement_type' => $movements->movement_type,
                    'warehouse_id' => $movements->warehouse_id,
                    'warehouse_name' => $warehouses[$movements->warehouse_id] ?? 'Unknown',
                    'quantity' => $movements->quantity,
                    'unit_price' => $movements->unit_price,
                    'vat' => $movements->vat,
                    'discount' => $movements->discount,
                    'delivery_fee' => $movements->delivery_fee,
                    'running_qty' => $movements->running_qty,
                    'running_value' => $movements->running_value,
                    'running_wap' => $movements->running_wap,
                ]);
            }
        }

        // Filter search
        if ($search) {
            $searchLower = strtolower($search);
            $data = $data->filter(function($movements) use ($searchLower) {
                return str_contains(strtolower($movements['item_code']), $searchLower)
                    || str_contains(strtolower($movements['product_name'] ?? ''), $searchLower)
                    || str_contains(strtolower($movements['product_khmer_name'] ?? ''), $searchLower)
                    || str_contains(strtolower($movements['category_name'] ?? ''), $searchLower)
                    || str_contains(strtolower($movements['sub_category_name'] ?? ''), $searchLower)
                    || str_contains(strtolower($movements['unit_name'] ?? ''), $searchLower)
                    || str_contains(strtolower($movements['warehouse_name'] ?? ''), $searchLower)
                    || str_contains(strtolower($movements['destination_warehouse_name'] ?? ''), $searchLower);
            })->values();
        }

        // Sort
        $sortColumn = $validated['sortColumn'] ?? 'movement_date';
        $sortDirection = strtolower($validated['sortDirection'] ?? 'asc');

        if ($data->isNotEmpty() && array_key_exists($sortColumn, $data->first())) {
            $data = $sortDirection === 'asc'
                ? $data->sortBy($sortColumn)->values()
                : $data->sortByDesc($sortColumn)->values();
        }

        // Paginate manually
        $total = $data->count();
        $paginated = $data->slice(($page - 1) * $limit, $limit)->values();

        return response()->json([
            'data' => $paginated,
            'recordsTotal' => $total,
            'recordsFiltered' => $total,
            'draw' => $draw,
        ]);
    }


}
