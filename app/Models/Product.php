<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Product extends Model
{
    use HasFactory, SoftDeletes; // Include SoftDeletes if desired

    protected $fillable = ['name', 'description', 'price', 'supplier_id'];

    public function supplier()
    {
        return $this->belongsTo(Supplier::class);
    }

    public function invoices()
    {
        return $this->belongsToMany(Invoice::class, 'invoice_product')->withPivot('quantity');
    }  

    // Optional: Accessor for formatted price
    public function getFormattedPriceAttribute()
    {
        return number_format($this->price, 2);
    }

    // Optional: Scope for filtering by supplier
    public function scopeOfSupplier($query, $supplierId)
    {
        return $query->where('supplier_id', $supplierId);
    }

    // Optional: Scope for filtering by price range
    public function scopePriceBetween($query, $min, $max)
    {
        return $query->whereBetween('price', [$min, $max]);
    }
}
