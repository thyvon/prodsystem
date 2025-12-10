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
        Schema::create('warehouse_stock_reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('warehouse_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();

            // Quantities & values as decimal
            $table->decimal('avg_daily_use_per_day', 16, 4)->default(0);
            $table->decimal('order_plan_qty', 16, 4)->default(0);
            $table->decimal('stock_out_forecast_qty', 16, 4)->default(0);
            $table->decimal('demand_stock_out_forecast_qty', 16, 4)->default(0);
            $table->decimal('ending_stock_qty', 16, 4)->default(0);
            $table->decimal('buffer_15_days_qty', 16, 4)->default(0);
            $table->decimal('safety_stock_qty', 16, 4)->default(0);
            $table->decimal('stock_value_usd', 16, 4)->default(0);
            $table->decimal('inventory_reorder_qty', 16, 4)->default(0);
            $table->decimal('reorder_level_qty', 16, 4)->default(0);
            $table->decimal('max_inventory_level_qty', 16, 4)->default(0);

            // Days as integer
            $table->integer('ending_stock_cover_days')->default(0);
            $table->integer('order_lead_time_ss_days')->default(0);
            $table->integer('stock_in_days')->default(0);
            $table->integer('target_safety_stock_days')->default(0);
            $table->integer('max_usage_days')->default(0);

            $table->timestamps();

            $table->unique(['warehouse_id', 'product_id'], 'warehouse_product_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('warehouse_stock_reports');
    }
};
