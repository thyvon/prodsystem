<?php

namespace App\Services;

use App\Models\WarehouseProduct;
use App\Models\StockLedger;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class WarehouseStockService
{
    protected $stockLedgerService;

    public function __construct(StockLedgerService $stockLedgerService)
    {
        $this->stockLedgerService = $stockLedgerService;
    }

    /**
     * Get stock report for a specific product in a warehouse
     *
     * @param int $warehouseId
     * @param int $productId
     * @return array|null
     */
    public function getStockReportByProduct(int $warehouseId, int $productId): ?array
    {
        $product = WarehouseProduct::where('warehouse_id', $warehouseId)
            ->where('product_id', $productId)
            ->first();

        if (!$product) {
            return [];
        }

        $now = Carbon::now();
        $threeMonthsAgo = $now->copy()->subMonths(3);
        $sixMonthsAgo = $now->copy()->subMonths(6);

        return $this->calculateProductStock($product, $warehouseId, $threeMonthsAgo, $sixMonthsAgo);
    }

    protected function calculateProductStock(WarehouseProduct $product, int $warehouseId, Carbon $threeMonthsAgo, Carbon $sixMonthsAgo): array
    {
        $stockOutForecastDays = $product->stock_out_forecast_days;
        $targetInvTurnoverDays = $product->target_inv_turnover_days;
        $orderLeadTimeDays = $product->order_leadtime_days;

        $beginningStockQty = $this->stockLedgerService->getStockOnHand(
            $product->product_id,
            $warehouseId,
            now()->toDateString()
        );

        $avgPrice = $this->stockLedgerService->getAvgPrice(
            $product->product_id,
            now()->toDateString()
        );

        // Get monthly total quantities
        $monthlyUsage = StockLedger::select(
                DB::raw('YEAR(transaction_date) as year'),
                DB::raw('MONTH(transaction_date) as month'),
                DB::raw('SUM(ABS(quantity)) as total_qty')
            )
            ->where('product_id', $product->product_id)
            ->where('parent_warehouse', $warehouseId)
            ->where('transaction_date', '>=', $threeMonthsAgo)
            ->where('transaction_type', 'Stock_Out')
            ->groupBy('year', 'month')
            ->get();

        // Filter months where total_qty > 0
        $monthsWithUsage = $monthlyUsage->filter(fn($m) => $m->total_qty > 0);

        // Calculate average only over months with usage
        $avgUsage = $monthsWithUsage->isNotEmpty() 
            ? $monthsWithUsage->avg('total_qty') 
            : 0;

        // If no usage in last 3 months, fallback to last 6 months
        if ($avgUsage == 0) {
            $monthlyUsage6m = StockLedger::select(
                    DB::raw('YEAR(transaction_date) as year'),
                    DB::raw('MONTH(transaction_date) as month'),
                    DB::raw('SUM(ABS(quantity)) as total_qty')
                )
                ->where('product_id', $product->product_id)
                ->where('parent_warehouse', $warehouseId)
                ->where('transaction_date', '>=', $sixMonthsAgo)
                ->where('transaction_type', 'Stock_Out')
                ->groupBy('year', 'month')
                ->get();

            $monthsWithUsage6m = $monthlyUsage6m->filter(fn($m) => $m->total_qty > 0);
            $avgUsage = $monthsWithUsage6m->isNotEmpty() 
                ? $monthsWithUsage6m->avg('total_qty') 
                : 0;
        }


        $avgDailyUse = $avgUsage / 21;
        $orderPlanQty = ceil($avgDailyUse) * ceil($targetInvTurnoverDays);
        // $stockOutForecastQty = $avgDailyUse * $stockOutForecastDays;
        $demandForecastQty = ceil($avgDailyUse) * ceil($stockOutForecastDays);
        $endingStockQty = $beginningStockQty + $orderPlanQty - $demandForecastQty;

        $endingStockCoverDays = ($demandForecastQty > 0)
            ? ($endingStockQty / $demandForecastQty) * 21
            : 0;

        $buffer15DaysQty = $avgUsage / 1.4;
        $orderLeadTimeSafetyDays = $orderLeadTimeDays / 21;
        $safetyStockQty = ceil(($avgUsage * $orderLeadTimeSafetyDays) + $buffer15DaysQty);

        $stockInDays = ($avgUsage > 0)
            ? ceil(($safetyStockQty / $avgUsage) * 21)
            : 0;

        $targetSafetyStockDays = $stockInDays;
        $stockValueUSD = $endingStockQty * $avgPrice;
        $inventoryReorderQty = ceil($avgDailyUse) * $targetInvTurnoverDays;

        $reorderLevelQty = ($avgDailyUse * $orderLeadTimeDays)
            + $safetyStockQty
            + $buffer15DaysQty;

        $maxInventoryLevelQty = $reorderLevelQty + $inventoryReorderQty;

        $maxUsageDays = ($avgDailyUse > 0)
            ? ($maxInventoryLevelQty / $avgDailyUse)
            : 0;

        return array_merge($product->toArray(), [
            'stock_onhand' => round($beginningStockQty, 0),
            'avg_price' => $avgPrice,
            'avg_daily_use_per_day' => round($avgDailyUse, 2),
            'order_plan_qty' => round($orderPlanQty, 0),
            // 'stock_out_forecast_qty' => round($stockOutForecastQty, 0),
            'demand_stock_out_forecast_qty' => round($demandForecastQty, 2),
            'ending_stock_qty' => round($endingStockQty, 2),
            'ending_stock_cover_days' => round($endingStockCoverDays, 2),
            'buffer_15_days_qty' => round($buffer15DaysQty, 2),
            'order_lead_time_ss_days' => round($orderLeadTimeSafetyDays, 2),
            'safety_stock_qty' => round($safetyStockQty, 2),
            'stock_in_days' => round($stockInDays, 2),
            'target_safety_stock_days' => round($targetSafetyStockDays, 2),
            'stock_value_usd' => round($stockValueUSD, 2),
            'inventory_reorder_qty' => round($inventoryReorderQty, 2),
            'reorder_level_qty' => round($reorderLevelQty, 2),
            'max_inventory_level_qty' => round($maxInventoryLevelQty, 2),
            'max_usage_days' => round($maxUsageDays, 2),
            // 'usage3m' => round($usage3m, 2),
            'avg_usage' => round($avgUsage, 2),
        ]);
    }



}
