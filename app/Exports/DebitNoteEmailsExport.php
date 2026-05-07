<?php

namespace App\Exports;

use App\Models\DebitNoteEmail;
use Illuminate\Database\Eloquent\Builder;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class DebitNoteEmailsExport implements FromQuery, WithHeadings, WithMapping, ShouldAutoSize
{
    public function __construct(private readonly Builder $query)
    {
    }

    public function query(): Builder
    {
        return $this->query;
    }

    public function headings(): array
    {
        return [
            'department',
            'warehouse',
            'campus',
            'receiver_name',
            'send_to_email',
            'cc_to_email',
        ];
    }

    public function map($debitNoteEmail): array
    {
        /** @var DebitNoteEmail $debitNoteEmail */
        return [
            $debitNoteEmail->department?->short_name,
            $debitNoteEmail->warehouse?->name,
            $debitNoteEmail->campus?->short_name,
            $debitNoteEmail->receiver_name,
            $this->flattenEmails($debitNoteEmail->send_to_email),
            $this->flattenEmails($debitNoteEmail->cc_to_email),
        ];
    }

    private function flattenEmails(array|string|null $emails): ?string
    {
        $values = is_array($emails)
            ? $emails
            : explode(',', (string) $emails);

        $values = array_values(array_filter(array_map('trim', $values)));

        return empty($values) ? null : implode(', ', $values);
    }
}
