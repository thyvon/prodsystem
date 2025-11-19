<?php

namespace App\Services;

use App\Models\ProductVariant;
use App\Models\StockLedger;
use Carbon\Carbon;

class StockReportService
{
    public function generate($startDate, $endDate, $warehouseIds = [], $productIds = [], $search = '')
    {
        $query = ProductVariant::query()
            ->with('product.unit')
            ->whereNull('deleted_at')
            ->whereHas('product', fn($q) => $q->where('manage_stock', 1))
            ->when($productIds, fn($q) => $q->whereIn('id', $productIds))
            ->when($search, fn($q) => $q->where(function($sq) use ($search) {
                $sq->where('item_code', 'like', "%{$search}%")
                   ->orWhere('description', 'like', "%{$search}%")
                   ->orWhereHas('product', fn($pq) => $pq->where('name', 'like', "%{$search}%"));
            }));

        $products = $query->get();

        return $products->map(function ($product) use ($warehouseIds, $startDate, $endDate) {
            $row = $this->calculateRow($product, $warehouseIds, $startDate, $endDate);
            return $row;
        })->filter(fn($r) => $r['ending_quantity'] != 0 || $r['stock_in_quantity'] > 0 || $r['stock_out_quantity'] > 0);
    }

    private function calculateRow($product, $warehouseIds, $startDate, $endDate)
    {
        $productId = $product->id;
        $beginQty = $beginTotal = $inQty = $inTotal = $outQty = $outTotal = 0;

        $warehouses = empty($warehouseIds)
            ? StockLedger::where('product_id', $productId)->distinct()->pluck('parent_warehouse')
            : $warehouseIds;

        foreach ($warehouses as $wid) {
            $begin = $this->sumBefore($productId, $wid, $startDate);
            $in    = $this->sumIn($productId, $wid, $startDate, $endDate);
            $out   = $this->sumOut($productId, $wid, $startDate, $endDate);

            $beginQty += $begin['qty'];     $beginTotal += $begin['price'];
            $inQty    += $in['qty'];        $inTotal    += $in['price'];
            $outQty   += $out['qty'];       $outTotal   += $out['price'];
        }

        $endingQty = $beginQty + $inQty + $outQty;
        $avgPrice  = $this->avgPrice($productId, $endDate);
        $endingTotal = $endingQty * $avgPrice;

        return [
            'item_code'          => $product->item_code,
            'description'        => trim(($product->product->name ?? '') . ' ' . ($product->description ?? '')),
            'unit_name'          => $product->product->unit->name ?? '',
            'beginning_quantity' => round($beginQty, 4),
            'beginning_total'    => round($beginTotal, 2),
            'stock_in_quantity'  => round($inQty, 4),
            'stock_in_total'     => round($inTotal, 2),
            'stock_out_quantity' => round(abs($outQty), 4),
            'stock_out_total'    => round(abs($outTotal), 2),
            'ending_quantity'    => round($endingQty, 4),
            'ending_total'       => round($endingTotal, 2),
            'average_price'      => round($avgPrice, 6),
        ];
    }

    private function sumBefore($pid, $wid, $date) { /* reuse your existing getBeginEnd */ }
    private function sumIn($pid, $wid, $start, $end) { /* reuse getStockIn */ }
    private function sumOut($pid, $wid, $start, $end) { /* reuse getStockOut */ }
    private function avgPrice($pid, $date) { /* reuse your avgPrice */ }
}