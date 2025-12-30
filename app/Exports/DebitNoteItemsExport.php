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

    protected Collection $rows;

    public function __construct(DebitNote $debitNote, ?string $logoPath = null)
    {
        $this->debitNote = $debitNote;
        $this->logoPath  = $logoPath;

        // Preload all necessary data efficiently
        $items = $this->debitNote
            ->items()
            ->with([
                'stockIssueItem:id,stock_issue_id,product_id,quantity,unit_price,total_price,campus_id,department_id',
                'stockIssueItem.stockIssue:id,transaction_date,requested_by',
                'stockIssueItem.stockIssue.requestedBy:id,name',
                'stockIssueItem.productVariant:id,item_code,product_id,description',
                'stockIssueItem.productVariant.product:id,name,unit_id',
                'stockIssueItem.productVariant.product.unit:id,name',
                'stockIssueItem.campus:id,short_name',
                'stockIssueItem.department:id,short_name,division_id',
                'stockIssueItem.department.division:id,short_name'
            ])
            ->get();

        // Precompute rows for speed
        $this->rows = $items->map(function ($item, $index) {
            $stockItem = $item->stockIssueItem;
            $transactionDate = $stockItem->stockIssue->transaction_date instanceof Carbon
                ? $stockItem->stockIssue->transaction_date
                : Carbon::parse($stockItem->stockIssue->transaction_date);

            return [
                $index,
                $transactionDate->format('M d, Y'),
                $stockItem->productVariant->item_code ?? '',
                trim(($stockItem->productVariant->product->name ?? '') . ' ' . ($stockItem->productVariant->description ?? '')),
                $stockItem->quantity ?? 0,
                $stockItem->productVariant->product->unit->name ?? '',
                $stockItem->unit_price ?? 0,
                $stockItem->total_price ?? 0,
                $stockItem->stockIssue->requestedBy->name ?? '',
                $stockItem->campus->short_name ?? '',
                $stockItem->department->division->short_name ?? '',
                $stockItem->department->short_name ?? '',
                $item->remarks ?? '',
                $stockItem->stockIssue->reference_no ?? '',
            ];
        });
    }

    public function collection()
    {
        return $this->rows;
    }

    public function map($row): array
    {
        return $row;
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

                // Department / Warehouse
                $sheet->mergeCells('A3:N3');
                $sheet->setCellValue(
                    'A3',
                    ($this->debitNote->department->name ?? '') . ' - ' .
                    ($this->debitNote->warehouse->name ?? '')
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
                $dataCount    = $this->rows->count();
                $firstDataRow = $headerRow + 1;
                $lastDataRow  = $headerRow + $dataCount;

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
            },
        ];
    }
}
