<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class PurchaseRequestItem extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $table = 'purchase_request_items';
    protected $fillable = [
        'purchase_request_id',
        'product_id',
        'campus_id',
        'department_id',
        'division_id',
        'budget_code_id',
        'description',
        'currency',
        'exchange_rate',
        'quantity',
        'unit_price',
        'total_price',
        'total_price_usd',
        'purchasing_status',
    ];

    public function purchaseRequest()
    {
        return $this->belongsTo(PurchaseRequest::class, 'purchase_request_id');
    }

    public function product()
    {
        return $this->belongsTo(ProductVariant::class);
    }

    public function campus()
    {
        return $this->belongsTo(Campus::class);
    }

    public function department()
    {
        return $this->belongsTo(Department::class);
    }

    public function division()
    {
        return $this->belongsTo(Division::class);
    }

    // public function budgetCode()
    // {
    //     return $this->belongsTo(BudgetItem::class, 'budget_code_id');
    // }

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
}
