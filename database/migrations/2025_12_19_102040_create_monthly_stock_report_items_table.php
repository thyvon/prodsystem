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
        Schema::create('monthly_stock_report_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('report_id')->constrained('monthly_stock_reports')->onDelete('cascade');
            $table->unsignedBigInteger('product_id');
            $table->decimal('beginning_quantity', 10, 4)->default(0);
            $table->decimal('stock_in_quantity', 10, 4)->default(0);
            $table->decimal('available_quantity', 10, 4)->default(0);
            $table->decimal('stock_out_quantity', 10, 4)->default(0);
            $table->decimal('ending_quantity', 10, 4)->default(0);
            $table->decimal('counted_quantity', 10, 4)->default(0);
            $table->decimal('variance_quantity', 10, 4)->default(0);

            // Prices / totals with 15 decimal places
            $table->decimal('beginning_price', 20, 15)->default(0);
            $table->decimal('beginning_total', 20, 15)->default(0);
            $table->decimal('stock_in_total', 20, 15)->default(0);
            $table->decimal('available_price', 20, 15)->default(0);
            $table->decimal('available_total', 20, 15)->default(0);
            $table->decimal('stock_out_total', 20, 15)->default(0);
            $table->decimal('ending_total', 20, 15)->default(0);
            $table->decimal('average_price', 20, 15)->default(0);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('monthly_stock_report_items');
    }
};
