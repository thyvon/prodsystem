<?php

namespace App\Jobs;

use App\Exports\DebitNoteItemsExport;
use App\Models\DebitNote;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Facades\Excel;

class SendDebitNotesEmailJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $debitNotes;
    protected $userId;
    protected $logoPath;
    protected $allowResend;

    public function __construct($debitNotes, $userId, $logoPath, bool $allowResend = false)
    {
        $this->debitNotes = $debitNotes;
        $this->userId    = $userId;
        $this->logoPath  = $logoPath;
        $this->allowResend = $allowResend;
    }

    public function handle()
    {
        $successCount = 0;
        $failedNotes  = [];
        $originalStatuses = [];

        $noteIds = collect($this->debitNotes)->pluck('id')->unique()->values()->all();

        $notes = DB::transaction(function () use ($noteIds, &$originalStatuses) {
            $query = DebitNote::with(['debitNoteEmail', 'department.division', 'campus', 'warehouse', 'creator.defaultPosition'])
                ->whereIn('id', $noteIds)
                ->lockForUpdate();

            if ($this->allowResend) {
                $query->where('status', '!=', 'sending');
            } else {
                $query->where('status', 'pending');
            }

            $lockedNotes = $query->get();

            $originalStatuses = $lockedNotes->pluck('status', 'id')->all();

            $lockedNotes->each(function ($note) {
                $note->update(['status' => 'sending']);
            });

            return $lockedNotes;
        });

        if ($notes->isEmpty()) {
            Cache::put("debit_note_progress_{$this->userId}", [
                'status'   => $this->allowResend
                    ? 'No debit notes available for sending.'
                    : 'No pending debit notes available for sending.',
                'finished' => true,
            ]);

            return;
        }

        $recipientGroups = [];

        foreach ($notes as $note) {
            $toEmails = collect(optional($note->debitNoteEmail)->send_to_email)
                ->map(fn ($email) => trim($email))
                ->filter()
                ->unique()
                ->values()
                ->toArray();

            $ccEmails = collect(optional($note->debitNoteEmail)->cc_to_email)
                ->map(fn ($email) => trim($email))
                ->filter()
                ->unique()
                ->values()
                ->toArray();

            if (empty($toEmails)) {
                $failedNotes[] = $note->reference_number . ' (No recipient)';
                DebitNote::where('id', $note->id)->where('status', 'sending')->update([
                    'status' => $originalStatuses[$note->id] ?? 'pending',
                ]);
                continue;
            }

            foreach ($toEmails as $recipientEmail) {
                $recipientGroups[$recipientEmail]['notes'][$note->id] = $note;
                $recipientGroups[$recipientEmail]['cc'] = array_unique(array_merge($recipientGroups[$recipientEmail]['cc'] ?? [], $ccEmails));
            }
        }

        $totalGroups = count($recipientGroups);
        $groupIndex = 0;

        foreach ($recipientGroups as $recipientEmail => $group) {
            $groupIndex++;
            $groupNotes = collect($group['notes'])->values();
            $ccEmails = array_values($group['cc'] ?? []);
            $ccEmails = array_values(array_diff($ccEmails, [$recipientEmail]));

            Cache::put("debit_note_progress_{$this->userId}", [
                'status'   => "Sending email " . $groupIndex . " of " . $totalGroups . " to {$recipientEmail}",
                'finished' => false,
            ]);

            try {
                $emailSummary = $this->buildEmailSummary($groupNotes);

                Mail::send(
                    'Inventory.debit-note.email-template',
                    [
                        'notes' => $groupNotes,
                        'note' => $groupNotes->first(),
                        'emailSummary' => $emailSummary,
                    ],
                    function ($message) use ($recipientEmail, $ccEmails, $groupNotes) {
                        $message->from(
                            config('mail.from.address'),
                            config('mail.from.name')
                        );

                        $message->to($recipientEmail);

                        if (!empty($ccEmails)) {
                            $message->cc($ccEmails);
                        }

                        $departmentNames = $groupNotes->map(function ($note) {
                            $divisionShortName = $note->department?->division?->short_name;
                            $departmentShortName = $note->department?->short_name;

                            if (!$departmentShortName) {
                                return null;
                            }

                            return $divisionShortName
                                ? "{$departmentShortName} ({$divisionShortName})"
                                : $departmentShortName;
                        })->filter()->unique()->values();

                        $campusNames = $groupNotes->pluck('campus.short_name')
                            ->filter()
                            ->unique()
                            ->values();

                        $periodLabels = $groupNotes->map(function ($note) {
                            if (empty($note->start_date)) {
                                return null;
                            }

                            return Carbon::parse($note->start_date)->format('M-y');
                        })->filter()->unique()->values();

                        $subjectBase = $groupNotes->count() === 1
                            ? 'Monthly Debit Note'
                            : 'Monthly Debit Notes';

                        if ($periodLabels->isNotEmpty()) {
                            $subjectBase .= ' ' . $periodLabels->implode(', ');
                        }

                        $subjectParts = $groupNotes->map(function ($note) {
                            $divisionShortName = $note->department?->division?->short_name;
                            $departmentShortName = $note->department?->short_name;
                            $campusShortName = $note->campus?->short_name;

                            if (!$departmentShortName || !$campusShortName) {
                                return null;
                            }

                            $departmentLabel = $divisionShortName
                                ? "{$departmentShortName} ({$divisionShortName})"
                                : $departmentShortName;

                            return "{$departmentLabel} - Campus ({$campusShortName})";
                        })->filter()->unique()->values();

                        if ($departmentNames->count() === 1) {
                            $subject = $campusNames->count() <= 1
                                ? "{$subjectBase} for {$departmentNames->first()} - Campus ({$campusNames->first()})"
                                : "{$subjectBase} for {$departmentNames->first()} - Campus ({$campusNames->implode(', ')})";
                        } else {
                            $subject = $subjectParts->count() === 1
                                ? "{$subjectBase} for {$subjectParts->first()}"
                                : "{$subjectBase} for " . $subjectParts->implode(', ');
                        }

                        $message->subject($subject);

                        foreach ($groupNotes as $note) {
                            $excelContent = Excel::raw(
                                new DebitNoteItemsExport($note, $this->logoPath),
                                \Maatwebsite\Excel\Excel::XLSX
                            );

                            $fileName = "DebitNote_{$note->department->short_name}_{$note->campus->short_name}_{$note->reference_number}.xlsx";

                            $message->attachData(
                                $excelContent,
                                $fileName,
                                ['mime' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet']
                            );
                        }
                    }
                );

                foreach ($groupNotes as $note) {
                    $note->update([
                        'status'    => 'sent',
                        'send_date' => now(),
                    ]);
                }

                $successCount += $groupNotes->count();

            } catch (\Throwable $e) {
                foreach ($groupNotes as $note) {
                    DebitNote::where('id', $note->id)->where('status', 'sending')->update([
                        'status' => $originalStatuses[$note->id] ?? 'pending',
                    ]);
                }

                $failedNotes[] = implode(', ', $groupNotes->pluck('reference_number')->all()) . ' (' . $e->getMessage() . ')';
            }
        }

        Cache::put("debit_note_progress_{$this->userId}", [
            'status'   => "Finished. Success: {$successCount}, Failed: " . count($failedNotes),
            'finished' => true,
        ]);
    }

    private function buildEmailSummary(Collection $groupNotes): array
    {
        $year = now()->year;
        $noteIds = $groupNotes->pluck('id')->filter()->unique()->values();
        $emailConfigIds = $groupNotes->pluck('debit_note_email_id')->filter()->unique()->values();

        $noteTotals = DB::table('debit_note_items')
            ->select('debit_note_id', DB::raw('COALESCE(SUM(total_price), 0) as total_amount'))
            ->whereIn('debit_note_id', $noteIds)
            ->groupBy('debit_note_id')
            ->pluck('total_amount', 'debit_note_id')
            ->map(fn ($amount) => round((float) $amount, 2));

        $groupTotal = round($noteTotals->sum(), 2);

        $yearlyNoteTotals = DB::table('debit_notes')
            ->leftJoin('debit_note_items', 'debit_notes.id', '=', 'debit_note_items.debit_note_id')
            ->select(
                'debit_notes.id',
                'debit_notes.start_date',
                'debit_notes.end_date',
                DB::raw('COALESCE(SUM(debit_note_items.total_price), 0) as total_amount')
            )
            ->whereIn('debit_notes.debit_note_email_id', $emailConfigIds)
            ->where(function ($query) use ($year) {
                $query->whereYear('debit_notes.start_date', $year)
                    ->orWhere(function ($fallbackQuery) use ($year) {
                        $fallbackQuery->whereNull('debit_notes.start_date')
                            ->whereYear('debit_notes.end_date', $year);
                    });
            })
            ->groupBy('debit_notes.id', 'debit_notes.start_date', 'debit_notes.end_date')
            ->get();

        $monthlyTotals = array_fill(1, 12, 0.0);

        foreach ($yearlyNoteTotals as $yearlyNoteTotal) {
            $periodDate = $yearlyNoteTotal->start_date ?: $yearlyNoteTotal->end_date;

            if (!$periodDate) {
                continue;
            }

            $monthNumber = (int) Carbon::parse($periodDate)->format('n');
            $monthlyTotals[$monthNumber] += (float) $yearlyNoteTotal->total_amount;
        }

        $yearTotal = round(array_sum($monthlyTotals), 2);
        $peakAmount = round(max($monthlyTotals), 2);
        $activeMonthCount = collect($monthlyTotals)->filter(fn ($amount) => $amount > 0)->count();
        $averageMonthlyAmount = $activeMonthCount > 0
            ? round($yearTotal / $activeMonthCount, 2)
            : 0.0;

        $chart = collect(range(1, 12))->map(function (int $monthNumber) use ($monthlyTotals, $peakAmount) {
            $amount = round($monthlyTotals[$monthNumber], 2);

            return [
                'label' => Carbon::createFromDate(null, $monthNumber, 1)->format('M'),
                'amount' => $amount,
                'percentage' => $peakAmount > 0 && $amount > 0
                    ? max(12, (int) round(($amount / $peakAmount) * 100))
                    : 0,
                'is_current_month' => $monthNumber === now()->month,
            ];
        })->all();

        return [
            'year' => $year,
            'note_count' => $groupNotes->count(),
            'group_total' => $groupTotal,
            'year_total' => $yearTotal,
            'current_month_total' => round($monthlyTotals[now()->month] ?? 0, 2),
            'average_monthly_amount' => $averageMonthlyAmount,
            'peak_month_amount' => $peakAmount,
            'note_totals' => $noteTotals->all(),
            'chart' => $chart,
        ];
    }
}
