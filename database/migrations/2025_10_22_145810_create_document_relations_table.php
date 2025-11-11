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
        Schema::create('document_relations', function (Blueprint $table) {
            $table->id();
            $table->morphs('documentable'); // documentable_type, documentable_id
            $table->string('document_name');
            $table->string('document_reference');
            // $table->string('sharepoint_file_id');
            $table->string('file_name');
            // $table->string('sharepoint_drive_id');
            $table->string('path');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('document_relations');
    }
};
