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
        Schema::create('purchase_request_items', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('purchase_request_id');
            $table->unsignedBigInteger('product_id');
            $table->text('description')->nullable();
            $table->decimal('quantity', 10, 4)->default(0);
            $table->decimal('unit_price', 10, 4)->default(0);
            $table->decimal('total_price', 10, 4)->default(0);
            $table->decimal('total_price_usd', 10, 4)->default(0);
            $table->unsignedBigInteger('campus_id');
            $table->unsignedBigInteger('department_id');
            $table->unsignedBigInteger('division_id');
            $table->decimal('quantity_ordered', 10, 4)->default(0);
            $table->decimal('quantity_purchased', 10, 4)->default(0);
            $table->decimal('quantity_cancelled', 10, 4)->default(0);
            $table->integer('status')->default(0);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('purchase_request_items');
    }
};
