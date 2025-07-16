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
        Schema::create('sub_categories', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('khmer_name')->nullable();
            $table->string('short_name')->nullable();
            $table->string('description')->nullable();
            $table->unsignedBigInteger('main_category_id');
            $table->integer('is_active')->default(1);
            $table->timestamps();

            $table->foreign('main_category_id')
            ->references('id')->on('main_categories')
            ->onDelete('restrict');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sub_categories');
    }
};
