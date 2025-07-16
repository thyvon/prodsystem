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
        Schema::create('unit_of_measures', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('khmer_name')->nullable();
            $table->string('short_name')->nullable();
            $table->string('description')->nullable();
            $table->string('operator')->nullable();
            $table->decimal('conversion_factor', 10, 2)->default(1.00);
            $table->unsignedBigInteger('parent_unit_id')->nullable();
            $table->integer('is_active')->default(1);
            $table->timestamps();

            $table->foreign('parent_unit_id')
            ->references('id')->on('unit_of_measures')
            ->onDelete('restrict');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('unit_of_measures');
    }
};
