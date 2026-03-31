<?php

namespace App\Jobs;

use App\Exports\DebitNoteItemsExport;
use App\Models\DebitNote;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Maatwebsite\Excel\Facades\Excel;

class SendDebitNotesEmailJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $debitNotes;
    protected $userId;
    protected $logoPath;

    public function __construct($debitNotes, $userId, $logoPath)
    {
        $this->debitNotes = $debitNotes;
        $this->userId    = $userId;
        $this->logoPath  = $logoPath;
    }

    public function handle()
    {
        $successCount = 0;
        $failedNotes  = [];

        $noteIds = collect($this->debitNotes)->pluck('id')->unique()->values()->all();

        $notes = DB::transaction(function () use ($noteIds) {
            $lockedNotes = DebitNote::with(['debitNoteEmail', 'department', 'campus', 'warehouse'])
                ->whereIn('id', $noteIds)
                ->where('status', 'pending')
                ->lockForUpdate()
                ->get();

            $lockedNotes->each(function ($note) {
                $note->update(['status' => 'sending']);
            });

            return $lockedNotes;
        });

        if ($notes->isEmpty()) {
            Cache::put("debit_note_progress_{$this->userId}", [
                'status'   => 'No pending debit notes available for sending.',
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
                DebitNote::where('id', $note->id)->where('status', 'sending')->update(['status' => 'pending']);
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
                Mail::send(
                    'Inventory.debit-note.email-template',
                    [
                        'notes' => $groupNotes,
                        'note' => $groupNotes->first(),
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

                        $subjectParts = $groupNotes->map(function ($note) {
                            return "{$note->department->short_name} - {$note->campus->short_name}";
                        })->unique()->values()->all();

                        $subject = count($subjectParts) === 1
                            ? "Monthly Debit Note for {$subjectParts[0]}"
                            : "Monthly Debit Notes for " . implode(', ', $subjectParts);

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
                    DebitNote::where('id', $note->id)->where('status', 'sending')->update(['status' => 'pending']);
                }

                $failedNotes[] = implode(', ', $groupNotes->pluck('reference_number')->all()) . ' (' . $e->getMessage() . ')';
            }
        }

        Cache::put("debit_note_progress_{$this->userId}", [
            'status'   => "Finished. Success: {$successCount}, Failed: " . count($failedNotes),
            'finished' => true,
        ]);
    }
}
