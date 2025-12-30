<?php

namespace App\Exports;

use App\Models\DebitNote;
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
use PhpOffice\PhpSpreadsheet\Shared\Date;
use Carbon\Carbon;

class DebitNoteItemsExport implements FromCollection, WithMapping, WithColumnFormatting, WithStyles, WithEvents
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

    public function __construct(DebitNote $debitNote, ?string $logoPath = null)
    {
        $this->debitNote = $debitNote;
        $this->logoPath = $logoPath;
    }

    public function collection()
    {
        return $this->debitNote->items()->with('stockIssueItem.productVariant.product.unit')->get();
    }

    public function map($item): array
    {
        $stockItem = $item->stockIssueItem;

        // Format transaction date as "Dec 30, 2025"
        $transactionDate = $stockItem->stockIssue->transaction_date instanceof Carbon
            ? $stockItem->stockIssue->transaction_date
            : Carbon::parse($stockItem->stockIssue->transaction_date);

        $formattedDate = $transactionDate->format('M d, Y');

        return [
            $item->id,
            $formattedDate,
            $stockItem->productVariant->item_code ?? '',
            trim(($stockItem->productVariant->product->name ?? '') . ' ' . ($stockItem->productVariant->description ?? '')),
            $stockItem->quantity ?? 0,
            $stockItem->productVariant->product->unit->name ?? '',
            $stockItem->unit_price ?? 0,
            $stockItem->total_price ?? 0,
            $item->user->name ?? '',
            $item->campus->name ?? '',
            $item->division->name ?? '',
            $item->department->name ?? '',
            $item->remarks ?? '',
            $item->io_number ?? '',
        ];
    }

    public function columnFormats(): array
    {
        return [
            // All date columns can be text because we format them manually
            'B' => NumberFormat::FORMAT_TEXT,
            'E' => NumberFormat::FORMAT_NUMBER,
            'G' => NumberFormat::FORMAT_NUMBER_00,
            'H' => NumberFormat::FORMAT_NUMBER_00,
        ];
    }

    public function styles(Worksheet $sheet)
    {
        // Set small width for No column
        $sheet->getColumnDimension('A')->setWidth(5);

        // Auto size other columns
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

                // Insert 3 rows at the top for logo and title
                $sheet->insertNewRowBefore(1, 3);

                // Insert logo
                if ($this->logoPath && file_exists($this->logoPath)) {
                    $drawing = new Drawing();
                    $drawing->setName('Company Logo');
                    $drawing->setPath($this->logoPath);
                    $drawing->setCoordinates('A1');
                    $drawing->setHeight(60);
                    $drawing->setWorksheet($sheet);
                }

                // Title row
                $sheet->mergeCells('A2:N2');
                $sheet->setCellValue('A2', 'Debit Note');
                $sheet->getStyle('A2')->getFont()->setBold(true)->setSize(14);
                $sheet->getStyle('A2')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                $sheet->getRowDimension(2)->setRowHeight(30);

                // Department & Warehouse row
                $departmentName = $this->debitNote->department->name ?? '';
                $warehouseName  = $this->debitNote->warehouse->name ?? '';
                $sheet->mergeCells('A3:N3');
                $sheet->setCellValue('A3', "{$departmentName} - {$warehouseName}");
                $sheet->getStyle('A3')->getFont()->setBold(true)->setSize(12);
                $sheet->getStyle('A3')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                $sheet->getRowDimension(3)->setRowHeight(20);

                // Header row
                $headerRow = 4;
                foreach ($this->headings as $colIndex => $heading) {
                    $sheet->setCellValueByColumnAndRow($colIndex + 1, $headerRow, $heading);
                }

                // Style header
                $sheet->getStyle("A{$headerRow}:N{$headerRow}")->getFont()->setBold(true);
                $sheet->getStyle("A{$headerRow}:N{$headerRow}")->getFill()
                    ->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
                    ->getStartColor()->setRGB('CCFFCC');
                $sheet->getStyle("A{$headerRow}:N{$headerRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

                // Data rows
                $dataCount = $this->debitNote->items()->count();
                $firstDataRow = $headerRow + 1;
                $lastDataRow = $headerRow + $dataCount;

                // Borders only for actual table
                if ($dataCount > 0) {
                    $sheet->getStyle("A{$headerRow}:N{$lastDataRow}")
                        ->getBorders()->getAllBorders()
                        ->setBorderStyle(Border::BORDER_THIN);
                }

                // TOTAL row
                $totalRow = $lastDataRow + 1;
                $sheet->setCellValue('G' . $totalRow, 'TOTAL');
                if ($dataCount > 0) {
                    $sheet->setCellValue('H' . $totalRow, "=SUM(H{$firstDataRow}:H{$lastDataRow})");
                } else {
                    $sheet->setCellValue('H' . $totalRow, 0);
                }
                $sheet->getStyle('G' . $totalRow . ':H' . $totalRow)->getFont()->setBold(true);

                // Footer with dynamic data in same cell
                $footerRow = $totalRow + 2;
                $preparedBy = $this->debitNote->creator; // relation to user
                $preparedName = $preparedBy->name ?? '';
                $preparedPosition = $preparedBy->defaultPosition->title ?? '';
                $preparedDate = $this->debitNote->created_at
                    ? Carbon::parse($this->debitNote->created_at)->format('M d, Y')
                    : '';

                $sheet->setCellValue('A' . $footerRow, 'Prepared by');
                $sheet->setCellValue('A' . ($footerRow + 3), 'Name: ' . $preparedName);
                $sheet->setCellValue('A' . ($footerRow + 4), 'Position: ' . $preparedPosition);
                $sheet->setCellValue('A' . ($footerRow + 5), 'Date: ' . $preparedDate);
            },
        ];
    }
}
