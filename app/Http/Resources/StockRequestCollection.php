<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\ResourceCollection;

class StockRequestCollection extends ResourceCollection
{
    public function toArray($request)
    {
        return $this->collection->map(fn($item) => [
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
            'items' => $item->stockRequestItems->map(fn($sb) => [
                'id' => $sb->id,
                'product_id' => $sb->product_id,
                'department_id' => $sb->department_id,
                'campus_id' => $sb->campus_id,
                'item_code' => $sb->productVariant?->item_code,
                'quantity' => $sb->quantity,
                'average_price' => $sb->average_price,
                'total_price' => $sb->total_price,
                'remarks' => $sb->remarks,
                'product_name' => $sb->productVariant?->product?->name,
                'product_khmer_name' => $sb->productVariant?->product?->khmer_name,
                'unit_name' => $sb->productVariant?->product?->unit?->name,
            ]),
        ]);
    }
}