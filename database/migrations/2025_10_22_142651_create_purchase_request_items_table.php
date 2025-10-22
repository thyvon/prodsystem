<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('purchase_request_items', function (Blueprint $table) {
            $table->id();

            // 🔗 Foreign keys with explicit table names
            $table->foreignId('purchase_request_id')
                ->constrained('purchase_requests')
                ->cascadeOnDelete();

            $table->foreignId('product_id')
                ->constrained('products')
                ->restrictOnDelete();

            $table->foreignId('campus_id')
                ->constrained('campus')
                ->restrictOnDelete();

            $table->foreignId('department_id')
                ->constrained('departments')
                ->restrictOnDelete();

            $table->foreignId('division_id')
                ->constrained('divisions')
                ->restrictOnDelete();
            
            $table->foreignId('budget_code_id')
                ->constrained('budget_items')
                ->restrictOnDelete();

            // 🧾 Item details
            $table->text('description')->nullable();

            $table->string('currency')->nullable();
            $table->decimal('exchange_rate', 15, 6)->default(1);
            // 📦 Quantity and pricing
            $table->decimal('quantity', 10, 4)->default(0);
            $table->decimal('unit_price', 10, 4)->default(0);
            $table->decimal('total_price', 10, 4)->default(0);
            $table->decimal('total_price_usd', 10, 4)->default(0);
            // 🚦 Status
            $table->integer('purchasing_status')->default(0);

            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('purchase_request_items');
    }
};
