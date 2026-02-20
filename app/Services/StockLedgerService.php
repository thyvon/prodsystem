<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;
use App\Models\StockLedger;

class StockLedgerService
{

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
    //             AND transaction_type IN ('Stock_Count', 'Stock_In', 'Stock_Out')
    //         ";

    //         $result = DB::selectOne($sql, [$productId, $warehouseId, $transactionDate]);
    //     } else {
    //         // Sum across all warehouses
    //         $sql = "
    //             SELECT COALESCE(SUM(quantity), 0) AS stock_on_hand
    //             FROM stock_ledgers
    //             WHERE product_id = ?
    //             AND transaction_date <= ?
    //             AND transaction_type IN ('Stock_Count', 'Stock_In', 'Stock_Out')
    //         ";

    //         $result = DB::selectOne($sql, [$productId, $transactionDate]);
    //     }

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

    public function getStockOnHandBulk(array $productIds, ?array $warehouseIds, string $transactionDate)
    {
        $productIds = array_values(array_filter($productIds));
        if (empty($productIds)) {
            return collect();
        }

        $transactionDate = Carbon::parse($transactionDate);
        $prevMonthStart = $transactionDate->copy()->subMonthNoOverflow()->startOfMonth()->format('Y-m-d');
        $prevMonthEnd = $transactionDate->copy()->subMonthNoOverflow()->endOfMonth()->format('Y-m-d');
        $currentMonthStart = $transactionDate->copy()->startOfMonth()->format('Y-m-d');
        $currentDate = $transactionDate->format('Y-m-d');

        $warehouseIds = is_array($warehouseIds) ? array_values(array_filter($warehouseIds)) : null;

        return DB::table('stock_ledgers')
            ->select('product_id', 'parent_warehouse')
            ->selectRaw(
                "
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
                ) AS stock
                ",
                [
                    $prevMonthStart, $prevMonthEnd,
                    $currentMonthStart, $currentDate,
                    $currentMonthStart, $currentDate,
                ]
            )
            ->whereIn('product_id', $productIds)
            ->when(!empty($warehouseIds), fn($q) => $q->whereIn('parent_warehouse', $warehouseIds))
            ->groupBy('product_id', 'parent_warehouse')
            ->get()
            ->groupBy('product_id')
            ->map(fn($rows) => $rows->keyBy('parent_warehouse'));
    }

    // public function getAvgPrice(int $productId, ?string $endDate = null): float
    // {
    //     $bindings = [$productId];
    //     $dateCondition = '';

    //     if ($endDate) {
    //         $dateCondition = "AND transaction_date <= ?";
    //         $bindings[] = $endDate;
    //     }

    //     $sql = "
    //         SELECT
    //             COALESCE(SUM(CASE WHEN quantity > 0 THEN quantity ELSE 0 END), 0) AS in_qty,
    //             COALESCE(SUM(CASE WHEN quantity > 0 THEN total_price ELSE 0 END), 0) AS in_total,
    //             COALESCE(SUM(CASE WHEN quantity < 0 THEN quantity ELSE 0 END), 0) AS out_qty,
    //             COALESCE(SUM(CASE WHEN quantity < 0 THEN total_price ELSE 0 END), 0) AS out_total
    //         FROM stock_ledgers
    //         WHERE product_id = ?
    //         AND transaction_type IN ('Stock_Count', 'Stock_In', 'Stock_Out')
    //         $dateCondition
    //     ";

    //     $totals = DB::selectOne($sql, $bindings);

    //     $balanceQty   = (float) $totals->in_qty + (float) $totals->out_qty;
    //     $balanceTotal = (float) $totals->in_total + (float) $totals->out_total;

    //     return $balanceQty > 0 ? round($balanceTotal / $balanceQty, 6) : 0;
    // }


    public function getAvgPrice(int $productId, ?int $warehouseId, string $transactionDate): float
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
                    -- Beginning (prev month) qty & amount
                    COALESCE(SUM(CASE WHEN transaction_type = 'Stock_Count' AND transaction_date BETWEEN ? AND ? THEN CASE WHEN quantity > 0 THEN quantity ELSE 0 END ELSE 0 END), 0) AS begin_qty,
                    COALESCE(SUM(CASE WHEN transaction_type = 'Stock_Count' AND transaction_date BETWEEN ? AND ? THEN CASE WHEN quantity > 0 THEN total_price ELSE 0 END ELSE 0 END), 0) AS begin_total,

                    -- Current month Stock_In qty & amount
                    COALESCE(SUM(CASE WHEN transaction_type = 'Stock_In' AND transaction_date BETWEEN ? AND ? THEN CASE WHEN quantity > 0 THEN quantity ELSE 0 END ELSE 0 END), 0) AS in_qty,
                    COALESCE(SUM(CASE WHEN transaction_type = 'Stock_In' AND transaction_date BETWEEN ? AND ? THEN CASE WHEN quantity > 0 THEN total_price ELSE 0 END ELSE 0 END), 0) AS in_total
                FROM stock_ledgers
                WHERE product_id = ?
                AND parent_warehouse = ?
            ";

            $params = [
                $prevMonthStart, $prevMonthEnd, // Stock_Count qty
                $prevMonthStart, $prevMonthEnd, // Stock_Count total

                $currentMonthStart, $transactionDate, // Stock_In qty
                $currentMonthStart, $transactionDate, // Stock_In total

                $productId, $warehouseId
            ];
        } else {
            $sql = "
                SELECT
                    COALESCE(SUM(CASE WHEN transaction_type = 'Stock_Count' AND transaction_date BETWEEN ? AND ? THEN CASE WHEN quantity > 0 THEN quantity ELSE 0 END ELSE 0 END), 0) AS begin_qty,
                    COALESCE(SUM(CASE WHEN transaction_type = 'Stock_Count' AND transaction_date BETWEEN ? AND ? THEN CASE WHEN quantity > 0 THEN total_price ELSE 0 END ELSE 0 END), 0) AS begin_total,

                    COALESCE(SUM(CASE WHEN transaction_type = 'Stock_In' AND transaction_date BETWEEN ? AND ? THEN CASE WHEN quantity > 0 THEN quantity ELSE 0 END ELSE 0 END), 0) AS in_qty,
                    COALESCE(SUM(CASE WHEN transaction_type = 'Stock_In' AND transaction_date BETWEEN ? AND ? THEN CASE WHEN quantity > 0 THEN total_price ELSE 0 END ELSE 0 END), 0) AS in_total
                FROM stock_ledgers
                WHERE product_id = ?
            ";

            $params = [
                $prevMonthStart, $prevMonthEnd, // Stock_Count qty
                $prevMonthStart, $prevMonthEnd, // Stock_Count total

                $currentMonthStart, $transactionDate, // Stock_In qty
                $currentMonthStart, $transactionDate, // Stock_In total

                $productId
            ];
        }

        $totals = DB::selectOne($sql, $params);

        $beginQty = (float) ($totals->begin_qty ?? 0);
        $beginTotal = (float) ($totals->begin_total ?? 0);
        $inQty = (float) ($totals->in_qty ?? 0);
        $inTotal = (float) ($totals->in_total ?? 0);

        $totalQty = $beginQty + $inQty;
        $totalAmount = $beginTotal + $inTotal;

        return $totalQty > 0 ? round($totalAmount / $totalQty, 15) : 0;
    }

}
