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
        Schema::table('documents_receivers', function (Blueprint $table) {
            $table->string('telegram_message_id')->nullable()->after('documents_id');
            $table->string('telegram_creator_message_id')->nullable()->after('telegram_message_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('documents_receivers', function (Blueprint $table) {
            $table->dropColumn('telegram_message_id');
            $table->dropColumn('telegram_creator_message_id');
        });
    }
};
