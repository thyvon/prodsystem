<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('warehouse_stock_report_items', function (Blueprint $table) {
            $table->integer('reorder_level_qty')->default(0)->after('inventory_reorder_quantity');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('warehouse_stock_report_items', function (Blueprint $table) {
            $table->dropColumn('reorder_level_qty');
        });
    }
};
