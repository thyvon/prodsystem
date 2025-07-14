<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Department extends Model
{
    use HasFactory;
    protected $table = 'departments';
    protected $fillable = [
        'name', 'short_name', 'division_id', 'is_active'
    ];

    public function division()
    {
        return $this->belongsTo(Division::class);
    }
}
