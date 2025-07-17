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
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->string('item_code')->unique();
            $table->string('name');
            $table->string('khmer_name')->nullable();
            $table->text('description')->nullable();
            $table->boolean('has_variants')->default(false);
            $table->string('barcode')->nullable();
            $table->unsignedBigInteger('category_id');
            $table->unsignedBigInteger('sub_category_id')->nullable();
            $table->unsignedBigInteger('unit_id');
            $table->boolean('manage_stock')->default(true);
            $table->string('image')->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->unsignedBigInteger('deleted_by')->nullable();
            $table->softDeletes();
            $table->timestamps();

            $table->foreign('category_id')
                ->references('id')->on('main_categories')
                ->onDelete('restrict');
            $table->foreign('sub_category_id')
                ->references('id')->on('sub_categories')
                ->onDelete('restrict');
            $table->foreign('unit_id')
                ->references('id')->on('unit_of_measures')
                ->onDelete('restrict'); 
            $table->foreign('created_by')
                ->references('id')->on('users')
                ->onDelete('restrict');
            $table->foreign('updated_by')
                ->references('id')->on('users')
                ->onDelete('restrict');
            $table->foreign('deleted_by')
                ->references('id')->on('users')
                ->onDelete('restrict');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
