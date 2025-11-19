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
        Schema::table('stock_issues', function (Blueprint $table) {
            $table->foreignId('position_id')
                ->nullable()
                ->after('created_by')
                ->constrained('positions')
                ->restrictOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('stock_issues', function (Blueprint $table) {
            $table->dropForeign(['position_id']);
            $table->dropColumn('position_id');
        });
    }
};
