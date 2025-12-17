<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stock_ledgers', function (Blueprint $table) {
            $table->id();

            // Product reference
            $table->foreignId('product_id')
                  ->constrained('product_variants')
                  ->cascadeOnDelete();

            $table->unsignedBigInteger('item_id');
            $table->date('transaction_date');

            // Stock movement
            $table->decimal('quantity', 10, 4)->default(0); // negative = stock out
            $table->decimal('unit_price', 20, 15)->default(0);   // 10 decimal places
            $table->decimal('total_price', 20, 15)->default(0);  // 10 decimal places

            // Transaction type
            $table->enum('transaction_type', ['Stock_In', 'Stock_Out','Stock_Count','Stock_Begin']);

            // Parent info
            $table->string('parent_reference')->nullable();

            $table->foreignId('parent_warehouse')
                  ->nullable()
                  ->constrained('warehouses')
                  ->nullOnDelete();

            // User who created entry
            $table->foreignId('created_by')
                  ->nullable()
                  ->constrained('users')
                  ->nullOnDelete();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_ledgers');
    }
};
