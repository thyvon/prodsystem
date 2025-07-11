<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Building extends Model
{
    use HasFactory;
    protected $table = 'buildings';
    protected $fillable = [
        'name', 'short_name', 'address', 'is_active', 'campus_id'
    ];
    public function campus()
    {
        return $this->belongsTo(Campus::class, 'campus_id');
    }

}
