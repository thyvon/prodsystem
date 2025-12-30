<?php

namespace App\Imports;

use App\Models\{
    DebitNoteEmail,
    Department,
    Warehouse
};
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

class DebitNoteEmailImport implements ToCollection, WithHeadingRow
{
    public function collection(Collection $rows)
    {
        foreach ($rows as $row) {

            $departmentName = trim($row['department'] ?? '');
            $warehouseName  = trim($row['warehouse'] ?? '');

            if (!$departmentName || !$warehouseName) {
                continue; // skip invalid row
            }

            $departmentId = Department::where('short_name', $departmentName)->value('id');
            $warehouseId  = Warehouse::where('name', $warehouseName)->value('id');

            if (!$departmentId || !$warehouseId) {
                continue; // skip if name not found
            }

            DebitNoteEmail::updateOrCreate(
                [
                    'department_id' => $departmentId,
                    'warehouse_id'  => $warehouseId,
                ],
                [
                    'receiver_name' => $this->receiverName($row['receiver_name'] ?? null),
                    'send_to_email' => $this->emails($row['send_to_email'] ?? null),
                    'cc_to_email'   => $this->emails($row['cc_to_email'] ?? null),
                ]
            );
        }
    }

    private function emails(?string $value): ?array
    {
        if (!$value) return null;

        return array_values(array_filter(
            array_map('trim', explode(',', $value))
        ));
    }
    private function receiverName(?string $value): ?string
    {
        return $value ? trim($value) : null;
    }
}
