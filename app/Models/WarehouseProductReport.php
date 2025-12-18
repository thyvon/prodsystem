<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class WarehouseProductReport extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $table = 'warehouse_stock_report_main';

    protected $fillable = [
        'reference_no',
        'report_date',
        'warehouse_id',
        'approval_status',
        'remarks',
        'created_by',
        'position_id',
        'updated_by',
        'deleted_by',
    ];

    public function items()
    {
        return $this->hasMany(WarehouseProductReportItems::class, 'report_id');
    }

    public function warehouse()
    {
        return $this->belongsTo(Warehouse::class, 'warehouse_id');
    }

    public function creater()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function createrPosition()
    {
        return $this->belongsTo(Position::class, 'position_id');
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
        return $this->morphMany(Approval::class, 'approvable');
    }
}
