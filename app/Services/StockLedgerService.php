<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;
use App\Models\StockLedger;

class StockLedgerService
{

    // public function getStockOnHand(int $productId, int $warehouseId, string $transactionDate): float
    // {
    //     $sql = "
    //         SELECT COALESCE(SUM(quantity), 0) AS stock_on_hand
    //         FROM stock_ledgers
    //         WHERE product_id = ?
    //         AND parent_warehouse = ?
    //         AND transaction_date <= ?
    //         AND transaction_type IN ('Stock_Begin', 'Stock_In', 'Stock_Out')
    //     ";

    //     $result = DB::selectOne($sql, [$productId, $warehouseId, $transactionDate]);

    //     return (float) $result->stock_on_hand;
    // }

    public function getStockOnHand(int $productId, ?int $warehouseId, string $transactionDate): float
    {
        $transactionDate = Carbon::parse($transactionDate);

        // Previous month (Excel EOMONTH -1)
        $prevMonthStart = $transactionDate->copy()
            ->subMonthNoOverflow()
            ->startOfMonth();

        $prevMonthEnd = $transactionDate->copy()
            ->subMonthNoOverflow()
            ->endOfMonth();

        // Current month
        $currentMonthStart = $transactionDate->copy()->startOfMonth();

        if ($warehouseId !== null) {
            $sql = "
                SELECT
                    (
                        -- Beginning stock from Stock_Count (previous month)
                        COALESCE(SUM(CASE
                            WHEN transaction_type = 'Stock_Count'
                                AND transaction_date BETWEEN ? AND ?
                            THEN quantity ELSE 0 END), 0)

                        +

                        -- Current month Stock In
                        COALESCE(SUM(CASE
                            WHEN transaction_type = 'Stock_In'
                                AND transaction_date BETWEEN ? AND ?
                            THEN quantity ELSE 0 END), 0)

                        +

                        -- Current month Stock Out
                        COALESCE(SUM(CASE
                            WHEN transaction_type = 'Stock_Out'
                                AND transaction_date BETWEEN ? AND ?
                            THEN quantity ELSE 0 END), 0)
                    ) AS stock_on_hand
                FROM stock_ledgers
                WHERE product_id = ?
                AND parent_warehouse = ?
            ";

            $params = [
                $prevMonthStart, $prevMonthEnd,       // Stock_Count (prev month)
                $currentMonthStart, $transactionDate, // Stock_In
                $currentMonthStart, $transactionDate, // Stock_Out
                $productId, $warehouseId
            ];
        } else {
            $sql = "
                SELECT
                    (
                        COALESCE(SUM(CASE
                            WHEN transaction_type = 'Stock_Count'
                                AND transaction_date BETWEEN ? AND ?
                            THEN quantity ELSE 0 END), 0)

                        +

                        COALESCE(SUM(CASE
                            WHEN transaction_type = 'Stock_In'
                                AND transaction_date BETWEEN ? AND ?
                            THEN quantity ELSE 0 END), 0)

                        +

                        COALESCE(SUM(CASE
                            WHEN transaction_type = 'Stock_Out'
                                AND transaction_date BETWEEN ? AND ?
                            THEN quantity ELSE 0 END), 0)
                    ) AS stock_on_hand
                FROM stock_ledgers
                WHERE product_id = ?
            ";

            $params = [
                $prevMonthStart, $prevMonthEnd,
                $currentMonthStart, $transactionDate,
                $currentMonthStart, $transactionDate,
                $productId
            ];
        }

        $result = DB::selectOne($sql, $params);

        return (float) ($result->stock_on_hand ?? 0);
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