<?php

namespace App\Services;

use App\Models\StockTransfer;
use Illuminate\Http\Request;
use App\Services\StockLedgerService;
use Illuminate\Support\Facades\Log;

class StockTransferService
{
    protected StockLedgerService $ledgerService;

    public function __construct(StockLedgerService $ledgerService)
    {
        $this->ledgerService = $ledgerService;
    }

    /**
     * Get stock transfers with items including stock and price info
     *
     * @param Request $request
     * @param string|null $cutoffDate Optional cutoff date (use Stock Issue transaction date)
     * @return array
     */
    public function getStockTransfers(Request $request, ?string $cutoffDate = null): array
    {
        // Fetch only approved stock transfers
        $stockTransfers = StockTransfer::where('approval_status', 'Approved')->get();
        $data = $stockTransfers->map(function ($item) use ($cutoffDate) {
            $effectiveCutoff = $cutoffDate ?? $item->transaction_date ?? now()->toDateString();
            $warehouseId = $item->warehouse_id ?? null;

            return [
                'id' => $item->id,
                'reference_no' => $item->reference_no,
                'transaction_date' => $item->transaction_date,
                'warehouse_id' => $item->warehouse_id,
                'destination_name' => $item->warehouse?->name,
                'approval_status' => $item->approval_status,
                'items' => $item->stockTransferItems->map(function ($stockItem) use ($effectiveCutoff, $warehouseId) {
                    // Stock on hand up to cutoff date
                    $stockMovements = $this->ledgerService->recalcProduct($stockItem->product_id, $warehouseId, $effectiveCutoff);
                    $stockOnHand = $stockMovements->last()->running_qty ?? 0;

                    // Average price up to cutoff date
                    $averagePrice = $this->ledgerService->getGlobalAvgPrice($stockItem->product_id, $effectiveCutoff);

                    return [
                        'id' => $stockItem->id,
                        'product_id' => $stockItem->product_id,
                        'item_code' => $stockItem->productVariant?->item_code,
                        'description' => $stockItem->productVariant?->description,
                        'product_name' => $stockItem->productVariant?->product?->name,
                        'quantity' => $stockItem->quantity,
                        'unit_name' => $stockItem->productVariant?->product?->unit?->name,
                        'unit_price' => $averagePrice,
                        'stock_on_hand' => $stockOnHand,
                        'total_price' => round($stockItem->quantity * $averagePrice, 4),
                        'remarks' => $stockItem->remarks,
                        'product_khmer_name' => $stockItem->productVariant?->product?->khmer_name,
                    ];
                }),
            ];
        });

        return ['data' => $data];
    }
}
