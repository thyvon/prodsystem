<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class WarehouseProduct extends Model
{
    use HasFactory;

    // ✅ Allow mass assignment
    protected $fillable = [
        'product_id',
        'warehouse_id',
        'alert_quantity',
        'order_leadtime',
        'is_active',
    ];

    // ✅ Relation to ProductVariant
    public function variant()
    {
        return $this->belongsTo(ProductVariant::class, 'product_id');
    }

    // ✅ Relation to Warehouse
    public function warehouse()
    {
        return $this->belongsTo(Warehouse::class, 'warehouse_id');
    }
}
