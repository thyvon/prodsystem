<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Invoice extends Model
{
    use HasFactory, SoftDeletes; // Include SoftDeletes if desired

    protected $fillable = [
        'user_id', // Allow mass assignment for user_id
        'supplier_id',
        'total',
    ];

    public function products()
    {
        return $this->belongsToMany(Product::class, 'invoice_product')->withPivot('quantity');
    }
    
    public function supplier()
    {
        return $this->belongsTo(Supplier::class);
    }

    // Optional: Relationship with User
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    // Optional: Calculate total dynamically based on products
    public function calculateTotal()
    {
        return $this->products->sum(function($product) {
            return $product->price * $product->pivot->quantity;
        });
    }

    // Optional: Scope for filtering invoices by user
    public function scopeForUser($query, $userId)
    {
        return $query->where('user_id', $userId);
    }

    // Optional: Scope for filtering invoices within a date range
    public function scopeWithinDateRange($query, $startDate, $endDate)
    {
        return $query->whereBetween('created_at', [$startDate, $endDate]);
    }
}
