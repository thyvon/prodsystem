<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class StockLedger extends Model
{
    use HasFactory;

    protected $table = 'stock_ledgers';

    protected $fillable = [
        'item_id',
        'transaction_date',
        'product_id',
        'quantity',
        'unit_price',
        'total_price',
        'transaction_type',
        'parent_reference',
        'parent_warehouse',
        'created_by',
    ];

    // ----------------------------
    // Relationships
    // ----------------------------

    // Product
    public function product()
    {
        return $this->belongsTo(ProductVariant::class, 'product_id');
    }

    // StockInItem (for Stock In)
    public function stockInItem()
    {
        return $this->belongsTo(StockInItem::class, 'item_id');
    }

    // StockIssueItem (for Stock Out)
    public function stockIssueItem()
    {
        return $this->belongsTo(StockIssueItem::class, 'item_id');
    }

    // Warehouse
    public function warehouse()
    {
        return $this->belongsTo(Warehouse::class, 'parent_warehouse');
    }

    // Created by User
    public function createdByUser()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
