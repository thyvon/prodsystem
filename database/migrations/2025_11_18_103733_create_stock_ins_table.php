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
      Schema::create('stock_ins', function (Blueprint $table) {
            $table->id();
            $table->date('transaction_date');
            $table->string('reference_no')->unique();
            $table->string('transaction_type');
            $table->string('invoice_no')->nullable();
            $table->string('payment_terms')->nullable();

            $table->foreignId('supplier_id')
                  ->constrained('suppliers')
                  ->restrictOnDelete();
            
            $table->foreignId('warehouse_id')
                  ->constrained('warehouses')
                  ->restrictOnDelete();

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
        Schema::dropIfExists('stock_ins');
    }
};
