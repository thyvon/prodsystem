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
            $table->unsignedBigInteger('position_id')->nullable()->after('created_by');
            $table->unsignedBigInteger('stock_request_id')->nullable()->after('id');

            $table->foreign('position_id')->references('id')->on('positions')->onDelete('restrict');
            $table->foreign('stock_request_id')->references('id')->on('stock_requests')->onDelete('restrict');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('stock_issues', function (Blueprint $table) {
            $table->dropForeign(['position_id']);
            $table->dropForeign(['stock_request_id']);
            $table->dropColumn(['position_id', 'stock_request_id']);
        });
    }
};
