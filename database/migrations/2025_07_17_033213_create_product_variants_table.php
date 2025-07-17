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
        Schema::create('product_variants', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('product_id');
            $table->string('item_code')->unique();
            $table->decimal('estimated_price', 10, 4)->nullable();
            $table->decimal('average_price', 10, 4)->nullable();
            $table->string('image')->nullable();
            $table->string('description')->nullable();
            $table->integer('is_active')->default(1);
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->unsignedBigInteger('deleted_by')->nullable();
            $table->softDeletes();
            $table->timestamps();
            
            $table->foreign('product_id')
            ->references('id')->on('products')
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
        Schema::dropIfExists('product_variants');
    }
};
