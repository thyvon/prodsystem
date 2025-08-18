<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class StockIssue extends Model
{
    use HasFactory;
    use SoftDeletes;
    protected $table = 'stock_issues';
    protected $fillable = [
        'issue_date',
        'reference_no',
        'warehouse_id',
        'remarks',
        'approval_status',
        'created_by',
        'updated_by',
        'deleted_by',
    ];

    public function warehouse()
    {
        return $this->belongsTo(Warehouse::class, 'warehouse_id');
    }

    public function stockIssueItems()
    {
        return $this->hasMany(StockIssueItem::class);
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
}
