<?php

namespace App\Services;

use App\Models\ProductVariant;
use Illuminate\Http\Request;

class ProductService
{
    protected $ledgerService;

    public function __construct(StockLedgerService $ledgerService)
    {
        $this->ledgerService = $ledgerService;
    }

    public function getStockManagedVariants(Request $request): array
    {
        $validated = $request->validate([
            'search' => 'nullable|string|max:255',
            'sortColumn' => 'nullable|string|in:item_code,description,product_name,product_khmer_name,created_at,updated_at,is_active,created_by',
            'sortDirection' => 'nullable|string|in:asc,desc',
            'limit' => 'nullable|integer|min:1|max:100',
            'draw' => 'nullable|integer',
            'warehouse_id' => 'nullable|integer', // Optional warehouse filter
            'date' => 'nullable|date',           // Optional cut-off date
        ]);

        $query = ProductVariant::with(['product.category', 'product.subCategory', 'product.unit', 'values.attribute'])
            ->whereHas('product', function ($q) use ($validated, $request) {
                $q->where('manage_stock', 1);

                if ($search = $validated['search'] ?? $request->input('search')) {
                    $q->where(function ($q2) use ($search) {
                        $q2->where('name', 'like', "%{$search}%")
                           ->orWhere('khmer_name', 'like', "%{$search}%")
                           ->orWhere('description', 'like', "%{$search}%")
                           ->orWhere('item_code', 'like', "%{$search}%");
                    });
                }
            });

        // Sorting
        $allowedSortColumns = ['item_code', 'created_at', 'updated_at', 'is_active'];
        $sortColumn = $validated['sortColumn'] ?? $request->input('sortColumn', 'created_at');
        $sortDirection = $validated['sortDirection'] ?? $request->input('sortDirection', 'desc');

        $sortColumn = in_array($sortColumn, $allowedSortColumns) ? $sortColumn : 'created_at';
        $sortDirection = in_array(strtolower($sortDirection), ['asc','desc']) ? $sortDirection : 'desc';

        $query->orderBy($sortColumn, $sortDirection);

        // Pagination
        $limit = max(1, min(100, (int) ($validated['limit'] ?? $request->input('limit', 10))));
        $variants = $query->paginate($limit);

        $warehouseId = $validated['warehouse_id'] ?? null;
        $date = $validated['date'] ?? now()->toDateString();

        // Transform data and calculate stock & average price
        $data = $variants->getCollection()->map(function ($variant) use ($warehouseId, $date) {

        $runningQty = $warehouseId 
            ? optional($this->ledgerService->recalcProduct($variant->id, $warehouseId, $date)->last())->running_qty ?? 0
            : null;

        // Global average price across all warehouses up to request date
        $avgPrice = $this->ledgerService->getGlobalAvgPrice($variant->id, $date);

            return [
                'id' => $variant->id,
                'item_code' => $variant->item_code,
                'estimated_price' => $variant->estimated_price,
                'average_price' => $avgPrice,
                'stock_on_hand' => $runningQty,
                'description' => $variant->description,
                'image' => $variant->image ?: $variant->product->image ?? null,
                'is_active' => (int) $variant->is_active,
                'image_url' => $variant->image ? asset('storage/' . $variant->image) : ($variant->product->image ? asset('storage/' . $variant->product->image) : null),
                'product_id' => $variant->product->id ?? null,
                'product_name' => $variant->product->name ?? null,
                'product_khmer_name' => $variant->product->khmer_name ?? null,
                'category_name' => $variant->product->category->name ?? null,
                'sub_category_name' => $variant->product->subCategory->name ?? null,
                'unit_name' => $variant->product->unit->name ?? null,
                'created_by' => $variant->product->createdBy ? $variant->product->createdBy->name : null,
                'created_at' => $variant->created_at?->toDateTimeString(),
                'updated_at' => $variant->updated_at?->toDateTimeString(),
            ];
        });

        return [
            'data' => $data->all(),
            'recordsTotal' => $variants->total(),
            'recordsFiltered' => $variants->total(),
            'draw' => (int) ($validated['draw'] ?? $request->input('draw', 1)),
        ];
    }
}
