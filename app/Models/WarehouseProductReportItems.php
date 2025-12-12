<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class WarehouseProductReportItems extends Model
{
    use HasFactory;
    use SoftDeletes;
    protected $table = 'warehouse_stock_report_items';

    protected $fillable = [
        'report_id',
        'product_id',
        'warehouse_product_id',
        'unit_price',
        'avg_6_month_usage',
        'last_month_usage',
        'stock_on_hand',
        'order_plan_quantity',
        'demand_forecast_quantity',
        'ending_stock_cover_day',
        'target_safety_stock_day',
        'stock_value',
        'inventory_reorder_quantity',
        'reorder_level_day',
        'max_inventory_level_quantity',
        'max_inventory_usage_day',
        'remarks',
        'updated_by',
        'deleted_by',
    ];

    protected $casts = [
        'unit_price' => 'float',
        'avg_6_month_usage' => 'float',
        'last_month_usage' => 'float',
        'stock_on_hand' => 'float',
        'order_plan_quantity' => 'float',
        'demand_forecast_quantity' => 'float',
        'ending_stock_cover_day' => 'float',
        'target_safety_stock_day' => 'float',
        'stock_value' => 'float',
        'inventory_reorder_quantity' => 'float',
        'reorder_level_day' => 'float',
        'max_inventory_level_quantity' => 'float',
        'max_inventory_usage_day' => 'float',
    ];


    public function product()
    {
        return $this->belongsTo(ProductVariant::class, 'product_id');
    }

    public function warehouseProduct()
    {
        return $this->belongsTo(WarehouseProduct::class, 'warehouse_product_id');
    }

    public function updater()
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function deleter()
    {
        return $this->belongsTo(User::class, 'deleted_by');
    }

    public function report()
    {
        return $this->belongsTo(WarehouseProductReport::class, 'report_id');
    }
}
