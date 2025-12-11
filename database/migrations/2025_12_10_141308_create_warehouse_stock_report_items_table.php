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
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('warehouse_stock_report_items');
    }
};
