<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Division extends Model
{
    use HasFactory;
    protected $table = 'divisions';
    protected $fillable = [
        'name', 'short_name', 'is_active'
    ];

    public function departments()
    {
        return $this->hasMany(Department::class, 'division_id');
    }
}
