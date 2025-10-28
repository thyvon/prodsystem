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
            $table->string('owner_received_status')->default('Pending')->after('status');
            $table->datetime('owner_received_date')->nullable()->after('owner_received_status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('documents_receivers', function (Blueprint $table) {
            $table->dropColumn('owner_received_status');
            $table->dropColumn('owner_received_date');
        });
    }
};
