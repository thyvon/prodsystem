<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class PurchaseRequestItem extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $table = 'purchase_request_items';
    protected $fillable = [
        'purchase_request_id',
        'product_id',
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

    public function campuses(): BelongsToMany
    {
        return $this->belongsToMany(Campus::class, 'purchase_item_campuses')
            ->withPivot('total_usd')
            ->withTimestamps();
    }

    public function departments(): BelongsToMany
    {
        return $this->belongsToMany(Department::class, 'purchase_item_departments')
            ->withPivot('total_usd')
            ->withTimestamps();
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
