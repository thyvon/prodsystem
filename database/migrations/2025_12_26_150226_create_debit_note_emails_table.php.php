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
        Schema::create('debit_note_emails', function (Blueprint $table) {
            $table->id();
            $table->foreignId('department_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('warehouse_id')->nullable()->constrained()->nullOnDelete();
            $table->json('send_to_email')->nullable();
            $table->json('cc_to_email')->nullable();
            $table->timestamps();

            $table->unique(['department_id', 'warehouse_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('debit_note_emails');
    }
};
