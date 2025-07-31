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
        Schema::create('approvals', function (Blueprint $table) {
            $table->id();
            $table->morphs('approvable'); // approvable_type, approvable_id
            $table->string('document_name');
            $table->string('request_type');
            $table->string('approval_status');
            $table->string('comment')->nullable();
            $table->integer('ordinal');
            $table->unsignedBigInteger('requester_id');
            $table->unsignedBigInteger('responder_id');
            $table->datetime('responded_date')->nullable();
            $table->timestamps();

            $table->foreign('requester_id')->references('id')->on('users')->onDelete('restrict');
            $table->foreign('responder_id')->references('id')->on('users')->onDelete('restrict');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('approvals');
    }
};
