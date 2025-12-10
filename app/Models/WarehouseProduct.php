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
        'order_leadtime_days',
        'stock_out_forecast_days',
        'target_inv_turnover_days',
        'is_active',
    ];

    protected $casts = [
        'alert_quantity' => 'float', // or 'float' if needed
        'is_active' => 'integer',
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
