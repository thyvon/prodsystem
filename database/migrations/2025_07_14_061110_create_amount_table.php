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
        Schema::create('toca_amount', function (Blueprint $table) {
            $table->id();
            $table->decimal('min_amount', 10, 2);
            $table->decimal('max_amount', 10, 2);
            $table->unsignedBigInteger('toca_id');
            $table->integer('is_active')->default(1);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('toca_amount');
    }
};
