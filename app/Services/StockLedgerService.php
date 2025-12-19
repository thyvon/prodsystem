<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;
use App\Models\StockLedger;

class StockLedgerService
{

    // New Fomular

    // public function getStockOnHand(int $productId, ?int $warehouseId, string $transactionDate): float
    // {
    //     if ($warehouseId !== null) {
    //         // Filter by specific warehouse
    //         $sql = "
    //             SELECT COALESCE(SUM(quantity), 0) AS stock_on_hand
    //             FROM stock_ledgers
    //             WHERE product_id = ?
    //             AND parent_warehouse = ?
    //             AND transaction_date <= ?
    //             AND transaction_type IN ('Stock_Begin', 'Stock_In', 'Stock_Out')
    //         ";

    //         $result = DB::selectOne($sql, [$productId, $warehouseId, $transactionDate]);
    //     } else {
    //         // Sum across all warehouses
    //         $sql = "
    //             SELECT COALESCE(SUM(quantity), 0) AS stock_on_hand
    //             FROM stock_ledgers
    //             WHERE product_id = ?
    //             AND transaction_date <= ?
    //             AND transaction_type IN ('Stock_Begin', 'Stock_In', 'Stock_Out')
    //         ";

    //         $result = DB::selectOne($sql, [$productId, $transactionDate]);
    //     }

    //     return (float) $result->stock_on_hand;
    // }

    // Old Fomular From Excel

    public function getStockOnHand(int $productId, ?int $warehouseId, string $transactionDate): float
    {
        $prevMonthStart = Carbon::parse($transactionDate)->subMonthNoOverflow()->startOfMonth();
        $prevMonthEnd   = Carbon::parse($transactionDate)->subMonthNoOverflow()->endOfMonth();
        $currentMonthStart = Carbon::parse($transactionDate)->startOfMonth();

        $ledgerQuery = StockLedger::query()
            ->where('product_id', $productId)
            ->when($warehouseId, fn($q) => $q->where('parent_warehouse', $warehouseId))
            ->where(function ($q) use ($prevMonthStart, $prevMonthEnd, $currentMonthStart, $transactionDate) {
                $q->where(function ($q) use ($prevMonthStart, $prevMonthEnd) {
                    // Beginning stock from previous month
                    $q->where('transaction_type', 'Stock_Begin')
                    ->whereBetween('transaction_date', [$prevMonthStart, $prevMonthEnd]);
                })
                ->orWhere(function ($q) use ($currentMonthStart, $transactionDate) {
                    // Stock movements from start of current month to transaction date
                    $q->whereIn('transaction_type', ['Stock_In','Stock_Out'])
                    ->whereBetween('transaction_date', [$currentMonthStart, $transactionDate]);
                });
            })
            ->selectRaw("
                SUM(CASE 
                    WHEN transaction_type='Stock_Begin' THEN quantity 
                    ELSE 0 
                END) AS begin_qty,
                SUM(CASE 
                    WHEN transaction_type='Stock_In' AND transaction_date >= ? THEN quantity 
                    ELSE 0 
                END) AS in_qty,
                SUM(CASE 
                    WHEN transaction_type='Stock_Out' AND transaction_date >= ? THEN quantity 
                    ELSE 0 
                END) AS out_qty
            ", [$currentMonthStart, $currentMonthStart]);

        $result = $ledgerQuery->first();

        $beginQty = (float) ($result->begin_qty ?? 0);
        $inQty    = (float) ($result->in_qty ?? 0);
        $outQty   = (float) ($result->out_qty ?? 0);

        // Final stock
        return $beginQty + $inQty - $outQty;
    }



    public function getAvgPrice(int $productId, ?string $endDate = null): float
    {
        $bindings = [$productId];
        $dateCondition = '';

        if ($endDate) {
            $dateCondition = "AND transaction_date <= ?";
            $bindings[] = $endDate;
        }

        $sql = "
            SELECT
                COALESCE(SUM(CASE WHEN quantity > 0 THEN quantity ELSE 0 END), 0) AS in_qty,
                COALESCE(SUM(CASE WHEN quantity > 0 THEN total_price ELSE 0 END), 0) AS in_total,
                COALESCE(SUM(CASE WHEN quantity < 0 THEN quantity ELSE 0 END), 0) AS out_qty,
                COALESCE(SUM(CASE WHEN quantity < 0 THEN total_price ELSE 0 END), 0) AS out_total
            FROM stock_ledgers
            WHERE product_id = ?
            AND transaction_type IN ('Stock_Begin', 'Stock_In', 'Stock_Out')
            $dateCondition
        ";

        $totals = DB::selectOne($sql, $bindings);

        $balanceQty   = (float) $totals->in_qty + (float) $totals->out_qty;
        $balanceTotal = (float) $totals->in_total + (float) $totals->out_total;

        return $balanceQty > 0 ? round($balanceTotal / $balanceQty, 6) : 0;
    }

}