<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('debit_note_items', function (Blueprint $table) {
            $table->string('item_code')->nullable()->after('stock_issue_item_id');
            $table->text('description')->nullable()->after('item_code');
            $table->decimal('quantity', 10, 4)->default(0)->after('description');
            $table->string('uom')->nullable()->after('quantity');
            $table->decimal('unit_price', 20, 15)->default(0)->after('uom');
            $table->decimal('total_price', 20, 15)->default(0)->after('unit_price');
            $table->string('requester_name')->nullable()->after('total_price');
            $table->string('campus_name')->nullable()->after('total_price');
            $table->string('division_name')->nullable()->after('campus_name');
            $table->string('department_name')->nullable()->after('division_name');
            $table->string('reference_no')->nullable()->after('department_name');
        });
    }

    public function down(): void
    {
        Schema::table('debit_note_items', function (Blueprint $table) {
            $table->dropColumn([
                'item_code',
                'description',
                'quantity',
                'uom',
                'unit_price',
                'total_price',
                'requester_name',
                'campus_name',
                'division_name',
                'department_name',
                'reference_no',
            ]);
        });
    }
};
