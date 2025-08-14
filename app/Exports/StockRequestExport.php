<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Illuminate\Database\Eloquent\Builder;

class StockRequestExport implements FromQuery, WithHeadings, WithMapping
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
            'Request Number',
            'Request Date',
            'Warehouse Name',
            'Warehouse Campus',
            'Warehouse Building',
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

    public function map($stockRequest): array
    {
        $rows = [];
        foreach ($stockRequest->stockRequestItems as $item) {
            $rows[] = [
                $stockRequest->request_number ?? null,
                $stockRequest->request_date ?? null,
                $stockRequest->warehouse->name ?? null,
                $stockRequest->warehouse->building->campus->short_name ?? null,
                $stockRequest->warehouse->building->short_name ?? null,
                $stockRequest->createdBy->name ?? 'System',
                $stockRequest->created_at?->toDateTimeString(),
                $stockRequest->updated_at?->toDateTimeString(),
                $item->productVariant->item_code ?? null,
                $item->productVariant->product->name ?? null,
                $item->productVariant->product->khmer_name ?? null,
                $item->productVariant->product->category->name ?? null,
                $item->productVariant->product->subCategory->name ?? null,
                $item->productVariant->product->unit->name ?? null,
                $item->quantity,
                $item->average_price,
                $item->total_price,
                $item->remarks,
            ];
        }
        return $rows;
    }
}
