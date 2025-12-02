<?php

namespace App\Exports;

use Illuminate\Database\Eloquent\Builder;
use Maatwebsite\Excel\Concerns\{
    FromQuery,
    WithHeadings,
    WithMapping,
    WithStyles,
    WithColumnWidths,
    WithColumnFormatting,
    WithDrawings,
    WithCustomStartCell,
    WithEvents
};
use Carbon\Carbon;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
use PhpOffice\PhpSpreadsheet\Worksheet\Drawing;
use PhpOffice\PhpSpreadsheet\Style\Alignment;

use App\Models\Warehouse;
use App\Models\Campus;
use App\Models\Department;

class StockIssueItemsExport implements FromQuery, WithHeadings, WithMapping, WithStyles, WithColumnWidths, WithColumnFormatting, WithDrawings, WithCustomStartCell, WithEvents
{
    protected Builder $query;
    protected array $filters;

    public function __construct(Builder $query, array $filters = [])
    {
        $this->query = $query;

        // Set default filters
        $filters['start_date'] = $filters['start_date'] ?? Carbon::now()->startOfMonth()->format('Y-m-d');
        $filters['end_date']   = $filters['end_date'] ?? Carbon::now()->endOfMonth()->format('Y-m-d');
        $filters['transaction_type'] = $filters['transaction_type'] ?? ['Issue'];

        $this->filters = $filters;
    }

    public function query()
    {
        $q = $this->query->with([
            'productVariant.product.unit',
            'stockIssue.warehouse.building.campus',
            'campus',
            'department',
            'department.division',
            'stockIssue.requestedBy',
        ]);

        // Apply filters
        if (!empty($this->filters['start_date'])) {
            $q->whereHas('stockIssue', fn($sub) => $sub->whereDate('transaction_date', '>=', $this->filters['start_date']));
        }
        if (!empty($this->filters['end_date'])) {
            $q->whereHas('stockIssue', fn($sub) => $sub->whereDate('transaction_date', '<=', $this->filters['end_date']));
        }
        if (!empty($this->filters['warehouse_ids'])) {
            $q->whereHas('stockIssue', fn($sub) => $sub->whereIn('warehouse_id', $this->filters['warehouse_ids']));
        }
        if (!empty($this->filters['campus_ids'])) {
            $q->whereIn('campus_id', $this->filters['campus_ids']);
        }
        if (!empty($this->filters['department_ids'])) {
            $q->whereIn('department_id', $this->filters['department_ids']);
        }
        if (!empty($this->filters['transaction_type'])) {
            $q->whereHas('stockIssue', fn($sub) => $sub->whereIn('transaction_type', $this->filters['transaction_type']));
        }

        return $q;
    }

    public function headings(): array
    {
        return [
            'Transaction Date',
            'Reference Number',
            'Product Code',
            'Description',
            'Quantity',
            'Unit',
            'Unit Price',
            'Total Amount',
            'Requester',
            'Campus',
            'Division',
            'Department',
            'Purpose',
            'Transaction Type',
            'Warehouse',
            'Remarks',
        ];
    }

    public function map($item): array
    {
        $productName = $item->productVariant->product->name ?? '';
        $variantDescription = $item->productVariant->description ?? '';
        $description = trim($productName . ' ' . $variantDescription);

        return [
            $item->stockIssue->transaction_date ?? null,          // Transaction Date
            $item->stockIssue->reference_no ?? null,             // Reference Number
            $item->productVariant->item_code ?? null,            // Product Code
            $description,                                        // Description
            $item->quantity,                                     // Quantity
            $item->productVariant->product->unit->name ?? null,  // Unit
            $item->unit_price,                                   // Unit Price
            $item->total_price,                                  // Total Amount
            $item->stockIssue->requestedBy->name ?? null,       // Requester
            $item->campus->short_name ?? null,                  // Campus
            $item->department->division->short_name ?? null,    // Division
            $item->department->short_name ?? null,              // Department
            $item->stockIssue->remarks ?? null,                 // Purpose
            $item->stockIssue->transaction_type ?? null,        // Transaction Type
            $item->stockIssue->warehouse->name ?? null,         // Warehouse
            $item->remarks,                                     // Remarks
        ];
    }

    public function startCell(): string
    {
        return 'A5';
    }

    public function drawings()
    {
        $drawing = new Drawing();
        $drawing->setName('Company Logo');
        $drawing->setDescription('Logo');
        $drawing->setPath(public_path('img/logo/logo-dark.png'));
        $drawing->setHeight(50);
        $drawing->setCoordinates('A1');
        return $drawing;
    }

    public function styles(Worksheet $sheet)
    {
        // Header row
        $sheet->getStyle('A5:P5')->applyFromArray([
            'font' => [
                'bold' => true,
                'color' => ['rgb' => 'FFFFFF'],
            ],
            'fill' => [
                'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                'startColor' => ['rgb' => '0B3D91'],
            ],
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_CENTER,
            ],
        ]);

        $sheet->freezePane('A6'); // Freeze header

        return $sheet;
    }

    public function columnWidths(): array
    {
        return [
            'A' => 20, 'B' => 20, 'C' => 15, 'D' => 40, 'E' => 15,
            'F' => 12, 'G' => 15, 'H' => 18, 'I' => 20, 'J' => 20,
            'K' => 20, 'L' => 20, 'M' => 30, 'N' => 18, 'O' => 20, 'P' => 30,
        ];
    }

    public function columnFormats(): array
    {
        return [
            'A' => NumberFormat::FORMAT_DATE_YYYYMMDD,
            'E' => NumberFormat::FORMAT_NUMBER_00,
            'G' => '0.0000',
            'H' => '0.0000',
        ];
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();

                // Title
                $sheet->setCellValue('A2', 'Stock Issue Items Report');
                $sheet->mergeCells('A2:P2');
                $sheet->getStyle('A2')->getFont()->setBold(true)->setSize(14);
                $sheet->getStyle('A2')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

                // Filter parameters
                $filterText = 'Filters: ';
                if (!empty($this->filters['start_date'])) $filterText .= "Start: {$this->filters['start_date']} ";
                if (!empty($this->filters['end_date'])) $filterText .= "End: {$this->filters['end_date']} ";
                if (!empty($this->filters['warehouse_ids'])) {
                    $filterText .= "Warehouses: " . implode(', ', Warehouse::whereIn('id', $this->filters['warehouse_ids'])->pluck('name')->toArray()) . ' ';
                }
                if (!empty($this->filters['campus_ids'])) {
                    $filterText .= "Campuses: " . implode(', ', Campus::whereIn('id', $this->filters['campus_ids'])->pluck('short_name')->toArray()) . ' ';
                }
                if (!empty($this->filters['department_ids'])) {
                    $filterText .= "Departments: " . implode(', ', Department::whereIn('id', $this->filters['department_ids'])->pluck('short_name')->toArray()) . ' ';
                }

                $sheet->setCellValue('A3', $filterText);
                $sheet->mergeCells('A3:P3');
                $sheet->getStyle('A3')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

                // Add total row
                $highestRow = $sheet->getHighestRow();
                $totalRow = $highestRow + 1;

                $sheet->setCellValue("E{$totalRow}", 'TOTAL');
                $sheet->mergeCells("E{$totalRow}:E{$totalRow}");
                $sheet->getStyle("E{$totalRow}")->getFont()->setBold(true);
                $sheet->getStyle("E{$totalRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

                // Sum numeric columns
                $sheet->setCellValue("E{$totalRow}", 'TOTAL');
                $sheet->setCellValue("H{$totalRow}", "=SUM(H6:H{$highestRow})"); // Total Amount
                $sheet->setCellValue("E{$totalRow}", "=SUM(E6:E{$highestRow})"); // Quantity

                // Bold sums
                $sheet->getStyle("E{$totalRow}:H{$totalRow}")->getFont()->setBold(true);
                $sheet->getStyle("E{$totalRow}:H{$totalRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
            },
        ];
    }
}
