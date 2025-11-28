<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class StockCount extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $table = 'stock_counts';
    protected $fillable = [
        'transaction_date',
        'reference_no',
        'warehouse_id',
        'remarks',
        'approval_status',
        'created_by',
        'position_id',
        'updated_by',
        'deleted_by',
    ];

    public function items()
    {
        return $this->hasMany(StockCountItems::class);
    }
    public function warehouse()
    {
        return $this->belongsTo(Warehouse::class);
    }
    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
    public function updater()
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
    public function deleter()
    {
        return $this->belongsTo(User::class, 'deleted_by');
    }
    public function approvals()
    {
        return $this->morphMany(Approval::class, 'approvable')->orderBy('ordinal');
    }
}
