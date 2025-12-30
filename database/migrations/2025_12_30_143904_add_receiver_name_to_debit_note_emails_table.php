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
        Schema::table('debit_note_emails', function (Blueprint $table) {
            $table -> string('receiver_name')->nullable()->after('send_to_email');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('debit_note_emails', function (Blueprint $table) {
            $table->dropColumn('receiver_name');
        });
    }
};
