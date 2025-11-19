<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;

class StockIssueItem extends Model
{
    use HasFactory, SoftDeletes;

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

    // ----------------------------
    // Relationships
    // ----------------------------
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

    // ----------------------------
    // Auto-sync to stock_ledgers (Stock Out)
    // ----------------------------
    protected static function booted()
    {
        // CREATE
        static::created(function ($item) {
            DB::table('stock_ledgers')->insert([
                'item_id'            => $item->id,
                'transaction_date'  => $item->stockIssue->issue_date,
                'product_id'       => $item->product_id,
                'quantity'         => $item->quantity * -1,
                'unit_price'       => $item->unit_price,
                'total_price'      => $item->total_price * -1,
                'transaction_type' => 'Stock_Out',
                'parent_reference' => $item->stockIssue->reference_no ?? null,
                'parent_warehouse' => $item->stockIssue->warehouse_id ?? null,
                'created_by'       => $item->updated_by ?? $item->created_by,
                'created_at'       => now(),
                'updated_at'       => now(),
            ]);
        });

        // UPDATE
        static::updated(function ($item) {
            // Delete old ledger row(s) for this item
            DB::table('stock_ledgers')
                ->where('transaction_type', 'Stock_Out')
                ->where('parent_reference', $item->stockIssue->reference_no ?? null)
                ->where('item_id', $item->id)
                ->where('product_id', $item->product_id)
                ->where('quantity', $item->getOriginal('quantity') * -1)
                ->where('unit_price', $item->getOriginal('unit_price'))
                ->delete();

            // Insert new ledger row with updated values
            DB::table('stock_ledgers')->insert([
                'item_id'            => $item->id,
                'transaction_date'  => $item->stockIssue->issue_date,
                'product_id'       => $item->product_id,
                'quantity'         => $item->quantity * -1,
                'unit_price'       => $item->unit_price,
                'total_price'      => $item->total_price * -1,
                'transaction_type' => 'Stock_Out',
                'parent_reference' => $item->stockIssue->reference_no ?? null,
                'parent_warehouse' => $item->stockIssue->warehouse_id ?? null,
                'created_by'       => $item->updated_by ?? $item->created_by,
                'created_at'       => now(),
                'updated_at'       => now(),
            ]);
        });

        // DELETE
        static::deleted(function ($item) {
            DB::table('stock_ledgers')
                ->where('transaction_type', 'Stock_Out')
                ->where('parent_reference', $item->stockIssue->reference_no ?? null)
                ->where('item_id', $item->id)
                ->where('product_id', $item->product_id)
                ->where('quantity', $item->quantity * -1)
                ->where('unit_price', $item->unit_price)
                ->delete();
        });

        // RESTORE
        static::restored(function ($item) {
            DB::table('stock_ledgers')->insert([
                'item_id'            => $item->id,
                'transaction_date'  => $item->stockIssue->issue_date,
                'product_id'       => $item->product_id,
                'quantity'         => $item->quantity * -1,
                'unit_price'       => $item->unit_price,
                'total_price'      => $item->total_price * -1,
                'transaction_type' => 'Stock_Out',
                'parent_reference' => $item->stockIssue->reference_no ?? null,
                'parent_warehouse' => $item->stockIssue->warehouse_id ?? null,
                'created_by'       => $item->updated_by ?? $item->created_by,
                'created_at'       => now(),
                'updated_at'       => now(),
            ]);
        });
    }
}
