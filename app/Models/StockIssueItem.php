<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class StockIssueItem extends Model
{
    use HasFactory;
    use SoftDeletes;
    protected $table = 'stock_issue_items';
    protected $fillable = [
        'stock_issue_id',
        'stock_request_item_id',
        'product_id',
        'quantity',
        'unit_price',
        'total_price',
        'remarks',
        'campus_id',
        'department_id',
        'updated_by',
        'deleted_by',
    ];

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

    public function stockRequestItem()
    {
        return $this->belongsTo(StockRequestItem::class, 'stock_request_item_id');
    }

    public function productVariant()
    {
        return $this->belongsTo(ProductVariant::class, 'product_id');
    }

    public function stockIssue()
    {
        return $this->belongsTo(StockIssue::class, 'stock_issue_id');
    }

    public function campus()
    {
        return $this->belongsTo(Campus::class, 'campus_id');
    }

    public function department()
    {
        return $this->belongsTo(Department::class, 'department_id');
    }
}
