<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\Eloquent\SoftDeletes;

class StockCountItems extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $table = 'stock_count_items';

    protected $fillable = [
        'stock_count_id',
        'product_id',
        'ending_quantity',
        'unit_price',
        'counted_quantity',
        'remarks',
        'created_by',
        'updated_by',
        'deleted_by',
    ];

    protected $casts = [
    'ending_quantity' => 'float',
    'counted_quantity' => 'float',
    'unit_price' => 'float',
    ];

    public function stockCount()
    {
        return $this->belongsTo(StockCount::class);
    }

    public function product()
    {
        return $this->belongsTo(ProductVariant::class);
    }
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

    // ----------------------------
    // Auto-sync to stock_ledgers
    // ----------------------------
    protected static function booted()
    {
        // CREATE
        static::created(function ($item) {
            $item->load('stockCount'); // ensure relation is loaded

            DB::table('stock_ledgers')->insert([
                'item_id'           => $item->id,
                'transaction_date'  => $item->stockCount->transaction_date,
                'product_id'        => $item->product_id,
                'quantity'          => $item->counted_quantity,
                'unit_price'        => $item->unit_price,
                'total_price'       => round($item->counted_quantity * $item->unit_price, 15),
                'transaction_type'  => 'Stock_Count',
                'parent_reference'  => $item->stockCount->reference_no,
                'parent_warehouse'  => $item->stockCount->warehouse_id,
                'created_by'        => $item->created_by ?? auth()->id(),
                'created_at'        => now(),
                'updated_at'        => now(),
            ]);
        });

        // UPDATE
        static::updated(function ($item) {
            $item->load('stockCount'); // ensure relation is loaded

            // Delete old ledger row(s) for this stock count item
            DB::table('stock_ledgers')
                ->where('transaction_type', 'Stock_Count')
                ->where('parent_reference', $item->stockCount->reference_no)
                ->where('item_id', $item->id)
                ->where('product_id', $item->product_id)
                ->where('quantity', $item->getOriginal('counted_quantity'))
                ->delete();

            // Insert new ledger row with updated values
            DB::table('stock_ledgers')->insert([
                'item_id'           => $item->id,
                'transaction_date'  => $item->stockCount->transaction_date,
                'product_id'        => $item->product_id,
                'quantity'          => $item->counted_quantity,
                'unit_price'        => $item->unit_price,
                'total_price'       => round($item->counted_quantity * $item->unit_price, 15),
                'transaction_type'  => 'Stock_Count',
                'parent_reference'  => $item->stockCount->reference_no,
                'parent_warehouse'  => $item->stockCount->warehouse_id,
                'created_by'        => $item->updated_by ?? $item->created_by ?? auth()->id(),
                'created_at'        => now(),
                'updated_at'        => now(),
            ]);
        });

        // DELETE (soft delete)
        static::deleted(function ($item) {
            $item->load('stockCount'); // ensure relation is loaded

            DB::table('stock_ledgers')
                ->where('transaction_type', 'Stock_Count')
                ->where('parent_reference', $item->stockCount->reference_no)
                ->where('item_id', $item->id)
                ->where('product_id', $item->product_id)
                ->where('quantity', $item->counted_quantity)
                ->delete();
        });

        // RESTORE (soft delete restore)
        static::restored(function ($item) {
            $item->load('stockCount'); // ensure relation is loaded

            DB::table('stock_ledgers')->insert([
                'item_id'           => $item->id,
                'transaction_date'  => $item->stockCount->transaction_date,
                'product_id'        => $item->product_id,
                'quantity'          => $item->counted_quantity,
                'unit_price'        => $item->unit_price,
                'total_price'       => round($item->counted_quantity * $item->unit_price, 15),
                'transaction_type'  => 'Stock_Count',
                'parent_reference'  => $item->stockCount->reference_no,
                'parent_warehouse'  => $item->stockCount->warehouse_id,
                'created_by'        => $item->created_by ?? auth()->id(),
                'created_at'        => now(),
                'updated_at'        => now(),
            ]);
        });
    }
}
