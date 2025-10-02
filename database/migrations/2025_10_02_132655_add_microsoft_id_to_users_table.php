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
        Schema::table('users', function (Blueprint $table) {
            $table->string('microsoft_id')->nullable()->after('id')->unique();
            $table->text('microsoft_token')->nullable()->after('microsoft_id');
            $table->text('microsoft_refresh_token')->nullable()->after('microsoft_token');
            $table->timestamp('microsoft_token_expires_at')->nullable()->after('microsoft_refresh_token');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('microsoft_id');
            $table->dropColumn('microsoft_token');
            $table->dropColumn('microsoft_refresh_token');
            $table->dropColumn('microsoft_token_expires_at');
        });
    }
};
