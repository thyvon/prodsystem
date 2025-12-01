<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class MainStockBeginning extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $table = 'main_stock_beginnings';
    protected $fillable = [
        'warehouse_id',
        'reference_no',
        'beginning_date',
        'created_by',
        'position_id',
        'updated_by',
        'deleted_by',
        'approval_status'
    ];

    public function warehouse()
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function items()
    {
        return $this->hasMany(StockBeginning::class, 'main_form_id');
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
        return $this->morphMany(Approval::class, 'approvable')->orderBy('ordinal');
    }
}
