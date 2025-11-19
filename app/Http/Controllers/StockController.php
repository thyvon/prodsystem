<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\StockLedger;
use App\Models\ProductVariant;

class StockController extends Controller
{
    /**
     * Generate stock report with date range and multiple warehouse filter
     */
    public function stockReport(Request $request)
    {
        $startDate = $request->input('start_date'); // format: YYYY-MM-DD
        $endDate = $request->input('end_date');     // format: YYYY-MM-DD
        $warehouseIds = $request->input('warehouse_ids', []); // array of warehouse IDs
        $productIds = $request->input('product_ids', []);     // optional array of product IDs

        $report = [];

        // Get products from ProductVariant excluding soft-deleted ones
        $productsQuery = ProductVariant::query()
            ->whereNull('deleted_at')
            ->whereHas('product', function ($q) {
                $q->where('manage_stock', 1); // only include parent products with manage_stock = 1
            });

        if (!empty($productIds)) {
            $productsQuery->whereIn('id', $productIds);
        }

        $products = $productsQuery->with('product')->get(); // eager load parent

        foreach ($products as $product) {
            $productId = $product->id;

            // Helpers: Beginning, Stock In, Stock Out (filtered by multiple warehouses)
            $begin = $this->getBeginEnd($productId, $warehouseIds, $startDate, $endDate);
            $stockIn = $this->getStockIn($productId, $warehouseIds, $endDate);
            $stockOut = $this->getStockOut($productId, $warehouseIds, $endDate);

            $endingQty = $begin['quantity'] + $stockIn['quantity'] - $stockOut['quantity'];
            $endingTotal = $begin['total_price'] + $stockIn['total_price'] - $stockOut['total_price'];
            $avgPrice = $this->avgPrice($productId); // across all warehouses

            $report[] = [
                'product_id' => $productId,
                'item_code' => $product->item_code,
                'beginning_quantity' => $begin['quantity'],
                'beginning_total' => $begin['total_price'],
                'stock_in_quantity' => $stockIn['quantity'],
                'stock_in_total' => $stockIn['total_price'],
                'stock_out_quantity' => $stockOut['quantity'],
                'stock_out_total' => $stockOut['total_price'],
                'ending_quantity' => $endingQty,
                'ending_total' => $endingTotal,
                'average_price' => $avgPrice,
            ];
        }

        return response()->json([
            'start_date' => $startDate,
            'end_date' => $endDate,
            'warehouse_ids' => $warehouseIds,
            'report' => $report,
        ]);
    }


    /**
     * Get beginning stock for product in multiple warehouses
     */
    private function getBeginEnd($productId, $warehouseIds = [], $startDate = null, $endDate = null)
    {
        $query = StockLedger::where('product_id', $productId)
            ->when(!empty($warehouseIds), fn($q) => $q->whereIn('parent_warehouse', $warehouseIds))
            ->when($startDate, fn($q) => $q->whereDate('transaction_date', '<', $startDate))
            ->when($endDate, fn($q) => $q->whereDate('transaction_date', '<=', $endDate));

        return [
            'quantity' => round($query->sum('quantity'), 6),
            'total_price' => round($query->sum('total_price'), 6),
        ];
    }

    /**
     * Get stock in for product in multiple warehouses
     */
    private function getStockIn($productId, $warehouseIds = [], $endDate = null)
    {
        $query = StockLedger::where('product_id', $productId)
            ->when(!empty($warehouseIds), fn($q) => $q->whereIn('parent_warehouse', $warehouseIds))
            ->when($endDate, fn($q) => $q->whereDate('transaction_date', '<=', $endDate))
            ->where('quantity', '>', 0);

        return [
            'quantity' => round($query->sum('quantity'), 6),
            'total_price' => round($query->sum('total_price'), 6),
        ];
    }

    /**
     * Get stock out for product in multiple warehouses
     */
    private function getStockOut($productId, $warehouseIds = [], $endDate = null)
    {
        $query = StockLedger::where('product_id', $productId)
            ->when(!empty($warehouseIds), fn($q) => $q->whereIn('parent_warehouse', $warehouseIds))
            ->when($endDate, fn($q) => $q->whereDate('transaction_date', '<=', $endDate))
            ->where('quantity', '<', 0);

        return [
            'quantity' => round(abs($query->sum('quantity')), 6),
            'total_price' => round(abs($query->sum('total_price')), 6),
        ];
    }

    /**
     * Average price across all warehouses
     */
    private function avgPrice($productId)
    {
        $beginQty = StockLedger::where('product_id', $productId)->sum('quantity');
        $beginTotal = StockLedger::where('product_id', $productId)->sum('total_price');

        $stockOutQty = StockLedger::where('product_id', $productId)->where('quantity', '<', 0)->sum('quantity');
        $stockOutTotal = StockLedger::where('product_id', $productId)->where('quantity', '<', 0)->sum('total_price');
        $qtyBalance = $beginQty + abs($stockOutQty);
        $priceBalance = $beginTotal + abs($stockOutTotal);

        return $qtyBalance ? round($priceBalance / $qtyBalance, 6) : 0;
    }

    /**
     * Quantity balance for product in multiple warehouses
     */
    private function qtyBalance($productId, $warehouseIds = [])
    {
        $begin = $this->getBeginEnd($productId, $warehouseIds);
        $stockIn = $this->getStockIn($productId, $warehouseIds);
        $stockOut = $this->getStockOut($productId, $warehouseIds);

        return round($begin['quantity'] + $stockIn['quantity'] - $stockOut['quantity'], 6);
    }
}
