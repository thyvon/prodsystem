<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class StockTransferItem extends Model
{
    use HasFactory;
    use SoftDeletes;
    protected $table = 'stock_transfer_items';
    protected $fillable = [
        'stock_transfer_id',
        'product_id',
        'quantity',
        'unit_price',
        'remarks',
        'created_by',
        'updated_by',
        'deleted_by',
    ];

    public function productVariant()
    {
        return $this->belongsTo(ProductVariant::class, 'product_id');
    }

    public function stockTransfer()
    {
        return $this->belongsTo(StockTransfer::class);
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
}
