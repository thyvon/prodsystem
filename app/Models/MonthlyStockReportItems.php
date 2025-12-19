<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MonthlyStockReportItems extends Model
{
    use HasFactory;

    protected $fillable = [
        'report_id',
        'product_id',
        'beginning_quantity',
        'beginning_price',
        'beginning_total',
        'stock_in_quantity',
        'stock_in_total',
        'available_quantity',
        'available_price',
        'available_total',
        'stock_out_quantity',
        'stock_out_total',
        'ending_quantity',
        'ending_total',
        'counted_quantity',
        'variance_quantity',
        'average_price',
    ];

    protected $casts = [
        'beginning_quantity' => 'float',
        'stock_in_quantity'  => 'float',
        'available_quantity' => 'float',
        'stock_out_quantity' => 'float',
        'ending_quantity'    => 'float',
        'counted_quantity'   => 'float',
        'variance_quantity'  => 'float',

        'beginning_price'    => 'float',
        'beginning_total'    => 'float',
        'stock_in_total'     => 'float',
        'available_price'    => 'float',
        'available_total'    => 'float',
        'stock_out_total'    => 'float',
        'ending_total'       => 'float',
        'average_price'      => 'float',
    ];

    /**
     * Relation to main report
     */
    public function report()
    {
        return $this->belongsTo(MonthlyStockReport::class, 'report_id');
    }

    public function productVariant()
    {
        return $this->belongsTo(ProductVariant::class, 'product_id');
    }
}
