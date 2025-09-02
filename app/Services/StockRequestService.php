<?php

namespace App\Services;

use App\Models\StockRequest;
use Illuminate\Http\Request;
use App\Services\StockLedgerService;

class StockRequestService
{
    private const ALLOWED_SORT_COLUMNS = [
        'id', 'request_code', 'approval_status', 'created_at', 'updated_at'
    ];

    private const DEFAULT_SORT_COLUMN = 'id';
    private const DEFAULT_SORT_DIRECTION = 'desc';
    private const DEFAULT_LIMIT = 10;
    private const MAX_LIMIT = 100;

    protected StockLedgerService $ledgerService;

    public function __construct(StockLedgerService $ledgerService)
    {
        $this->ledgerService = $ledgerService;
    }

    /**
     * Get stock requests with items and stock/price info for a stock issue
     *
     * @param Request $request
     * @param string|null $cutoffDate Optional cutoff date (use Stock Issue transaction date)
     * @return array
     */
    public function getStockRequests(Request $request, ?string $cutoffDate = null)
    {
        $validated = $request->validate([
            'search' => 'nullable|string|max:255',
            'sortColumn' => 'nullable|string|in:' . implode(',', self::ALLOWED_SORT_COLUMNS),
            'sortDirection' => 'nullable|string|in:asc,desc',
            'limit' => 'nullable|integer|min:1|max:' . self::MAX_LIMIT,
            'page' => 'nullable|integer|min:1',
            'draw' => 'nullable|integer',
        ]);

        $query = StockRequest::with([
            'warehouse.building.campus',
            'campus',
            'stockRequestItems.productVariant.product.unit',
            'createdBy',
            'updatedBy',
        ])
        ->search($validated['search'] ?? null)
        ->orderBy(
            $validated['sortColumn'] ?? self::DEFAULT_SORT_COLUMN, 
            $validated['sortDirection'] ?? self::DEFAULT_SORT_DIRECTION
        );

        $stockRequests = $query->paginate(
            $validated['limit'] ?? self::DEFAULT_LIMIT,
            ['*'],
            'page',
            $validated['page'] ?? 1
        );

        $data = $stockRequests->getCollection()->map(function ($item) use ($cutoffDate) {
            // Use the cutoff date passed from stock issue, or fallback to stock request's transaction date
            $effectiveCutoff = $cutoffDate ?? $item->transaction_date ?? now()->toDateString();
            $warehouseId = $item->warehouse_id ?? null;

            return [
                'id' => $item->id,
                'request_number' => $item->request_number,
                'request_date' => $item->request_date,
                'warehouse_name' => $item->warehouse?->name,
                'warehouse_campus_name' => $item->warehouse?->building?->campus?->short_name,
                'user_campus_name' => $item->campus?->short_name,
                'building_name' => $item->warehouse?->building?->short_name,
                'quantity' => round($item->stockRequestItems->sum('quantity'), 4),
                'total_price' => round($item->stockRequestItems->sum('total_price'), 4),
                'created_at' => $item->created_at?->toDateTimeString(),
                'updated_at' => $item->updated_at?->toDateTimeString(),
                'created_by' => $item->createdBy?->name ?? 'System',
                'updated_by' => $item->updatedBy?->name ?? 'System',
                'approval_status' => $item->approval_status,
                'items' => $item->stockRequestItems->map(function ($sb) use ($effectiveCutoff, $warehouseId) {
                    $productId = $sb->product_id;

                    // Calculate stock on hand up to the cutoff date
                    $stockMovements = $this->ledgerService->recalcProduct($productId, $warehouseId, $effectiveCutoff);
                    $stockOnHand = $stockMovements->last()->running_qty ?? 0;

                    // Calculate average price up to the cutoff date
                    $averagePrice = $this->ledgerService->getGlobalAvgPrice($productId, $effectiveCutoff);

                    return [
                        'id' => $sb->id,
                        'product_id' => $productId,
                        'item_code' => $sb->productVariant?->item_code,
                        'description' => $sb->productVariant?->description,
                        'product_name' => $sb->productVariant?->product?->name,
                        'quantity' => $sb->quantity,
                        'unit_name' => $sb->productVariant?->product?->unit?->name,
                        'unit_price' => $averagePrice,
                        'stock_on_hand' => $stockOnHand,
                        'total_price' => round($sb->quantity * $averagePrice, 4),
                        'remarks' => $sb->remarks,
                        'product_name' => $sb->productVariant?->product?->name,
                        'product_khmer_name' => $sb->productVariant?->product?->khmer_name,
                    ];
                }),
            ];
        });

        return [
            'data' => $data,
            'recordsTotal' => $stockRequests->total(),
            'recordsFiltered' => $stockRequests->total(),
            'draw' => (int) ($validated['draw'] ?? 1),
        ];
    }
}
