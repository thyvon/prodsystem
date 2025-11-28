<?php

namespace App\Jobs;

use App\Models\MonthlyStockReport;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Spatie\Browsershot\Browsershot;

class GenerateStockReportPdf implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $monthlyStockReport;

    public function __construct(MonthlyStockReport $monthlyStockReport)
    {
        $this->monthlyStockReport = $monthlyStockReport;
    }

    public function handle()
    {
        $report = $this->monthlyStockReport->load([
            'creator',
            'creatorPosition',
            'approvals.responder',
            'approvals.responderPosition',
        ]);

        Log::info('PDF generation started', ['report_id' => $report->id]);

        // -------------------------
        // 1. Ensure PDF folder exists
        // -------------------------
        Storage::disk('public')->makeDirectory('pdf');

        // -------------------------
        // 3. Map approvals
        // -------------------------
        $approvalLabels = [
            'verify'      => 'Verified By',
            'check'       => 'Checked By',
            'acknowledge' => 'Acknowledged By',
        ];

        $approvals = $report->approvals->map(function ($approval) use ($approvalLabels) {
            $typeKey = strtolower($approval->request_type);
            return [
                'user_name'          => $approval->responder->name ?? 'Unknown',
                'position_name'      => $approval->responderPosition->title ?? '-',
                'request_type_label' => $approvalLabels[$typeKey] ?? ucfirst($typeKey) . ' By',
                'approval_status'    => $approval->approval_status,
                'responded_date'     => $approval->responded_date,
                'signature_url'      => $approval->responder->signature_url ?? null,
            ];
        })->toArray();

        // -------------------------
        // 4. Prepare report data
        // -------------------------
        $data = app(\App\Http\Controllers\StockController::class)
            ->prepareReportData($report);
        $data['approvals'] = $approvals;

        $html = view('Inventory.stock-report.print-report', $data)->render();

        // -------------------------
        // 5. Generate PDF
        // -------------------------
        $fileName = 'Stock_Report_' . $report->id . '_' . now()->timestamp . '.pdf';
        $savePath = 'pdf/' . $fileName; // inside storage/app/public/pdf/

        $pdfContent = Browsershot::html($html)
            ->noSandbox()
            ->showBackground()
            ->emulateMedia('print')
            ->format('A4')
            ->landscape()
            ->margins(5, 3, 5, 3)
            ->setDelay(20)
            ->timeout(120)
            ->userAgent('Mozilla/5.0 (X11; Linux x86_64)')
            ->windowSize(1280, 800)
            ->disableJavascript() // CPU saver
            ->addChromiumArguments([
                '--disable-gpu',
                '--disable-dev-shm-usage',
                '--no-zygote',
                '--single-process',
                '--no-sandbox',
                '--disable-extensions',
                '--disable-software-rasterizer',
                '--disable-background-networking',
                '--disable-background-timer-throttling',
                '--disable-renderer-backgrounding',
                '--disable-sync',
                '--disable-images', // massive CPU & RAM saver
            ])
            ->pdf();

        // -------------------------
        // 6. Save PDF in storage
        // -------------------------
        Storage::disk('public')->put($savePath, $pdfContent);

        // -------------------------
        // 7. Update database with path
        // -------------------------
        $report->update(['pdf_file_path' => $savePath]);

        Log::info('PDF generation finished', [
            'report_id' => $report->id,
            'storage_path' => Storage::disk('public')->path($savePath),
        ]);
    }
}
