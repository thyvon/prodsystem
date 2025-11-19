<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;

class StockInItem extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'stock_in_id',
        'product_id',
        'quantity',
        'unit_price',
        'vat',
        'discount',
        'delivery_fee',
        'total_price',
        'remarks',
        'updated_by',
        'deleted_by',
    ];

    // Relationships
    public function stockIn()
    {
        return $this->belongsTo(StockIn::class);
    }

    public function product()
    {
        return $this->belongsTo(ProductVariant::class, 'product_id');
    }

    public function updatedByUser()
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function deletedByUser()
    {
        return $this->belongsTo(User::class, 'deleted_by');
    }

    // ----------------------------
    // Auto-sync to stock_ledgers
    // ----------------------------
    protected static function booted()
    {
        // CREATE
        static::created(function ($item) {

            DB::table('stock_ledgers')->insert([
                'item_id'           => $item->id,
                'transaction_date'  => $item->stockIn->transaction_date,
                'product_id'        => $item->product_id,
                'quantity'          => $item->quantity, // positive for stock in
                'unit_price'        => $item->unit_price,
                'total_price'       => $item->total_price,
                'transaction_type'  => 'Stock_In',
                'parent_reference'  => $item->stockIn->reference_no ?? null,
                'parent_warehouse'  => $item->stockIn->warehouse_id ?? null,
                'created_by' => $item->stockIn->created_by ?? auth()->id() ?? 1,
                'created_at'        => now(),
                'updated_at'        => now(),
            ]);
        });


        // UPDATE
        static::updated(function ($item) {
            // Delete old ledger row(s) for this stock in item
            DB::table('stock_ledgers')
                ->where('transaction_type', 'Stock_In')
                ->where('parent_reference', $item->stockIn->reference_no ?? null)
                ->where('item_id', $item->id)
                ->where('product_id', $item->product_id)
                ->where('quantity', $item->getOriginal('quantity'))
                ->where('unit_price', $item->getOriginal('unit_price'))
                ->delete();

            // Insert new ledger row with updated values
            DB::table('stock_ledgers')->insert([
                'item_id'            => $item->id,
                'transaction_date'   => $item->stockIn->transaction_date,
                'product_id'         => $item->product_id,
                'quantity'           => $item->quantity,
                'unit_price'         => $item->unit_price,
                'total_price'        => $item->total_price,
                'transaction_type'   => 'Stock_In',
                'parent_reference'   => $item->stockIn->reference_no ?? null,
                'parent_warehouse'   => $item->stockIn->warehouse_id ?? null,
                'created_by'         => $item->updated_by ?? $item->created_by,
                'created_at'         => now(),
                'updated_at'         => now(),
            ]);
        });

        // DELETE (soft delete or force delete)
        static::deleted(function ($item) {

            DB::table('stock_ledgers')
                ->where('transaction_type', 'Stock_In')
                ->where('parent_reference', $item->stockIn->reference_no ?? null)
                ->where('item_id', $item->id)
                ->where('product_id', $item->product_id)
                ->delete();
        });


        // RESTORE (for soft deletes)
        static::restored(function ($item) {

            DB::table('stock_ledgers')->insert([
                'item_id'           => $item->id,
                'transaction_date'  => $item->stockIn->transaction_date,
                'product_id'        => $item->product_id,
                'quantity'          => $item->quantity,
                'unit_price'        => $item->unit_price,
                'total_price'       => $item->total_price,
                'transaction_type'  => 'Stock_In',
                'parent_reference'  => $item->stockIn->reference_no ?? null,
                'parent_warehouse'  => $item->stockIn->warehouse_id ?? null,
                'created_by' => $item->stockIn->created_by ?? auth()->id() ?? 1,
                'created_at'        => now(),
                'updated_at'        => now(),
            ]);
        });
    }
}
