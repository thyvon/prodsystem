<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class VariantValue extends Model
{
    use HasFactory;

    protected $table = 'variant_values';
    protected $fillable = [
        'variant_attribute_id',
        'value',
        'is_active',
    ];

    public function attribute()
    {
        return $this->belongsTo(VariantAttribute::class, 'variant_attribute_id');
    }
}
