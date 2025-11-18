<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class StockInItem extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'stock_in_id',
        'product_id',
        'quantity',
        'unit_price',
        'vat',
        'discount',
        'delivery_fee',
        'total_price',
        'remarks',
        'updated_by',
        'deleted_by',
    ];

    // Relationships
    public function stockIn()
    {
        return $this->belongsTo(StockIn::class);
    }

    public function product()
    {
        return $this->belongsTo(ProductVariant::class, 'product_id');
    }

    public function updatedByUser()
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function deletedByUser()
    {
        return $this->belongsTo(User::class, 'deleted_by');
    }
}
