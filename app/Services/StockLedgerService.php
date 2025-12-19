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
        $transactionDate      = date('Y-m-d', strtotime($transactionDate));
        $prevMonthStart       = date('Y-m-01', strtotime($transactionDate . ' -1 month'));
        $prevMonthEnd         = date('Y-m-t', strtotime($transactionDate . ' -1 month'));
        $currentMonthStart    = date('Y-m-01', strtotime($transactionDate));

        if ($warehouseId !== null) {
            $sql = "
                SELECT
                    COALESCE(
                        SUM(CASE WHEN transaction_type='Stock_Begin' 
                            AND transaction_date BETWEEN ? AND ? THEN quantity ELSE 0 END), 0
                    ) 
                    + COALESCE(
                        SUM(CASE WHEN transaction_type='Stock_In' 
                            AND transaction_date BETWEEN ? AND ? THEN quantity ELSE 0 END), 0
                    )
                    - COALESCE(
                        SUM(CASE WHEN transaction_type='Stock_Out' 
                            AND transaction_date BETWEEN ? AND ? THEN quantity ELSE 0 END), 0
                    ) AS stock_on_hand
                FROM stock_ledgers
                WHERE product_id = ?
                AND parent_warehouse = ?
            ";

            $params = [
                // Stock_Begin previous month
                $prevMonthStart, $prevMonthEnd,
                // Stock_In current month
                $currentMonthStart, $transactionDate,
                // Stock_Out current month
                $currentMonthStart, $transactionDate,
                // Where clause
                $productId, $warehouseId
            ];
        } else {
            $sql = "
                SELECT
                    COALESCE(
                        SUM(CASE WHEN transaction_type='Stock_Begin' 
                            AND transaction_date BETWEEN ? AND ? THEN quantity ELSE 0 END), 0
                    ) 
                    + COALESCE(
                        SUM(CASE WHEN transaction_type='Stock_In' 
                            AND transaction_date BETWEEN ? AND ? THEN quantity ELSE 0 END), 0
                    )
                    - COALESCE(
                        SUM(CASE WHEN transaction_type='Stock_Out' 
                            AND transaction_date BETWEEN ? AND ? THEN quantity ELSE 0 END), 0
                    ) AS stock_on_hand
                FROM stock_ledgers
                WHERE product_id = ?
            ";

            $params = [
                $prevMonthStart, $prevMonthEnd,    // Stock_Begin previous month
                $currentMonthStart, $transactionDate, // Stock_In current month
                $currentMonthStart, $transactionDate, // Stock_Out current month
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