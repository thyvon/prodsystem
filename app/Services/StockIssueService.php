<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use App\Services\StockLedgerService;

class StockIssueService
{
    protected StockLedgerService $ledgerService;

    public function __construct(StockLedgerService $ledgerService)
    {
        $this->ledgerService = $ledgerService;
    }

public function getStockRequests(?string $cutoffDate = null): array
{
    $cutoff = $cutoffDate ?? now()->toDateString();

    // 1️⃣ Stock Requests
    $stockRequests = DB::table('stock_requests as sr')
        ->leftJoin('warehouses as w', 'w.id', '=', 'sr.warehouse_id')
        ->leftJoin('buildings as b', 'b.id', '=', 'w.building_id')
        ->leftJoin('campus as c', 'c.id', '=', 'b.campus_id')
        ->leftJoin('users as u', 'u.id', '=', 'sr.created_by')
        ->select([
            'sr.id',
            DB::raw("'request' as type"),
            'sr.request_number as reference_no',
            'sr.request_date as transaction_date',
            'sr.warehouse_id as warehouse_from',
            DB::raw('NULL as warehouse_to'),
            'sr.approval_status',
            'w.name as warehouse_name',
            'b.short_name as building_name',
            'c.short_name as warehouse_campus_name',
            'u.name as created_by',
            'sr.updated_by',
        ])
        ->where('sr.approval_status', 'Approved')
        ->whereNull('sr.deleted_at')
        ->get();

    // 2️⃣ Stock Transfers
    $stockTransfers = DB::table('stock_transfers as st')
        ->leftJoin('warehouses as w', 'w.id', '=', 'st.warehouse_id')
        ->leftJoin('warehouses as dw', 'dw.id', '=', 'st.destination_warehouse_id')
        ->select([
            'st.id',
            DB::raw("'transfer' as type"),
            'st.reference_no',
            'st.transaction_date',
            'st.warehouse_id as warehouse_from',
            'st.destination_warehouse_id as warehouse_to',
            'st.approval_status',
            'w.name as warehouse_name',
            'dw.name as warehouse_to_name',
        ])
        ->where('st.approval_status', 'Approved')
        ->whereNull('st.deleted_at')
        ->get();

    // 3️⃣ Stock Request Items
    $requestItems = DB::table('stock_request_items as sri')
        ->leftJoin('product_variants as pv', 'pv.id', '=', 'sri.product_id')
        ->leftJoin('products as p', 'p.id', '=', 'pv.product_id')
        ->leftJoin('unit_of_measures as u', 'u.id', '=', 'p.unit_id')
        ->select([
            'sri.id as id',
            'sri.stock_request_id as transaction_id',
            DB::raw("'request' as type"),
            'sri.product_id',
            'pv.item_code',
            'pv.description',
            'p.name as product_name',
            'p.khmer_name as product_khmer_name',
            'sri.quantity',
            'u.name as unit_name',
            'sri.remarks',
        ])
        ->whereNull('sri.deleted_at')
        ->get();

    // 4️⃣ Stock Transfer Items
    $transferItems = DB::table('stock_transfer_items as sti')
        ->leftJoin('product_variants as pv', 'pv.id', '=', 'sti.product_id')
        ->leftJoin('products as p', 'p.id', '=', 'pv.product_id')
        ->leftJoin('unit_of_measures as u', 'u.id', '=', 'p.unit_id')
        ->select([
            'sti.id as id',
            'sti.stock_transfer_id as transaction_id',
            DB::raw("'transfer' as type"),
            'sti.product_id',
            'pv.item_code',
            'pv.description',
            'p.name as product_name',
            'p.khmer_name as product_khmer_name',
            'sti.quantity',
            'u.name as unit_name',
            'sti.remarks',
        ])
        ->whereNull('sti.deleted_at')
        ->get();

    $allItems = $requestItems->concat($transferItems);

    // 5️⃣ Merge all transactions
    $transactions = $stockRequests->concat($stockTransfers)->sortByDesc('transaction_date')->values();

    // 6️⃣ Attach items to transactions
    $transactions = $transactions->map(function ($tx) use ($allItems, $cutoff) {
        $warehouseId = $tx->warehouse_from;

        $items = $allItems
            ->where('transaction_id', $tx->id)
            ->where('type', $tx->type)
            ->map(function ($item) use ($warehouseId, $cutoff) {
                $stockMovements = $this->ledgerService->recalcProduct($item->product_id, $warehouseId, $cutoff);
                $stockOnHand = $stockMovements->last()->running_qty ?? 0;
                $averagePrice = $this->ledgerService->getGlobalAvgPrice($item->product_id, $cutoff);

                return [
                    'id' => $item->id,
                    'product_id' => $item->product_id,
                    'item_code' => $item->item_code,
                    'description' => $item->description,
                    'product_name' => $item->product_name,
                    'product_khmer_name' => $item->product_khmer_name,
                    'quantity' => $item->quantity,
                    'unit_name' => $item->unit_name,
                    'unit_price' => $averagePrice,
                    'stock_on_hand' => $stockOnHand,
                    'total_price' => round($item->quantity * $averagePrice, 4),
                    'remarks' => $item->remarks,
                ];
            });

        return array_merge((array) $tx, ['items' => $items]);
    });

    return [
        'data' => $transactions,
        'recordsTotal' => $transactions->count(),
        'recordsFiltered' => $transactions->count(),
        'draw' => 1,
    ];
}

}
