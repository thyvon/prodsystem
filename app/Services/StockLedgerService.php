<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Models\ProductVariant;

class StockLedgerService
{
    /**
     * Recalculate stock ledger for a specific product in a warehouse
     */
    public function recalcProduct(int $productId, int $warehouseId, ?string $cutoffDate = null)
    {
        $bindings = [
            $productId, $warehouseId, // Stock Beginning
            $productId, $warehouseId, // Stock In
            $productId, $warehouseId, // Transfer In
            $productId, $warehouseId, // Transfer Out
            $productId, $warehouseId  // Stock Out
        ];

        $sql = "
        (
            -- Stock Beginning
            SELECT sb.id as child_id, msb.id as parent_id, 'main_stock_beginnings' as parent_table,
                   sb.product_id, sb.quantity, sb.unit_price, 0 as vat, 0 as discount, 0 as delivery_fee,
                   msb.beginning_date as transaction_date, msb.warehouse_id, 'beginning' as movement_type, sb.created_at
            FROM stock_beginnings sb
            JOIN main_stock_beginnings msb ON msb.id = sb.main_form_id
            WHERE sb.product_id = ? AND msb.warehouse_id = ?

            UNION ALL

            -- Stock In
            SELECT sii.id as child_id, si.id as parent_id, 'stock_ins' as parent_table,
                   sii.product_id, sii.quantity, sii.unit_price, sii.vat, sii.discount, sii.delivery_fee,
                   si.transaction_date, si.warehouse_id, 'in' as movement_type, sii.created_at
            FROM stock_in_items sii
            JOIN stock_ins si ON si.id = sii.stock_in_id
            WHERE sii.product_id = ? AND si.warehouse_id = ?

            UNION ALL

            -- Transfer In
            SELECT sti.id as child_id, st.id as parent_id, 'stock_transfers' as parent_table,
                   sti.product_id, sti.quantity, sti.unit_price, 0 as vat, 0 as discount, 0 as delivery_fee,
                   st.transaction_date, st.destination_warehouse_id as warehouse_id,
                   'transfer_in' as movement_type, sti.created_at
            FROM stock_transfer_items sti
            JOIN stock_transfers st ON st.id = sti.stock_transfer_id
            WHERE sti.product_id = ? AND st.destination_warehouse_id = ?

            UNION ALL

            -- Transfer Out
            SELECT sti.id as child_id, st.id as parent_id, 'stock_transfers' as parent_table,
                   sti.product_id, sti.quantity, sti.unit_price, 0 as vat, 0 as discount, 0 as delivery_fee,
                   st.transaction_date, st.warehouse_id as warehouse_id,
                   'transfer_out' as movement_type, sti.created_at
            FROM stock_transfer_items sti
            JOIN stock_transfers st ON st.id = sti.stock_transfer_id
            WHERE sti.product_id = ? AND st.warehouse_id = ?

            UNION ALL

            -- Stock Out (Issues)
            SELECT sii.id as child_id, si.id as parent_id, 'stock_issues' as parent_table,
                   sii.product_id, sii.quantity, sii.unit_price, 0 as vat, 0 as discount, 0 as delivery_fee,
                   si.transaction_date, si.warehouse_id, 'out' as movement_type, sii.created_at
            FROM stock_issue_items sii
            JOIN stock_issues si ON si.id = sii.stock_issue_id
            WHERE sii.product_id = ? AND si.warehouse_id = ?
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

            if (in_array($movement->movement_type, ['beginning','in','transfer_in'])) {
                $totalCost = ($movement->quantity * $movement->unit_price)
                             + ($movement->vat ?? 0)
                             + ($movement->delivery_fee ?? 0)
                             - ($movement->discount ?? 0);

                $runningValue += $totalCost;
                $runningQty   += $movement->quantity;
                $runningWAP   = $runningQty > 0 ? $runningValue / $runningQty : 0;

            } elseif (in_array($movement->movement_type, ['out','transfer_out'])) {
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
        $bindings = [$productId, $productId, $productId, $productId, $productId];

        $sql = "
        (
            -- Stock Beginning
            SELECT sb.id as child_id, msb.id as parent_id, 'main_stock_beginnings' as parent_table,
                   sb.product_id, sb.quantity, sb.unit_price, 0 as vat, 0 as discount, 0 as delivery_fee,
                   msb.beginning_date as transaction_date, msb.warehouse_id, 'beginning' as movement_type, sb.created_at
            FROM stock_beginnings sb
            JOIN main_stock_beginnings msb ON msb.id = sb.main_form_id
            WHERE sb.product_id = ?

            UNION ALL

            -- Stock In
            SELECT sii.id as child_id, si.id as parent_id, 'stock_ins' as parent_table,
                   sii.product_id, sii.quantity, sii.unit_price, sii.vat, sii.discount, sii.delivery_fee,
                   si.transaction_date, si.warehouse_id, 'in' as movement_type, sii.created_at
            FROM stock_in_items sii
            JOIN stock_ins si ON si.id = sii.stock_in_id
            WHERE sii.product_id = ?

            UNION ALL

            -- Transfer In
            SELECT sti.id as child_id, st.id as parent_id, 'stock_transfers' as parent_table,
                   sti.product_id, sti.quantity, sti.unit_price, 0 as vat, 0 as discount, 0 as delivery_fee,
                   st.transaction_date, st.destination_warehouse_id as warehouse_id,
                   'transfer_in' as movement_type, sti.created_at
            FROM stock_transfer_items sti
            JOIN stock_transfers st ON st.id = sti.stock_transfer_id
            WHERE sti.product_id = ?

            UNION ALL

            -- Transfer Out
            SELECT sti.id as child_id, st.id as parent_id, 'stock_transfers' as parent_table,
                   sti.product_id, sti.quantity, sti.unit_price, 0 as vat, 0 as discount, 0 as delivery_fee,
                   st.transaction_date, st.warehouse_id,
                   'transfer_out' as movement_type, sti.created_at
            FROM stock_transfer_items sti
            JOIN stock_transfers st ON st.id = sti.stock_transfer_id
            WHERE sti.product_id = ?

            UNION ALL

            -- Stock Out
            SELECT sii.id as child_id, si.id as parent_id, 'stock_issues' as parent_table,
                   sii.product_id, sii.quantity, sii.unit_price, 0 as vat, 0 as discount, 0 as delivery_fee,
                   si.transaction_date, si.warehouse_id, 'out' as movement_type, sii.created_at
            FROM stock_issue_items sii
            JOIN stock_issues si ON si.id = sii.stock_issue_id
            WHERE sii.product_id = ?
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

            if (in_array($movement->movement_type, ['beginning','in','transfer_in'])) {
                $totalCost = ($movement->quantity * $movement->unit_price)
                             + ($movement->vat ?? 0)
                             + ($movement->delivery_fee ?? 0)
                             - ($movement->discount ?? 0);

                $runningValue += $totalCost;
                $runningQty   += $movement->quantity;
                $runningWAP   = $runningQty > 0 ? $runningValue / $runningQty : 0;

            } elseif (in_array($movement->movement_type, ['out','transfer_out'])) {
                $runningQty   -= $movement->quantity;
                $runningValue -= $movement->quantity * $runningWAP;
            }
        }

        return round($runningWAP, 2);
    }
    public function getStockOnHandByWarehouse(?int $productId = null, ?string $cutoffDate = null)
    {
        $warehouses = DB::table('warehouses')->pluck('id');
        $stock = [];

        foreach ($warehouses as $whId) {
            if ($productId) {
                $movements = $this->recalcProduct($productId, $whId, $cutoffDate);

                if ($movements->count() > 0) {
                    $last = $movements->last();
                    $stock[] = [
                        'warehouse_id' => $whId,
                        'product_id'   => $productId,
                        'qty'          => $last->running_qty,
                        'value'        => $last->running_value,
                        'avg_price'    => $last->running_wap,
                    ];
                }
            } else {
                // get distinct product_ids in this warehouse
                $productIds = DB::table('product_variants')
                    ->where('product_variants.product.manage_stock', 1)
                    ->join('products','products.id','=','product_variants.product_id')
                    ->pluck('product_variants.product_id')
                    ->unique();

                foreach ($productIds as $pid) {
                    $movements = $this->recalcProduct($pid, $whId, $cutoffDate);

                    if ($movements->count() > 0) {
                        $last = $movements->last();
                        $stock[] = [
                            'warehouse_id' => $whId,
                            'product_id'   => $pid,
                            'qty'          => $last->running_qty,
                            'value'        => $last->running_value,
                            'avg_price'    => $last->running_wap,
                        ];
                    }
                }
            }
        }

        return $stock;
    }

}
