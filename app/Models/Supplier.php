<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Supplier extends Model
{
    use HasFactory;

    // Table name (optional if using default 'suppliers')
    protected $table = 'suppliers';

    // Mass assignable fields
    protected $fillable = [
        'name',
        'email',
        'phone',
        'address',
        'description',
        'is_active',
    ];

    // Casts
    protected $casts = [
        'is_active' => 'boolean',
    ];

    /**
     * Relationship: Supplier has many Stock Ins
     */
    public function stockIns()
    {
        return $this->hasMany(StockIn::class);
    }

    /**
     * Scope: Only active suppliers
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', 1);
    }

    /**
     * Accessor: Get supplier full info
     */
    public function getFullInfoAttribute()
    {
        return "{$this->name} ({$this->email}, {$this->phone})";
    }
}
