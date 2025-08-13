<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class StockRequestItem extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $table = 'stock_request_items';
    protected $fillable = [
        'stock_request_id',
        'product_id',
        'quantity',
        'average_price',
        'total_price',
        'remarks',
        'created_by',
        'updated_by',
        'deleted_by',
    ];

    public function stockRequest()
    {
        return $this->belongsTo(StockRequest::class, 'stock_request_id');
    }
    public function product()
    {
        return $this->belongsTo(Product::class, 'product_id');
    }
    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
    public function updatedBy()
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
    public function deletedBy()
    {
        return $this->belongsTo(User::class, 'deleted_by');
    }
    public function productVariant()
    {
        return $this->belongsTo(ProductVariant::class, 'product_id');
    }
}