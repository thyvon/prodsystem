<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class UnitOfMeasure extends Model
{
    use HasFactory;
    protected $table = 'unit_of_measures';
    protected $fillable = [
        'name', 'khmer_name', 'short_name', 'description', 'operator',
        'conversion_factor', 'parent_unit_id', 'is_active'
    ];

    public function parentUnit()
    {
        return $this->belongsTo(UnitOfMeasure::class, 'parent_unit_id');
    }
    
    public function subUnits()
    {
        return $this->hasMany(UnitOfMeasure::class, 'parent_unit_id');
    }
}
