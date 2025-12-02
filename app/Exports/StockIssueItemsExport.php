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
    protected $query;
    protected $filters;

    public function __construct(Builder $query, array $filters = [])
    {
        $this->query = $query;

        $filters['start_date'] = $filters['start_date'] ?? Carbon::now()->startOfMonth()->format('Y-m-d');
        $filters['end_date']   = $filters['end_date'] ?? Carbon::now()->endOfMonth()->format('Y-m-d');
        $filters['transaction_type'] = $filters['transaction_type'] ?? 'Issue';
        
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
            $q->whereHas('stockIssue', fn($sub) => $sub->where('transaction_type', $this->filters['transaction_type']));
        }

        return $q;
    }

    public function headings(): array
    {
        return [
            'Reference Number',
            'Transaction Date',
            'Transaction Type',
            'Warehouse',
            'Campus',
            'Department',
            'Division',
            'Requester',
            'Product Code',
            'Description',
            'Unit',
            'Quantity',
            'Unit Price',
            'Total Amount',
            'Purpose',
            'Remarks',
        ];
    }

    public function map($item): array
    {
        $productName = $item->productVariant->product->name ?? '';
        $variantDescription = $item->productVariant->description ?? '';
        $description = trim($productName . ' ' . $variantDescription);

        return [
            $item->stockIssue->reference_no ?? null,
            $item->stockIssue->transaction_date ?? null,
            $item->stockIssue->transaction_type ?? null,
            $item->stockIssue->warehouse->name ?? null,
            $item->campus->short_name ?? null,
            $item->department->short_name ?? null,
            $item->department->division->short_name ?? null,
            $item->stockIssue->requestedBy->name ?? null,
            $item->productVariant->item_code ?? null,
            $description,
            $item->productVariant->product->unit->name ?? null,
            $item->quantity,
            $item->unit_price,
            $item->total_price,
            $item->stockIssue->remarks ?? null,
            $item->remarks,
        ];
    }

    public function startCell(): string
    {
        return 'A5'; // Table header starts at row 5
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
        // Header row style
        $sheet->getStyle('A5:P5')->applyFromArray([
            'font' => ['bold' => true],
            'fill' => ['fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID, 'startColor' => ['rgb' => 'FFD700']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
        ]);

        $sheet->freezePane('A6'); // Freeze header row

        return $sheet;
    }

    public function columnWidths(): array
    {
        return [
            'A' => 20, 'B' => 15, 'C' => 15, 'D' => 20, 'E' => 15,
            'F' => 15, 'G' => 15, 'H' => 20, 'I' => 15, 'J' => 40,
            'K' => 12, 'L' => 12, 'M' => 15, 'N' => 18, 'O' => 30, 'P' => 30,
        ];
    }

    public function columnFormats(): array
    {
        return [
            'B' => NumberFormat::FORMAT_DATE_YYYYMMDD,
            'L' => NumberFormat::FORMAT_NUMBER_00,
            'M' => '0.0000',
            'N' => '0.0000',
        ];
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();

                // Title row
                $sheet->setCellValue('A2', 'Stock Issue Items Report');
                $sheet->mergeCells('A2:P2');
                $sheet->getStyle('A2')->getFont()->setBold(true)->setSize(14);
                $sheet->getStyle('A2')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

                // Filter parameters row (convert IDs to names here)
                $filterText = 'Filters: ';
                if (!empty($this->filters['start_date'])) $filterText .= "Start: {$this->filters['start_date']} ";
                if (!empty($this->filters['end_date'])) $filterText .= "End: {$this->filters['end_date']} ";

                // Convert IDs to names automatically
                if (!empty($this->filters['warehouse_ids'])) {
                    $warehouseNames = Warehouse::whereIn('id', $this->filters['warehouse_ids'])
                        ->pluck('name')->toArray();
                    $filterText .= "Warehouses: " . implode(', ', $warehouseNames) . ' ';
                }

                if (!empty($this->filters['campus_ids'])) {
                    $campusNames = Campus::whereIn('id', $this->filters['campus_ids'])
                        ->pluck('short_name')->toArray();
                    $filterText .= "Campuses: " . implode(', ', $campusNames) . ' ';
                }

                if (!empty($this->filters['department_ids'])) {
                    $departmentNames = Department::whereIn('id', $this->filters['department_ids'])
                        ->pluck('short_name')->toArray();
                    $filterText .= "Departments: " . implode(', ', $departmentNames) . ' ';
                }

                $sheet->setCellValue('A3', $filterText);
                $sheet->mergeCells('A3:P3');
                $sheet->getStyle('A3')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

                // Add total row at the bottom
                $highestRow = $sheet->getHighestRow();
                $sheet->setCellValue("K" . ($highestRow + 1), 'TOTAL');
                $sheet->mergeCells("K" . ($highestRow + 1) . ":K" . ($highestRow + 1));
                $sheet->getStyle("K" . ($highestRow + 1))->getFont()->setBold(true);
                $sheet->getStyle("K" . ($highestRow + 1))->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

                $sheet->setCellValue("L" . ($highestRow + 1), "=SUM(L6:L{$highestRow})");
                $sheet->setCellValue("M" . ($highestRow + 1), "=SUM(M6:M{$highestRow})");
                $sheet->setCellValue("N" . ($highestRow + 1), "=SUM(N6:N{$highestRow})");
            }
        ];
    }
}
