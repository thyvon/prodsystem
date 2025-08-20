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
        Schema::table('stock_request_items', function (Blueprint $table) {
            $table->unsignedBigInteger('department_id')->nullable()->after('product_id');
            $table->unsignedBigInteger('campus_id')->nullable()->after('department_id');

            // Add foreign key constraints
            $table->foreign('department_id')
                ->references('id')
                ->on('departments')
                ->onDelete('restrict');

            $table->foreign('campus_id')
                ->references('id')
                ->on('campus')
                ->onDelete('restrict');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('stock_request_items', function (Blueprint $table) {
            $table->dropForeign(['department_id']);
            $table->dropForeign(['campus_id']);
            $table->dropColumn(['department_id', 'campus_id']);
        });
    }
};
