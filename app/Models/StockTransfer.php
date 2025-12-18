<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class StockTransfer extends Model
{
    use HasFactory;
    use SoftDeletes;
    protected $table = 'stock_transfers';
    protected $fillable = [
        'transaction_date',
        'reference_no',
        'warehouse_id',
        'destination_warehouse_id',
        'remarks',
        'approval_status',
        'created_by',
        'position_id',
        'updated_by',
        'deleted_by',
    ];
    public function warehouse()
    {
        return $this->belongsTo(Warehouse::class, 'warehouse_id');
    }
    public function destinationWarehouse()
    {
        return $this->belongsTo(Warehouse::class, 'destination_warehouse_id');
    }
    public function stockTransferItems()
    {
        return $this->hasMany(StockTransferItem::class);
    }
    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
    public function creatorPosition()
    {
        return $this->belongsTo(Position::class, 'position_id');
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
        return $this->morphMany(Approval::class, 'approvable');
    }
}
