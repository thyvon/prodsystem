<?php

namespace App\Exports;

use App\Models\StockCount;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;

class StockCountSessionExport implements FromCollection, WithHeadings
{
    private StockCount $stockCount;

    public function __construct(StockCount $stockCount)
    {
        $this->stockCount = $stockCount;
    }

    public function collection(): Collection
    {
        return $this->stockCount->items->values()->map(function ($item, $index) {
            return [
                'no' => $index + 1,
                'item_code' => $item->product?->item_code ?? '',
                'description' => trim((($item->product?->product?->name) ?? '') . ' ' . (($item->product?->description) ?? '')),
                'unit' => $item->product?->product?->unit?->name ?? '',
                'ending_qty' => (float) ($item->ending_quantity ?? 0),
                'counted_qty' => (float) ($item->counted_quantity ?? 0),
                'variance' => (float) (($item->ending_quantity ?? 0) - ($item->counted_quantity ?? 0)),
                'remarks' => $item->remarks ?? '',
            ];
        });
    }

    public function headings(): array
    {
        return [
            'No',
            'Item Code',
            'Product Description',
            'Unit',
            'Ending Qty',
            'Counted Qty',
            'Variance',
            'Remarks',
        ];
    }

}
