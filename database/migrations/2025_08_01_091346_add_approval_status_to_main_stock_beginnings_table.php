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
        Schema::table('main_stock_beginnings', function (Blueprint $table) {
            $table->string('approval_status')->default('Pending')->after('warehouse_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('main_stock_beginnings', function (Blueprint $table) {
            $table->dropColumn('approval_status');
        });
    }
};
