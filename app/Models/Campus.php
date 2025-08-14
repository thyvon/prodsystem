<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Campus extends Model
{
    use HasFactory;

    protected $table = 'campus';
    protected $fillable = [
        'code', 'short_name', 'name', 'address', 'is_active'
    ];
    public function buildings()
    {
        return $this->hasMany(Building::class, 'campus_id');
    }

    public function stockRequests()
    {
        return $this->hasMany(StockRequest::class);
    }
}
