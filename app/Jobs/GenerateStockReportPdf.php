<?php

namespace App\Jobs;

use App\Models\MonthlyStockReport;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Support\Facades\Log;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Spatie\Browsershot\Browsershot;
use Illuminate\Support\Facades\Storage;

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

    Log::info('GenerateStockReportPdf started', ['report_id' => $report->id]);

    $mapLabel = [
        'verify'      => 'Verified By',
        'check'       => 'Checked By',
        'acknowledge' => 'Acknowledged By',
    ];

    $approvals = $report->approvals->map(function ($approval) use ($mapLabel) {
        $typeKey = strtolower($approval->request_type);
        return [
            'user_name'          => $approval->responder->name ?? 'Unknown',
            'position_name'      => $approval->responderPosition->title ?? null,
            'request_type_label' => $mapLabel[$typeKey] ?? ucfirst($typeKey) . ' By',
            'approval_status'    => $approval->approval_status,
            'responded_date'     => $approval->responded_date,
            'comment'            => $approval->comment,
            'signature_url'      => $approval->responder->signature_url ?? null,
        ];
    })->toArray();

    $data = app(\App\Http\Controllers\StockController::class)
                ->prepareReportData($report);
    $data['approvals'] = $approvals;

    $html = view('Inventory.stock-report.print-report', $data)->render();

    $fileName = 'Stock_Report_' . $report->id . '_' . now()->timestamp . '.pdf';

    Storage::disk('public')->makeDirectory('pdf'); // ensure folder exists
    $pdfContent = Browsershot::html($html)
        ->noSandbox()
        ->showBackground()
        ->emulateMedia('print')
        ->format('A4')
        ->landscape()
        ->margins(5, 3, 5, 3)
        ->setDelay(40)
        ->timeout(60)
        ->setTemporaryFolder(sys_get_temp_dir())
        ->addChromiumArguments([
            '--disable-gpu',
            '--disable-dev-shm-usage',
            '--no-zygote',
            '--single-process',
            '--no-sandbox',
            '--disable-setuid-sandbox',
            '--disable-software-rasterizer',
            '--disable-extensions',
            '--blink-settings=imagesEnabled=true',
            '--font-render-hinting=none',
            '--no-first-run',
            '--no-default-browser-check',
            '--disable-background-timer-throttling',
            '--disable-renderer-backgrounding',
        ])
        ->pdf();

    Storage::disk('public')->put('pdf/' . $fileName, $pdfContent);

    $report->pdf_file_path = 'pdf/' . $fileName;
    $report->save();

    Log::info('GenerateStockReportPdf finished', [
        'report_id' => $report->id,
        'path' => $report->pdf_file_path
    ]);
}

}
