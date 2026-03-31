<?php

namespace App\Imports;

use App\Models\{
    DebitNoteEmail,
    Department,
    Warehouse,
    Campus,
};
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithValidation;
use Maatwebsite\Excel\Concerns\SkipsOnFailure;
use Maatwebsite\Excel\Validators\Failure;

class DebitNoteEmailImport implements ToCollection, WithHeadingRow, WithValidation
{
    private array $errors = [];

    public function collection(Collection $rows)
    {
        foreach ($rows as $index => $row) {
            $rowNumber = $index + 2; // +2 to account for heading row

            $departmentName = trim($row['department'] ?? '');
            $warehouseName  = trim($row['warehouse'] ?? '');
            $campusName     = trim($row['campus'] ?? '');

            $departmentId = $departmentName
                ? Department::where('short_name', $departmentName)->value('id')
                : null;

            $warehouseId = $warehouseName
                ? Warehouse::where('name', $warehouseName)->value('id')
                : null;

            $campusId = $campusName
                ? Campus::where('short_name', $campusName)->value('id')
                : null;

            if (!$departmentId) {
                $this->errors[] = "Row {$rowNumber}: Department '{$departmentName}' not found.";
            }

            if (!$warehouseId) {
                $this->errors[] = "Row {$rowNumber}: Warehouse '{$warehouseName}' not found.";
            }

            if (!$campusId) {
                $this->errors[] = "Row {$rowNumber}: Campus '{$campusName}' not found.";
            }

            $sendToEmails = $this->emails($row['send_to_email'] ?? null);
            if ($sendToEmails) {
                foreach ($sendToEmails as $email) {
                    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                        $this->errors[] = "Row {$rowNumber}: send_to_email contains invalid email '{$email}'.";
                    }
                }
            }

            $ccToEmails = $this->emails($row['cc_to_email'] ?? null);
            if ($ccToEmails) {
                foreach ($ccToEmails as $email) {
                    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                        $this->errors[] = "Row {$rowNumber}: cc_to_email contains invalid email '{$email}'.";
                    }
                }
            }
        }

        if (!empty($this->errors)) {
            throw new \Illuminate\Validation\ValidationException(
                validator: validator([], []),
                response: response()->json([
                    'message' => 'Import failed due to validation errors.',
                    'errors'  => $this->errors,
                ], 422)
            );
        }

        // All rows are valid — proceed with import
        foreach ($rows as $row) {
            $departmentId = Department::where('short_name', trim($row['department']))->value('id');
            $warehouseId  = Warehouse::where('name', trim($row['warehouse']))->value('id');
            $campusId     = Campus::where('short_name', trim($row['campus']))->value('id');

            DebitNoteEmail::updateOrCreate(
                [
                    'department_id' => $departmentId,
                    'warehouse_id'  => $warehouseId,
                    'campus_id'     => $campusId,
                ],
                [
                    'receiver_name' => $this->receiverName($row['receiver_name'] ?? null),
                    'send_to_email' => $this->emails($row['send_to_email'] ?? null),
                    'cc_to_email'   => $this->emails($row['cc_to_email'] ?? null),
                ]
            );
        }
    }

    public function rules(): array
    {
        return [
            'department'     => ['required', 'string'],
            'warehouse'      => ['required', 'string'],
            'campus'         => ['required', 'string'],
            'receiver_name'  => ['nullable', 'string'],
            'send_to_email'  => ['nullable', 'string'],
            'cc_to_email'    => ['nullable', 'string'],
        ];
    }

    public function customValidationMessages(): array
    {
        return [
            'department.required'  => 'The department field is required.',
            'warehouse.required'   => 'The warehouse field is required.',
            'campus.required'      => 'The campus field is required.',
        ];
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
