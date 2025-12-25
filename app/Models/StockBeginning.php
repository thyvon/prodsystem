<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;

class StockBeginning extends Model
{
    use HasFactory;
    use SoftDeletes;
    protected $table = 'stock_beginnings';
    protected $fillable = [
        'main_form_id',
        'product_id',
        'quantity',
        'unit_price',
        'total_value',
        'remarks',
        'warehouse_id',
        'beginning_date',
        'created_by',
        'updated_by',
        'deleted_by',
    ];

    public function productVariant()
    {
        return $this->belongsTo(ProductVariant::class, 'product_id');
    }

    public function warehouse()
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function mainStockBeginning()
    {
        return $this->belongsTo(MainStockBeginning::class, 'main_form_id');
    }

    // ----------------------------
    // Auto-sync to stock_ledgers
    // ----------------------------
    protected static function booted()
    {
        // CREATE
        static::created(function ($item) {
            $item->load('mainStockBeginning'); // ensure relation is loaded
            DB::table('stock_ledgers')->insert([
                'item_id'           => $item->id,
                'transaction_date'  => $item->mainStockBeginning->beginning_date,
                'product_id'        => $item->product_id,
                'quantity'          => $item->quantity,
                'unit_price'        => $item->unit_price,
                'total_price'       => $item->total_value,
                'transaction_type'  => 'Stock_Begin',
                'parent_reference'  => $item->mainStockBeginning->reference_no,
                'parent_warehouse'  => $item->mainStockBeginning->warehouse_id,
                'created_by'        => $item->created_by ?? auth()->id(),
                'created_at'        => now(),
                'updated_at'        => now(),
            ]);
        });

        // UPDATE
        static::updated(function ($item) {
            $item->load('mainStockBeginning');
            // Remove existing ledger row for this item
            DB::table('stock_ledgers')
                ->where('transaction_type', 'Stock_Begin')
                ->where('item_id', $item->id)
                ->delete();

            // Insert updated ledger row
            DB::table('stock_ledgers')->insert([
                'item_id'           => $item->id,
                'transaction_date'  => $item->mainStockBeginning->beginning_date,
                'product_id'        => $item->product_id,
                'quantity'          => $item->quantity,
                'unit_price'        => $item->unit_price,
                'total_price'       => $item->total_value,
                'transaction_type'  => 'Stock_Begin',
                'parent_reference'  => $item->mainStockBeginning->reference_no,
                'parent_warehouse'  => $item->mainStockBeginning->warehouse_id,
                'created_by'        => $item->updated_by ?? $item->created_by,
                'created_at'        => now(),
                'updated_at'        => now(),
            ]);
        });

        // DELETE (soft delete)
        static::deleted(function ($item) {
            $item->load('mainStockBeginning');
            DB::table('stock_ledgers')
                ->where('transaction_type', 'Stock_Begin')
                ->where('item_id', $item->id)
                ->delete();
        });

        // RESTORE (soft delete restore)
        static::restored(function ($item) {
            $item->load('mainStockBeginning');
            DB::table('stock_ledgers')->insert([
                'item_id'           => $item->id,
                'transaction_date'  => $item->mainStockBeginning->beginning_date,
                'product_id'        => $item->product_id,
                'quantity'          => $item->quantity,
                'unit_price'        => $item->unit_price,
                'total_price'       => $item->total_value,
                'transaction_type'  => 'Stock_Begin',
                'parent_reference'  => $item->mainStockBeginning->reference_no,
                'parent_warehouse'  => $item->mainStockBeginning->warehouse_id,
                'created_by'        => $item->created_by ?? auth()->id(),
                'created_at'        => now(),
                'updated_at'        => now(),
            ]);
        });
    }
}
