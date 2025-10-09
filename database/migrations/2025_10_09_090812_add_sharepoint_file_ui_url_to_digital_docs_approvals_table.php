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
            $table->text('sharepoint_file_ui_url')->nullable()->after('sharepoint_file_id');
            $table->string('sharepoint_drive_id')->nullable()->after('sharepoint_file_ui_url');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('digital_docs_approvals', function (Blueprint $table) {
            $table->dropColumn('sharepoint_file_ui_url');
            $table->dropColumn('sharepoint_drive_id');
        });
    }
};
