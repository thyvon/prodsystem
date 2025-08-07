<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Warehouse extends Model
{
    use HasFactory;
    use SoftDeletes;
    protected $table = 'warehouses';
    protected $fillable = [
        'code',
        'name',
        'khmer_name',
        'address',
        'address_khmer',
        'description',
        'building_id',
        'is_active',
        'created_by',
        'updated_by',
        'deleted_by',
    ];

    public function building()
    {
        return $this->belongsTo(Building::class);
    }

    public function stockBeginnings()
    {
        return $this->hasMany(MainStockBeginning::class);
    }
}