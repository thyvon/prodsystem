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
        Schema::create('stock_beginnings', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('main_form_id');
            $table->unsignedBigInteger('product_id');
            $table->decimal('quantity', 15, 8)->default(0);
            $table->decimal('unit_price', 15, 8)->default(0);
            $table->decimal('total_value', 15, 8)->default(0);
            $table->string('remarks')->nullable();
            $table->unsignedBigInteger('created_by');
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->unsignedBigInteger('deleted_by')->nullable();
            $table->softDeletes();
            $table->timestamps();

            $table->foreign('product_id')->references('id')->on('product_variants')->onDelete('restrict');
            $table->foreign('created_by')->references('id')->on('users')->onDelete('restrict');
            $table->foreign('updated_by')->references('id')->on('users')->onDelete('restrict');
            $table->foreign('deleted_by')->references('id')->on('users')->onDelete('restrict');
            $table->foreign('main_form_id')->references('id')->on('main_stock_beginnings')->onDelete('restrict');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('stock_beginnings');
    }
};
