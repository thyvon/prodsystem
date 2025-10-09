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
        Schema::table('digital_docs_approvals', function (Blueprint $table) {
            $table->string('sharepoint_file_ui_url')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('digital_docs_approvals', function (Blueprint $table) {
            $table->dropColumn('sharepoint_file_ui_url');
        });
    }
};
