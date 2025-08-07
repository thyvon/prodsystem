<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Position extends Model
{
    use HasFactory;

    protected $table = 'positions';

    protected $fillable = [
        'title',
        'short_title',
        'is_active',
        'departement_id'
    ];

    public function department()
    {
        return $this->belongsTo(Department::class);
    }
}
