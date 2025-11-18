<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stock_ledger', function (Blueprint $table) {
            $table->id();

            // Product info
            $table->unsignedBigInteger('product_id')->index();

            // Stock movement
            $table->integer('quantity'); // negative for Stock Out
            $table->decimal('unit_price', 15, 2);
            $table->decimal('total_price', 15, 2);

            // Type of transaction
            $table->enum('transaction_type', ['Stock In', 'Stock Out'])->index();

            // Parent info
            $table->string('parent_reference')->nullable()->index();
            $table->unsignedBigInteger('parent_warehouse')->nullable()->index();
            $table->unsignedBigInteger('parent_department')->nullable()->index();

            // User info
            $table->unsignedBigInteger('created_by')->index();

            $table->timestamps();

            // Foreign keys
            $table->foreign('product_id')
                  ->references('id')
                  ->on('product_variants')
                  ->onDelete('cascade');

            $table->foreign('parent_warehouse')
                  ->references('id')
                  ->on('warehouses')
                  ->onDelete('set null');

            $table->foreign('parent_department')
                  ->references('id')
                  ->on('departments')
                  ->onDelete('set null');

            $table->foreign('created_by')
                  ->references('id')
                  ->on('users')
                  ->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_ledger');
    }
};
