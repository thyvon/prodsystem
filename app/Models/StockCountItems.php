<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class StockCountItems extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $table = 'stock_count_items';

    protected $fillable = [
        'stock_count_id',
        'product_id',
        'ending_quantity',
        'counted_quantity',
        'remarks',
        'created_by',
        'updated_by',
        'deleted_by',
    ];

    public function stockCount()
    {
        return $this->belongsTo(StockCount::class);
    }

    public function product()
    {
        return $this->belongsTo(ProductVariant::class);
    }
    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    } 
    public function updater()
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
    public function deleter()
    {
        return $this->belongsTo(User::class, 'deleted_by');
    }
}
