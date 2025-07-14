<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TocaAmount extends Model
{
    use HasFactory;
    protected $table = 'toca_amount';
    protected $fillable = [
        'min_amount', 'max_amount', 'toca_id', 'is_active'
    ];

    public function tocaPolicy()
    {
        return $this->belongsTo(TocaPolicy::class, 'toca_id');
    }
}
