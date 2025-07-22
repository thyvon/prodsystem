<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class StockBeginning extends Model
{
    use HasFactory;
    use SoftDeletes;
    protected $table = 'stock_beginnings';
    protected $fillable = [
        'product_id',
        'quantity',
        'unit_price',
        'total_value',
        'remarks',
        'warehouse_id',
        'beginning_date',
        'created_by',
        'updated_by',
        'deleted_by',
    ];

    public function productVariant()
    {
        return $this->belongsTo(ProductVariant::class, 'product_id');
    }

    public function warehouse()
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function mainStockBeginning()
    {
        return $this->belongsTo(MainStockBeginning::class, 'main_form_id');
    }

}
