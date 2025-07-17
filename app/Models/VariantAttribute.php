<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class VariantAttribute extends Model
{
    use HasFactory;

    protected $table = 'variant_attributes';
    protected $fillable = [
        'name',
        'ordinal',
        'is_active',
    ];

    public function values()
    {
        return $this->hasMany(VariantValue::class);
    }
}
