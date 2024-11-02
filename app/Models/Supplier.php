<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Supplier extends Model
{
    use HasFactory;

    protected $fillable = ['name', 'contact_person', 'email', 'phone'];

    public function products()
    {
        return $this->hasMany(Product::class);
    }

    // Optional: Relationship with invoices
    public function invoices()
    {
        return $this->hasMany(Invoice::class);
    }

    // Optional: Accessor for full contact info
    public function getFullContactAttribute()
    {
        return "{$this->contact_person} ({$this->email}, {$this->phone})";
    }

    // Optional: Model casting
    protected $casts = [
        'phone' => 'string', // Adjust based on needs
    ];

    // Optional: Scope for active suppliers
    public function scopeActive($query)
    {
        return $query->where('active', 1);
    }
}
