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
    // Auto-sync to stock_ledger
    // ----------------------------
    protected static function booted()
    {
        // Create
        static::created(function ($item) {
            DB::table('stock_ledger')->insert([
                'product_id' => $item->product_id,
                'quantity' => $item->quantity, // positive for stock in
                'unit_price' => $item->unit_price,
                'total_price' => $item->total_price,
                'transaction_type' => 'Stock In',
                'parent_reference' => $item->stockIn->reference_no ?? null,
                'parent_warehouse' => $item->stockIn->warehouse_id ?? null,
                'parent_department' => null,
                'created_by' => $item->updated_by,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        });

        // Update
        static::updated(function ($item) {
            DB::table('stock_ledger')
                ->where('transaction_type', 'Stock In')
                ->where('parent_reference', $item->stockIn->reference_no ?? null)
                ->where('product_id', $item->product_id)
                ->update([
                    'quantity' => $item->quantity,
                    'unit_price' => $item->unit_price,
                    'total_price' => $item->total_price,
                    'updated_at' => now(),
                ]);
        });

        // Delete
        static::deleted(function ($item) {
            DB::table('stock_ledger')
                ->where('transaction_type', 'Stock In')
                ->where('parent_reference', $item->stockIn->reference_no ?? null)
                ->where('product_id', $item->product_id)
                ->delete();
        });

        // Restore (for soft deletes)
        static::restored(function ($item) {
            DB::table('stock_ledger')->insert([
                'product_id' => $item->product_id,
                'quantity' => $item->quantity,
                'unit_price' => $item->unit_price,
                'total_price' => $item->total_price,
                'transaction_type' => 'Stock In',
                'parent_reference' => $item->stockIn->reference_no ?? null,
                'parent_warehouse' => $item->stockIn->warehouse_id ?? null,
                'parent_department' => null,
                'created_by' => $item->updated_by,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        });
    }
}
