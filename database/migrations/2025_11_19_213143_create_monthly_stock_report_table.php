<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('monthly_stock_reports', function (Blueprint $table) {
            $table->id();
            $table->string('reference_no')->unique();
            $table->date('report_date'); // usually end_date or today
            $table->foreignId('created_by')->nullable()->constrained('users')->onDelete('restrict');
            $table->foreignId('position_id')->nullable()->constrained('positions')->onDelete('restrict');
            $table->date('start_date');
            $table->date('end_date');
            $table->json('warehouse_ids')->nullable();
            $table->json('warehouse_names')->nullable();
            $table->string('approval_status')->default('Pending');
            $table->softDeletes();
            $table->timestamps();

            $table->index(['start_date', 'end_date']);
            $table->index('approval_status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('monthly_stock_reports');
    }
};