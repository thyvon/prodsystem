<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Illuminate\Database\Eloquent\Builder;

class StockBeginningsExport implements FromQuery, WithHeadings, WithMapping
{
    protected $query;

    public function __construct(Builder $query)
    {
        $this->query = $query;
    }

    public function query()
    {
        return $this->query;
    }

    public function headings(): array
    {
        return [
            'Reference No',
            'Beginning Date',
            'Warehouse Name',
            'Campus Name',
            'Building Name',
            'Created By',
            'Created At',
            'Updated At',
            'Item Code',
            'Product Name',
            'Product Khmer Name',
            'Category Name',
            'Sub Category Name',
            'Unit Name',
            'Quantity',
            'Unit Price',
            'Total Value',
            'Remarks',
        ];
    }

    public function map($mainStockBeginning): array
    {
        $rows = [];
        foreach ($mainStockBeginning->stockBeginnings as $stockBeginning) {
            $rows[] = [
                $mainStockBeginning->reference_no,
                $mainStockBeginning->beginning_date ?? null,
                $mainStockBeginning->warehouse->name ?? null,
                $mainStockBeginning->warehouse->building->campus->short_name ?? null,
                $mainStockBeginning->warehouse->building->short_name ?? null,
                $mainStockBeginning->createdBy->name ?? 'System',
                $mainStockBeginning->created_at?->toDateTimeString(),
                $mainStockBeginning->updated_at?->toDateTimeString(),
                $stockBeginning->productVariant->item_code ?? null,
                $stockBeginning->productVariant->product->name ?? null,
                $stockBeginning->productVariant->product->khmer_name ?? null,
                $stockBeginning->productVariant->product->category->name ?? null,
                $stockBeginning->productVariant->product->subCategory->name ?? null,
                $stockBeginning->productVariant->product->unit->name ?? null,
                $stockBeginning->quantity,
                $stockBeginning->unit_price,
                $stockBeginning->total_value,
                $stockBeginning->remarks,
            ];
        }
        return $rows;
    }
}
