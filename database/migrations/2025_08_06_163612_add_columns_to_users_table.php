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
            $table->string('card_number')->nullable()->after('email');
            $table->string('profile_url')->nullable()->after('card_number');
            $table->string('signature_url')->nullable()->after('profile_url');
            $table->string('telegram_id')->nullable()->after('signature_url');
            $table->string('phone')->nullable()->after('telegram_id');
            $table->integer('is_active')->default(1)->after('phone');
            $table->unsignedBigInteger('building_id')->nullable()->after('is_active');
            $table->unsignedBigInteger('default_department_id')->nullable()->after('building_id');
            $table->unsignedBigInteger('default_campus_id')->nullable()->after('default_department_id');
            $table->unsignedBigInteger('current_position_id')->nullable()->after('default_campus_id');

            $table->foreign('building_id')->references('id')->on('buildings')->onDelete('restrict');
            $table->foreign('default_department_id')->references('id')->on('departments')->onDelete('restrict');
            $table->foreign('default_campus_id')->references('id')->on('campus')->onDelete('restrict');
            $table->foreign('current_position_id')->references('id')->on('positions')->onDelete('restrict');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('card_number');
            $table->dropColumn('profile_url');
            $table->dropColumn('signature_url');
            $table->dropColumn('telegram_id');
            $table->dropColumn('phone');
            $table->dropColumn('is_active');
            $table->dropColumn('building_id');
            $table->dropColumn('default_department_id');
            $table->dropColumn('default_campus_id');
            $table->dropColumn('current_position_id');
        });
    }
};
