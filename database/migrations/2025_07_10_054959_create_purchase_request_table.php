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
        Schema::create('purchase_request', function (Blueprint $table) {
            $table->id();
            $table->string('request_number')->unique();
            $table->text('purpose');
            $table->date('deadline')->nullable();
            $table->unsignedBigInteger('currency_id')->default(1);
            $table->integer('exchange_rate')->default(4000);
            $table->decimal('total_amount', 10, 4)->default(0);
            $table->decimal('total_amount_usd', 10, 4)->default(0);
            $table->integer('status')->default(0);
            $table->integer('is_urgent')->default(0);
            $table->dateTime('received_date')->nullable();
            $table->unsignedBigInteger('created_by');
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->unsignedBigInteger('deleted_by')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('purchase_request');
    }
};
