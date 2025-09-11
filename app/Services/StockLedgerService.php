<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Models\ProductVariant;

class StockLedgerService
{
    public function recalcProduct(int $productId, ?int $warehouseId = null, ?string $cutoffDate = null)
    {
        $bindings = [
            $productId, $productId, $productId // Reduced bindings for remaining queries
        ];

        $sql = "
        (
            -- Stock Beginning
            SELECT sb.id as child_id, msb.id as parent_id, 'main_stock_beginnings' as parent_table,
                sb.product_id, sb.quantity, sb.unit_price, 0 as vat, 0 as discount, 0 as delivery_fee,
                msb.beginning_date as transaction_date, msb.warehouse_id, 'beginning' as movement_type, sb.created_at
            FROM stock_beginnings sb
            JOIN main_stock_beginnings msb ON msb.id = sb.main_form_id
            WHERE sb.product_id = ? AND sb.deleted_at IS NULL AND msb.deleted_at IS NULL
            AND msb.approval_status = 'Approved'
            " . ($warehouseId ? "AND msb.warehouse_id = $warehouseId" : "") . "

            UNION ALL

            -- Stock In
            SELECT sii.id as child_id, si.id as parent_id, 'stock_ins' as parent_table,
                sii.product_id, sii.quantity, sii.unit_price, sii.vat, sii.discount, sii.delivery_fee,
                si.transaction_date, si.warehouse_id, 'in' as movement_type, sii.created_at
            FROM stock_in_items sii
            JOIN stock_ins si ON si.id = sii.stock_in_id
            WHERE sii.product_id = ? AND sii.deleted_at IS NULL AND si.deleted_at IS NULL
            " . ($warehouseId ? "AND si.warehouse_id = $warehouseId" : "") . "

            UNION ALL

            -- Stock Out (Issues)
            SELECT sii.id as child_id, si.id as parent_id, 'stock_issues' as parent_table,
                sii.product_id, sii.quantity, sii.unit_price, 0 as vat, 0 as discount, 0 as delivery_fee,
                si.transaction_date, si.warehouse_id, 'out' as movement_type, sii.created_at
            FROM stock_issue_items sii
            JOIN stock_issues si ON si.id = sii.stock_issue_id
            WHERE sii.product_id = ? AND sii.deleted_at IS NULL AND si.deleted_at IS NULL
            " . ($warehouseId ? "AND si.warehouse_id = $warehouseId" : "") . "
        ) as all_txn
        ";

        $allStockMovements = DB::table(DB::raw($sql))
            ->orderBy('transaction_date')
            ->orderBy('created_at')
            ->setBindings($bindings)
            ->get();

        $runningQty = 0;
        $runningValue = 0;
        $runningWAP = 0;

        foreach ($allStockMovements as $movement) {
            if ($cutoffDate && $movement->transaction_date > $cutoffDate) continue;

            if (in_array($movement->movement_type, ['beginning', 'in'])) {
                $totalCost = ($movement->quantity * $movement->unit_price)
                            + ($movement->vat ?? 0)
                            + ($movement->delivery_fee ?? 0)
                            - ($movement->discount ?? 0);

                $runningValue += $totalCost;
                $runningQty   += $movement->quantity;
                $runningWAP = $runningQty > 0 ? round($runningValue / $runningQty, 4) : 0;

            } elseif ($movement->movement_type === 'out') {
                $runningQty   -= $movement->quantity;
                $runningValue -= $movement->quantity * $runningWAP;
            }

            $movement->running_qty   = $runningQty;
            $movement->running_value = $runningValue;
            $movement->running_wap   = $runningWAP;
        }
        
        return $allStockMovements;
    }

    /**
     * Get global average price across all warehouses
     */
    public function getGlobalAvgPrice(int $productId, ?string $cutoffDate = null)
    {
        $bindings = [$productId, $productId, $productId]; // Reduced bindings for remaining queries

        $sql = "
        (
            -- Stock Beginning
            SELECT sb.id as child_id, msb.id as parent_id, 'main_stock_beginnings' as parent_table,
                sb.product_id, sb.quantity, sb.unit_price, 0 as vat, 0 as discount, 0 as delivery_fee,
                msb.beginning_date as transaction_date, msb.warehouse_id, 'beginning' as movement_type, sb.created_at
            FROM stock_beginnings sb
            JOIN main_stock_beginnings msb ON msb.id = sb.main_form_id
            WHERE sb.product_id = ? AND sb.deleted_at IS NULL AND msb.deleted_at IS NULL
            AND msb.approval_status = 'Approved'

            UNION ALL

            -- Stock In
            SELECT sii.id as child_id, si.id as parent_id, 'stock_ins' as parent_table,
                sii.product_id, sii.quantity, sii.unit_price, sii.vat, sii.discount, sii.delivery_fee,
                si.transaction_date, si.warehouse_id, 'in' as movement_type, sii.created_at
            FROM stock_in_items sii
            JOIN stock_ins si ON si.id = sii.stock_in_id
            WHERE sii.product_id = ? AND sii.deleted_at IS NULL AND si.deleted_at IS NULL

            UNION ALL

            -- Stock Out (Issues)
            SELECT sii.id as child_id, si.id as parent_id, 'stock_issues' as parent_table,
                sii.product_id, sii.quantity, sii.unit_price, 0 as vat, 0 as discount, 0 as delivery_fee,
                si.transaction_date, si.warehouse_id, 'out' as movement_type, sii.created_at
            FROM stock_issue_items sii
            JOIN stock_issues si ON si.id = sii.stock_issue_id
            WHERE sii.product_id = ? AND sii.deleted_at IS NULL AND si.deleted_at IS NULL
        ) as all_txn
        ";

        $ledgerGlobal = DB::table(DB::raw($sql))
            ->orderBy('transaction_date')
            ->orderBy('created_at')
            ->setBindings($bindings)
            ->get();

        $runningQty = 0;
        $runningValue = 0;
        $runningWAP = 0;

        foreach ($ledgerGlobal as $movement) {
            if ($cutoffDate && $movement->transaction_date > $cutoffDate) continue;

            if (in_array($movement->movement_type, ['beginning', 'in'])) {
                $totalCost = ($movement->quantity * $movement->unit_price)
                            + ($movement->vat ?? 0)
                            + ($movement->delivery_fee ?? 0)
                            - ($movement->discount ?? 0);

                $runningValue += $totalCost;
                $runningQty   += $movement->quantity;
                $runningWAP   = $runningQty > 0 ? $runningValue / $runningQty : 0;

            } elseif ($movement->movement_type === 'out') {
                $runningQty   -= $movement->quantity;
                $runningValue -= $movement->quantity * $runningWAP;
            }
        }

        return $runningQty > 0 ? round($runningWAP, 4) : 0;
    }
}