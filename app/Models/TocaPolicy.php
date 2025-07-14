<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TocaPolicy extends Model
{
    use HasFactory;
    protected $table = 'toca_policies';
    protected $fillable = [
        'name', 'short_name', 'is_active'
    ];

    public function tocaAmounts()
    {
        return $this->hasMany(TocaAmount::class, 'toca_id');
    }
}
