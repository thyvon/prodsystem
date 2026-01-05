<?php

namespace App\Exports;

use App\Models\DebitNote;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithColumnFormatting;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Worksheet\Drawing;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use Carbon\Carbon;

class DebitNoteItemsExport implements
    FromCollection,
    WithMapping,
    WithColumnFormatting,
    WithStyles,
    WithEvents
{
    protected DebitNote $debitNote;
    protected ?string $logoPath;

    protected array $headings = [
        'No',
        'Date',
        'Code',
        'Item Name',
        'Qty',
        'Packing',
        'U/Price',
        'Amount',
        'Name',
        'Campus',
        'Division',
        'Division/Department',
        'Remarks',
        'IO Number',
    ];
    private $index = 0;
    protected Collection $rows;

    public function __construct(DebitNote $debitNote, ?string $logoPath = null)
    {
        $this->debitNote = $debitNote;
        $this->logoPath  = $logoPath;

        // Load items directly from debit_note_items table
        $this->rows = $this->debitNote->items()->orderBy('id')->get();
    }

    public function collection()
    {
        return $this->rows;
    }

    public function map($item): array
    {
        $currentIndex = $this->index + 1; // number starting from 1
        $this->index++; // increment for next row
        $transactionDate = $item->transaction_date instanceof Carbon
            ? $item->transaction_date
            : Carbon::parse($item->transaction_date);

        return [
            $currentIndex,
            $transactionDate->format('M d, Y'),
            $item->item_code,
            $item->description,
            $item->quantity,
            $item->uom,
            $item->unit_price,
            $item->total_price,
            $item->requester_name,
            $item->campus_name,
            $item->division_name,
            $item->department_name,
            $item->remarks,
            $item->reference_no,
        ];
    }

    public function columnFormats(): array
    {
        return [
            'B' => NumberFormat::FORMAT_TEXT,
            'E' => NumberFormat::FORMAT_NUMBER,
            'G' => NumberFormat::FORMAT_NUMBER_00,
            'H' => NumberFormat::FORMAT_NUMBER_00,
        ];
    }

    public function styles(Worksheet $sheet)
    {
        $sheet->getColumnDimension('A')->setWidth(5);
        foreach (range('B', 'N') as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }
        return [];
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();
                $headerRow = 4;

                // Insert space for logo + title
                $sheet->insertNewRowBefore(1, 3);

                // Logo
                if ($this->logoPath && file_exists($this->logoPath)) {
                    $drawing = new Drawing();
                    $drawing->setName('Company Logo');
                    $drawing->setPath($this->logoPath);
                    $drawing->setCoordinates('A1');
                    $drawing->setHeight(60);
                    $drawing->setWorksheet($sheet);
                }

                // Title
                $sheet->mergeCells('A2:N2');
                $sheet->setCellValue('A2', 'Debit Note');
                $sheet->getStyle('A2')->getFont()->setBold(true)->setSize(14);
                $sheet->getStyle('A2')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                
                
                $startDate = $this->debitNote->start_date instanceof \Carbon\Carbon 
                    ? $this->debitNote->start_date 
                    : \Carbon\Carbon::parse($this->debitNote->start_date);

                $endDate = $this->debitNote->end_date instanceof \Carbon\Carbon 
                    ? $this->debitNote->end_date 
                    : \Carbon\Carbon::parse($this->debitNote->end_date);
                // Department / Warehouse
                $sheet->mergeCells('A3:N3');
                $sheet->setCellValue(
                    'A3',
                    ($this->debitNote->department->name ?? '') . ' - ' .
                    ($this->debitNote->warehouse->name ?? '') . ' (' .
                    $startDate->format('M d, Y') . ' - ' .
                    $endDate->format('M d, Y') . ')'
                );
                $sheet->getStyle('A3')->getFont()->setBold(true);
                $sheet->getStyle('A3')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

                // Header
                foreach ($this->headings as $i => $heading) {
                    $sheet->setCellValueByColumnAndRow($i + 1, $headerRow, $heading);
                }
                $sheet->getStyle("A{$headerRow}:N{$headerRow}")->getFont()->setBold(true);
                $sheet->getStyle("A{$headerRow}:N{$headerRow}")
                    ->getFill()
                    ->setFillType(Fill::FILL_SOLID)
                    ->getStartColor()->setRGB('CCFFCC');

                // Table borders
                $dataCount = $this->rows->count();
                $firstDataRow = $headerRow + 1;
                $lastDataRow = $headerRow + $dataCount;

                if ($dataCount > 0) {
                    $sheet->getStyle("A{$headerRow}:N{$lastDataRow}")
                        ->getBorders()->getAllBorders()
                        ->setBorderStyle(Border::BORDER_THIN);
                }

                // TOTAL row
                $totalRow = $lastDataRow + 1;
                $sheet->setCellValue("G{$totalRow}", 'TOTAL');
                $sheet->setCellValue(
                    "H{$totalRow}",
                    $dataCount > 0 ? "=SUM(H{$firstDataRow}:H{$lastDataRow})" : 0
                );
                $sheet->getStyle("G{$totalRow}:H{$totalRow}")->getFont()->setBold(true);

                // ----------------------------
                // Prepared By section (4 rows)
                // ----------------------------
                $footerRow = $totalRow + 2; // leave one row blank

                $creatorName     = $this->debitNote->creator->name ?? '';
                $creatorPosition = $this->debitNote->creator->defaultPosition->title ?? '';
                $currentDate     = $this->debitNote->created_at->format('F d, Y');

                $sheet->setCellValue("A{$footerRow}", 'Prepared By:');
                $sheet->getStyle("A{$footerRow}")->getFont()->setBold(true);

                $sheet->setCellValue("A" . ($footerRow + 1), 'Name: ' . $creatorName);
                $sheet->setCellValue("A" . ($footerRow + 2), 'Position: ' . $creatorPosition);
                $sheet->setCellValue("A" . ($footerRow + 3), 'Date: ' . $currentDate);

                $sheet->getStyle("A{$footerRow}:A" . ($footerRow + 3))
                    ->getAlignment()
                    ->setHorizontal(Alignment::HORIZONTAL_LEFT);
            },
        ];
    }
}
