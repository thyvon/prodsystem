<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Illuminate\Support\Collection;

class WarehouseStockPivotExport implements FromCollection, WithHeadings
{
    protected $warehouses;
    protected $rows;

    public function __construct($warehouses, $rows)
    {
        $this->warehouses = $warehouses;
        $this->rows = $rows;
    }

    public function collection()
    {
        return collect($this->rows);
    }

    public function headings(): array
    {
        $headings = ['Item Code', 'Description', 'Unit'];
        foreach ($this->warehouses as $wh) {
            $headings[] = $wh['name'];
        }
        $headings[] = 'Total';
        return $headings;
    }
}
