<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class StockRequest extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $table = 'stock_requests';

    protected $fillable = [
        'request_date',
        'request_number',
        'campus_id',
        'warehouse_id',
        'type',
        'purpose',
        'approval_status',
        'created_by',
        'updated_by',
        'deleted_by',
    ];

    public function campus()
    {
        return $this->belongsTo(Campus::class);
    }
    public function warehouse()
    {
        return $this->belongsTo(Warehouse::class);
    }
    public function stockRequestItems()
    {
        return $this->hasMany(StockRequestItem::class);
    }
    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
    public function updatedBy()
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
    public function deletedBy()
    {
        return $this->belongsTo(User::class, 'deleted_by');
    }

    public function approvals()
    {
        return $this->morphMany(Approval::class, 'approvable')->orderBy('ordinal');
    }
}