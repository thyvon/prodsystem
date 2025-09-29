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
        Schema::create('documents_receivers', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('documents_id');
            $table->string('document_reference');
            $table->string('document_name');
            $table->string('status');
            $table->unsignedBigInteger('requester_id')->nullable();
            $table->unsignedBigInteger('receiver_id')->nullable();
            $table->datetime('sent_date')->nullable();
            $table->datetime('received_date')->nullable();

            $table->foreign('documents_id')->references('id')->on('document_transfers')->onDelete('cascade');
            $table->foreign('requester_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('receiver_id')->references('id')->on('users')->onDelete('cascade');
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('documents_receivers');
    }
};
