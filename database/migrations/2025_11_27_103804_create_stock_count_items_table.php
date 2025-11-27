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
        Schema::create('stock_count_items', function (Blueprint $table) {
            $table->id();

            $table->foreignId('stock_count_id')
                ->constrained('stock_counts')
                ->cascadeOnDelete();   // delete items when stock_count is deleted

            $table->foreignId('product_id')
                ->constrained('product_variants')
                ->restrictOnDelete();
            $table->decimal('ending_quantity', 10, 4);
            $table->decimal('counted_quantity', 10, 4);
            $table->string('remarks')->nullable();

            $table->foreignId('created_by')
                ->constrained('users')
                ->restrictOnDelete();

            $table->foreignId('updated_by')
                ->nullable()
                ->constrained('users')
                ->restrictOnDelete();

            $table->foreignId('deleted_by')
                ->nullable()
                ->constrained('users')
                ->restrictOnDelete();

            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('stock_count_items');
    }
};
