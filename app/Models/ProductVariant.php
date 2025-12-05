<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use \Illuminate\Database\Eloquent\SoftDeletes;

class ProductVariant extends Model
{
    use HasFactory;
    use SoftDeletes;
    protected $table = 'product_variants';
    protected $fillable = [
        'product_id',
        'item_code',
        'description',
        'estimated_price',
        'average_price',
        'image',
        'is_active',
        'updated_by',
        'deleted_by',
    ];

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function values()
    {
        return $this->belongsToMany(VariantValue::class, 'product_variant_value');
    }

    public function updatedBy()
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function deletedBy()
    {
        return $this->belongsTo(User::class, 'deleted_by');
    }

    public function stockLedgers()
    {
        return $this->hasMany(StockLedger::class, 'product_id');
    }

    public function warehouseProducts()
    {
        return $this->hasMany(WarehouseProduct::class, 'product_id');
    }
}
