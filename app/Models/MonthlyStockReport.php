<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class MonthlyStockReport extends Model
{
    use SoftDeletes;

    protected $table = 'monthly_stock_reports';

    protected $fillable = [
        'reference_no',
        'report_date',
        'created_by',
        'position_id',
        'start_date',
        'end_date',
        'warehouse_ids',
        'warehouse_names',
        'approval_status',
        'approved_at',
        'approved_by',
    ];

    protected $casts = [
        'report_date'     => 'date',
        'start_date'      => 'date',
        'end_date'        => 'date',
        'approved_at'     => 'datetime',
        'warehouse_ids'   => 'array',
        'warehouse_names' => 'array',
    ];

    // Relationships
    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function approver()
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function position()
    {
        return $this->belongsTo(Position::class);
    }

    public function warehouses()
    {
        return $this->belongsToMany(Warehouse::class, 'warehouses', 'id');
    }

    // Auto generate reference_no on create
// app/Models/MonthlyStockReport.php

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($report) {
            // REQUIRED: end_date must exist for correct reference_no
            if (!$report->end_date) {
                throw new \Exception('end_date is required to generate reference number');
            }

            // Use end_date to determine the year-month
            $yearMonth = \Carbon\Carbon::parse($report->end_date)->format('Y-m');
            // Example: 2025-01, 2025-12

            // Find the last report that ends in the same month/year
            $last = static::withTrashed()
                ->whereRaw("DATE_FORMAT(end_date, '%Y-%m') = ?", [$yearMonth])
                ->orderByDesc('id')
                ->first();

            $sequence = $last 
                ? (int) substr($last->reference_no, -4) + 1 
                : 1;

            // Generate: MSR-2025-01-0001
            $report->reference_no = "MSR-{$yearMonth}-" . str_pad($sequence, 4, '0', STR_PAD_LEFT);

            // Auto-fill other fields
            if (empty($report->report_date)) {
                $report->report_date = $report->end_date;
            }

            if (empty($report->created_by)) {
                $report->created_by = auth()->id();
            }

            if (empty($report->position_id) && auth()->user()?->position_id) {
                $report->position_id = auth()->user()->position_id;
            }
        });
    }
}