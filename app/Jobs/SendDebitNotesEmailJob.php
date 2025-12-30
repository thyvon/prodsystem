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
        $this->userId = $userId;
        $this->logoPath = $logoPath;
    }

    public function handle()
    {
        $successCount = 0;
        $failedNotes = [];

        foreach ($this->debitNotes as $index => $note) {
            Cache::put("debit_note_progress_{$this->userId}", [
                'status' => "Sending " . ($index + 1) . " of " . count($this->debitNotes) . ": " . $note->reference_number,
                'finished' => false,
            ]);

            $toEmails = optional($note->debitNoteEmail)->send_to_email
                ? array_map('trim', explode(',', $note->debitNoteEmail->send_to_email))
                : null;

            $ccEmails = optional($note->debitNoteEmail)->cc_to_email
                ? array_map('trim', explode(',', $note->debitNoteEmail->cc_to_email))
                : [];

            if (!$toEmails) {
                $failedNotes[] = $note->reference_number . ' (No recipient)';
                continue;
            }

            try {
                $excelContent = Excel::raw(
                    new DebitNoteItemsExport($note, $this->logoPath),
                    \Maatwebsite\Excel\Excel::XLSX
                );

                Mail::send(
                    'Inventory.debit-note.email-template',
                    ['note' => $note],
                    function ($message) use ($toEmails, $ccEmails, $note, $excelContent) {
                        $message->from(config('mail.from.address'), config('mail.from.name'));
                        $message->to($toEmails)
                            ->cc($ccEmails)
                            ->subject("Debit Note: {$note->reference_number}")
                            ->attachData(
                                $excelContent,
                                "DebitNote_{$note->reference_number}.xlsx",
                                ['mime' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet']
                            );
                    }
                );

                $note->update(['status' => 'sent', 'send_date' => now()]);
                $successCount++;
            } catch (\Throwable $e) {
                $failedNotes[] = $note->reference_number . ' (' . $e->getMessage() . ')';
            }
        }

        // Final cache update
        Cache::put("debit_note_progress_{$this->userId}", [
            'status' => "Finished. Success: {$successCount}, Failed: ".count($failedNotes),
            'finished' => true,
        ]);
    }
}

