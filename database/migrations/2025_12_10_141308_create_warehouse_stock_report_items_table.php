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
<<<<<<< HEAD
        Schema::create('warehouse_stock_reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('warehouse_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();

            // Quantities & values as decimal
            $table->decimal('avg_daily_use_per_day', 10, 4)->default(0);
            $table->decimal('order_plan_qty', 10, 4)->default(0);
            $table->decimal('stock_out_forecast_qty', 10, 4)->default(0);
            $table->decimal('demand_stock_out_forecast_qty', 10, 4)->default(0);
            $table->decimal('ending_stock_qty', 10, 4)->default(0);
            $table->decimal('buffer_15_days_qty', 10, 4)->default(0);
            $table->decimal('safety_stock_qty', 10, 4)->default(0);
            $table->decimal('stock_value_usd', 10, 4)->default(0);
            $table->decimal('inventory_reorder_qty', 10, 4)->default(0);
            $table->decimal('reorder_level_qty', 10, 4)->default(0);
            $table->decimal('max_inventory_level_qty', 10, 4)->default(0);

            // Days as integer
            $table->integer('ending_stock_cover_days')->default(0);
            $table->integer('order_lead_time_ss_days')->default(0);
            $table->integer('stock_in_days')->default(0);
            $table->integer('target_safety_stock_days')->default(0);
            $table->integer('max_usage_days')->default(0);

            $table->timestamps();

            $table->unique(['warehouse_id', 'product_id'], 'warehouse_product_unique');
=======
        Schema::create('warehouse_stock_report_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('report_id')->constrained('warehouse_stock_report_main')->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('warehouse_product_id')->constrained('warehouse_products')->cascadeOnDelete();

            // Quantities & values as decimal
            $table->decimal('unit_price', 10, 6)->default(0);
            $table->decimal('avg_6_month_usage', 10, 6)->default(0);
            $table->decimal('last_month_usage', 10, 6)->default(0);
            $table->decimal('stock_on_hand', 10, 6)->default(0);
            $table->decimal('order_plan_quantity', 10, 6)->default(0);
            $table->decimal('demand_forecast_quantity', 10, 6)->default(0);
            $table->decimal('ending_stock_cover_day', 10, 6)->default(0);
            $table->decimal('target_safety_stock_day', 10, 6)->default(0);
            $table->decimal('stock_value', 10, 6)->default(0);
            $table->decimal('inventory_reorder_quantity', 10, 6)->default(0);
            $table->decimal('reorder_level_day', 10, 6)->default(0);
            $table->decimal('max_inventory_level_quantity', 10, 6)->default(0);
            $table->decimal('max_inventory_usage_day', 10, 6)->default(0);

            $table->string('remarks')->nullable();
            $table->foreignId('updated_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->foreignId('deleted_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->softDeletes();
            $table->timestamps();
>>>>>>> 57be74724c4d7a629eb5e28e99dcfb78f7692860
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
<<<<<<< HEAD
        Schema::dropIfExists('warehouse_stock_reports');
=======
        Schema::dropIfExists('warehouse_stock_report_items');
>>>>>>> 57be74724c4d7a629eb5e28e99dcfb78f7692860
    }
};
