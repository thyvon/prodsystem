<?php

namespace App\Services;

use App\Models\Warehouse;
use App\Models\ProductVariant;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ProductService
{
    protected $stockLedgerService;

    public function __construct(StockLedgerService $stockLedgerService)
    {
        $this->stockLedgerService = $stockLedgerService;
    }

    /**
     * Get stock managed product variants with mapped data including product name and unit name.
     */
    // public function getStockManagedVariants(Request $request): array
    // {
    //     $validated = $request->validate([
    //         'limit' => 'nullable|integer|min:1|max:100',
    //         'search.value' => 'nullable|string|max:255',
    //     ]);

    //     $search = $validated['search']['value'] ?? null;
    //     $limit = max(1, min(100, (int) ($validated['limit'] ?? 10)));

    //     // Join product_variants → products → unit_or_measures
    //     $query = DB::table('product_variants as pv')
    //         ->join('products as p', 'pv.product_id', '=', 'p.id')
    //         ->leftJoin('unit_of_measures as uom', 'p.unit_id', '=', 'uom.id')
    //         ->leftJoin('main_categories as pc', 'p.category_id', '=', 'pc.id')
    //         ->leftJoin('sub_categories as sc', 'p.sub_category_id', '=', 'sc.id')
    //         ->select([
    //             'pv.id',
    //             'pv.item_code',
    //             'pv.description',
    //             'pv.image',
    //             'pv.estimated_price',
    //             'pv.is_active',
    //             'pv.created_at',
    //             'pv.updated_at',
    //             'p.name as product_name',   // ✅ product name
    //             'uom.name as unit_name',    // ✅ unit name
    //             'pc.name as category_name',
    //             'sc.name as sub_category_name',
    //         ])
    //         ->where('pv.is_active', 1)
    //         ->where('pv.deleted_at', null)
    //         ->orderBy('pv.item_code', 'asc');

    //     // Apply search on item_code, description, or product name
    //     if ($search) {
    //         $query->where(function ($q) use ($search) {
    //             $q->where('pv.item_code', 'like', "%{$search}%")
    //               ->orWhere('pv.description', 'like', "%{$search}%")
    //               ->orWhere('p.name', 'like', "%{$search}%");
    //         });
    //     }

    //     // Get paginated results
    //     $variants = $query->paginate($limit);

    //     // Load warehouses for stock calculation
    //     $warehouses = Warehouse::select('id', 'name')->get();

    //     // Map data with stock by warehouse and global average price
    //     $data = collect($variants->items())->map(function ($variant) use ($warehouses) {
    //         $globalAvgPrice = $this->ledgerService->getGlobalAvgPrice($variant->id);

    //         $stockByCampus = [];
    //         $totalStock = 0;

    //         foreach ($warehouses as $warehouse) {
    //             $movements = $this->ledgerService->recalcProduct($variant->id, $warehouse->id);
    //             $last = $movements->last();
    //             $stockOnHand = $last->running_qty ?? 0;

    //             $stockByCampus[] = [
    //                 'warehouse_id' => $warehouse->id,
    //                 'warehouse_name' => $warehouse->name,
    //                 'stock_on_hand' => $stockOnHand,
    //                 'average_price' => $globalAvgPrice,
    //                 'total_cost' => round($stockOnHand * $globalAvgPrice, 2),
    //             ];

    //             $totalStock += $stockOnHand;
    //         }

    //         return [
    //             'id' => $variant->id,
    //             'item_code' => $variant->item_code,
    //             'description' => $variant->product_name . ' - ' . $variant-> description,
    //             'estimated_price' => $variant->estimated_price,
    //             'unit_name' => $variant->unit_name,
    //             'category_name' => $variant->category_name,
    //             'sub_category_name' => $variant->sub_category_name,
    //             'image' => $variant->image,       // ✅ included
    //             'is_active' => (int) $variant->is_active,
    //             'created_at' => $variant->created_at,
    //             'updated_at' => $variant->updated_at,
    //             'stock_on_hand' => $totalStock,
    //             'average_price' => $globalAvgPrice,
    //             'stock_by_campus' => $stockByCampus,
    //         ];
    //     });

    //     return [
    //         'data' => $data->all(),
    //         'recordsTotal' => $variants->total(),
    //         'recordsFiltered' => $variants->total(),
    //         'draw' => (int) ($request->input('draw', 1)),
    //     ];
    // }

    public function getStockProducts(array $params)
    {
        $warehouseId = $params['warehouse_id'] ?? null;
        $transactionDate = $params['cutoff_date'] ?? null;

        if (!$warehouseId || !$transactionDate) {
            return [
                'draw' => (int) ($params['draw'] ?? 1),
                'recordsTotal' => 0,
                'recordsFiltered' => 0,
                'data' => [],
                'message' => 'Warehouse ID and transaction date are required.'
            ];
        }

        $draw = (int) ($params['draw'] ?? 1);
        $start = (int) ($params['start'] ?? 0);
        $length = (int) ($params['length'] ?? 10);
        $searchValue = $params['search']['value'] ?? null;
        $orderColumnIndex = $params['order'][0]['column'] ?? 0;
        $orderDirection = $params['order'][0]['dir'] ?? 'asc';

        $columns = [
            0 => 'id',
            1 => 'item_code',
            2 => 'description',
            3 => 'estimated_price',
            4 => 'is_active',
            5 => 'created_at',
            6 => 'updated_at',
        ];
        $orderColumn = $columns[$orderColumnIndex] ?? 'id';

        $query = ProductVariant::with('product')
        ->select('id', 'item_code','product_id', 'description', 'estimated_price', 'is_active',)
        ->orderBy('item_code', 'asc')
        ->whereNull('deleted_at');

        if ($searchValue) {
            $query->where(function ($q) use ($searchValue) {
                $q->where('item_code', 'like', "%{$searchValue}%")
                ->orWhere('description', 'like', "%{$searchValue}%")
                ->orWhereHas('product', function ($q2) use ($searchValue) {
                    $q2->where('name', 'like', "%{$searchValue}%");
                });
            });
        }

        $recordsFiltered = $query->count();

        $variants = $query->orderBy($orderColumn, $orderDirection)
                        ->skip($start)
                        ->take($length)
                        ->get();

        $data = $variants->map(function ($variant) use ($warehouseId, $transactionDate) {
            $stockOnHand = $this->stockLedgerService->getStockOnHand(
                $variant->id,
                $warehouseId,
                $transactionDate
            );

            $averagePrice = $this->stockLedgerService->getAvgPrice(
                $variant->id,
                $transactionDate
            );

            return [
                'id' => $variant->id,
                'item_code' => $variant->item_code,
                'description' => ($variant->product->name ?? '') . ' ' . $variant->description,
                'unit_name' => $variant->product->unit->name ?? '',
                'estimated_price' => number_format($variant->estimated_price, 2),
                'stock_on_hand' => $stockOnHand,
                'average_price' => $averagePrice,
            ];
        });

        return [
            'draw' => $draw,
            'recordsTotal' => ProductVariant::whereNull('deleted_at')->count(),
            'recordsFiltered' => $recordsFiltered,
            'data' => $data,
        ];
    }

}
