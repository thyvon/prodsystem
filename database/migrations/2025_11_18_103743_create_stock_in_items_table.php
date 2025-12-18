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
        Schema::create('stock_in_items', function (Blueprint $table) {
            $table->id();

            $table->foreignId('stock_in_id')->constrained('stock_ins')->cascadeOnDelete();
            $table->foreignId('product_id')->constrained('product_variants')->restrictOnDelete();

            $table->decimal('quantity', 10, 4);
            $table->decimal('unit_price', 20, 15);
            $table->decimal('vat', 20, 15)->default(0);
            $table->decimal('discount', 20, 15)->default(0);
            $table->decimal('delivery_fee', 20, 15)->default(0);
            $table->decimal('total_price', 20, 15);
            $table->string('remarks')->nullable();

            $table->foreignId('updated_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->foreignId('deleted_by')->nullable()->constrained('users')->restrictOnDelete();

            $table->timestamps();
            $table->softDeletes();
        });
    }


    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('stock_in_items');
    }
};
