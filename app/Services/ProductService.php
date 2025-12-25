<?php

namespace App\Services;

use App\Models\Warehouse;
use App\Models\ProductVariant;
use App\Models\WarehouseProduct;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Services\WarehouseStockService;

class ProductService
{
    protected $stockLedgerService;

    public function __construct(StockLedgerService $stockLedgerService)
    {
        $this->stockLedgerService = $stockLedgerService;
    }

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
            // Ensure warehouseId is int or null
            $warehouseIdInt = $warehouseId !== null ? (int) $warehouseId : null;

            $stockOnHand = $this->stockLedgerService->getStockOnHand(
                $variant->id,
                $warehouseIdInt,
                $transactionDate
            );

            $averagePrice = $this->stockLedgerService->getAvgPrice(
                $variant->id,
                $warehouseIdInt,
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

    public function getAllProducts(array $params)
    {
        $draw = (int) ($params['draw'] ?? 1);
        $warehouseId = $params['warehouse_id'] ?? null; // null = all warehouses
        $transactionDate = $params['cutoff_date'] ?? now()->format('Y-m-d'); // default today
        $searchValue = $params['search']['value'] ?? null;
        $start = (int) ($params['start'] ?? 0);
        $length = (int) ($params['length'] ?? 10); // default page size
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
            ->select('id', 'item_code', 'product_id', 'description', 'estimated_price', 'is_active')
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
        $recordsTotal = ProductVariant::whereNull('deleted_at')->count();

        // Apply ordering, offset, and limit for server-side pagination
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

            $averagePrice = $this->stockLedgerService->getAvgPrice($variant->id, $transactionDate);

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
            'recordsTotal' => $recordsTotal,
            'recordsFiltered' => $recordsFiltered,
            'data' => $data,
        ];
    }

    public function getWarehouseStockProductsWithReport(array $params)
    {
        $warehouseId = $params['warehouse_id'] ?? null;
        $transactionDate = $params['cutoff_date'] ?? now()->format('Y-m-d');

        if (!$warehouseId) {
            return [
                'draw' => (int) ($params['draw'] ?? 1),
                'recordsTotal' => 0,
                'recordsFiltered' => 0,
                'data' => [],
                'message' => 'Warehouse ID is required.'
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
            3 => 'stock_onhand',
            4 => 'avg_price',
            5 => 'ending_stock_qty',
            6 => 'order_plan_qty',
        ];
        $orderColumn = $columns[$orderColumnIndex] ?? 'id';

        $query = WarehouseProduct::with([
                'variant:id,item_code,product_id,description',
                'variant.product:id,name,unit_id,item_code',
                'variant.product.unit:id,name'
            ])
            ->where('warehouse_id', $warehouseId);

            if ($searchValue) {
                $query->where(function ($q) use ($searchValue) {
                    $q->whereHas('variant', fn($q2) => 
                            $q2->where('item_code', 'like', "%{$searchValue}%")
                            ->orWhere('description', 'like', "%{$searchValue}%")
                    )
                    ->orWhereHas('variant.product', fn($q2) => 
                            $q2->where('name', 'like', "%{$searchValue}%")
                            ->orWhere('item_code', 'like', "%{$searchValue}%")
                    );
                });
            }

        $recordsFiltered = $query->count();

        $warehouseProducts = $query->orderBy($orderColumn, $orderDirection)
            ->skip($start)
            ->take($length)
            ->get();

        $warehouseStockService = app(WarehouseStockService::class);

        $data = $warehouseProducts->map(function ($whProduct) use ($warehouseId, $transactionDate, $warehouseStockService) {
            // Use the existing calculation from WarehouseStockService
            return $warehouseStockService->calculateProductStock(
                $whProduct,
                $warehouseId,
                \Carbon\Carbon::parse($transactionDate)->subMonths(3)->startOfMonth(),
                \Carbon\Carbon::parse($transactionDate)->subMonths(6)->startOfMonth()
            );
        });

        return [
            'draw' => $draw,
            'recordsTotal' => WarehouseProduct::where('warehouse_id', $warehouseId)->count(),
            'recordsFiltered' => $recordsFiltered,
            'data' => $data,
        ];
    }

}
