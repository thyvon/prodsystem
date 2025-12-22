<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('stock_ledgers', function (Blueprint $table) {

            // 🔥 Fast re-sync (DELETE / rebuild by document)
            $table->index(
                ['transaction_type', 'parent_reference'],
                'idx_stock_ledgers_tx_ref'
            );

            // 🔥 Fast stock-on-hand & reports
            $table->index(
                ['product_id', 'parent_warehouse', 'transaction_date'],
                'idx_stock_ledgers_stock_calc'
            );

            // 🔹 Useful for item-based operations
            $table->index('item_id');
        });
    }

    public function down(): void
    {
        Schema::table('stock_ledgers', function (Blueprint $table) {
            $table->dropIndex('idx_stock_ledgers_tx_ref');
            $table->dropIndex('idx_stock_ledgers_stock_calc');
            $table->dropIndex(['item_id']);
        });
    }
};
