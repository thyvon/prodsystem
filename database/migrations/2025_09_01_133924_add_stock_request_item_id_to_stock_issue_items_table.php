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
        Schema::table('stock_issue_items', function (Blueprint $table) {
            $table->unsignedBigInteger('stock_request_item_id')->after('stock_issue_id');
            $table->foreign('stock_request_item_id')->references('id')->on('stock_request_items')->onDelete('restrict');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('stock_issue_items', function (Blueprint $table) {
            $table->dropForeign(['stock_request_item_id']);
            $table->dropColumn('stock_request_item_id');
        });
    }
};
