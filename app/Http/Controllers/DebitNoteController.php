<?php

namespace App\Http\Controllers;

use App\Exports\DebitNoteEmailsExport;
use App\Exports\DebitNoteItemsExport;
use App\Imports\DebitNoteEmailImport;
use App\Jobs\SendDebitNotesEmailJob;
use App\Models\DebitNote;
use App\Models\DebitNoteEmail;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Maatwebsite\Excel\Facades\Excel;
use ZipArchive;

class DebitNoteController extends Controller
{
    private const DEBIT_NOTE_LIST_SORT_COLUMNS = [
        'id' => 'debit_notes.id',
        'reference_number' => 'debit_notes.reference_number',
        'warehouse_name' => 'warehouses.name',
        'campus_name' => 'campus.short_name',
        'department_name' => 'departments.short_name',
        'start_date' => 'debit_notes.start_date',
        'end_date' => 'debit_notes.end_date',
        'status' => 'debit_notes.status',
        'created_by' => 'users.name',
        'created_at' => 'debit_notes.created_at',
        'updated_at' => 'debit_notes.updated_at',
    ];

    private const DEBIT_NOTE_EMAIL_SORT_COLUMNS = [
        'campus_name' => 'campus.short_name',
        'department_name' => 'departments.short_name',
        'warehouse_name' => 'warehouses.name',
        'receiver_name' => 'debit_note_emails.receiver_name',
        'created_at' => 'debit_note_emails.created_at',
        'updated_at' => 'debit_note_emails.updated_at',
    ];

    public function debitNoteEmailIndex()
    {
        return view('Inventory.debit-note.debit-note-email-index');
    }
    // Get list of Debit Note Emails
    public function getDebitNoteEmails(Request $request): JsonResponse
    {
        $query = $this->buildDebitNoteEmailQuery($request);

        // Pagination
        $limit = max(1, (int) $request->input('limit', 10));
        $emails = $query->paginate($limit);

        // Map data for frontend
        $data = $emails->getCollection()->map(fn($item) => [
            'id' => $item->id,
            'campus_id' => $item->campus_id,
            'campus_name' => $item->campus->short_name,
            'department_id' => $item->department_id,
            'department_name' => $item->department?->short_name,
            'warehouse_id' => $item->warehouse_id,
            'warehouse_name' => $item->warehouse?->name,
            'receiver_name' => $item->receiver_name,
            'send_to_email' => $this->normalizeEmailList($item->send_to_email),
            'cc_to_email' => $this->normalizeEmailList($item->cc_to_email),
            'created_at' => $item->created_at?->toDateTimeString(),
            'updated_at' => $item->updated_at?->toDateTimeString(),
        ]);

        return response()->json([
            'data' => $data,
            'recordsTotal' => $emails->total(),
            'recordsFiltered' => $emails->total(),
            'draw' => (int) $request->input('draw', 1),
        ]);
    }

    public function exportDebitNoteEmails(Request $request)
    {
        $fileName = 'debit_note_emails_' . now()->format('Ymd_His') . '.xlsx';

        return Excel::download(
            new DebitNoteEmailsExport($this->buildDebitNoteEmailQuery($request)),
            $fileName
        );
    }

    // Import Debit Note Emails from Excel
    public function importDebitNoteEmails(Request $request): JsonResponse
    {
        $request->validate([
            'file' => 'required|file|mimes:xlsx,xls,csv',
        ]);

        Excel::import(new DebitNoteEmailImport, $request->file('file'));

        return response()->json([
            'success' => true,
            'message' => 'Imported successfully'
        ]);
    }

    // Store a new Debit Note Email
    public function storeDebitNoteEmail(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'department_id'    => 'required|exists:departments,id',
            'warehouse_id'     => 'required|exists:warehouses,id',
            'campus_id'        => 'required|exists:campus,id',
            'receiver_name'    => 'required|string',
            'send_to_email'    => 'required|array|min:1',
            'send_to_email.*'  => 'email',
            'cc_to_email'      => 'nullable|array',
            'cc_to_email.*'    => 'email',
        ]);

        $email = DebitNoteEmail::create([
            'department_id' => $validated['department_id'],
            'warehouse_id'  => $validated['warehouse_id'],
            'campus_id'     => $validated['campus_id'],
            'receiver_name' => $validated['receiver_name'],
            'send_to_email' => array_values($validated['send_to_email']),
            'cc_to_email'   => array_values($validated['cc_to_email'] ?? []),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Debit Note Email created successfully',
            'data'    => $email
        ], 201);
    }


    // Get single Debit Note Email for editing
    public function editDebitNoteEmail($id): JsonResponse
    {
        $email = DebitNoteEmail::with(['department', 'warehouse'])->findOrFail($id);

        $data = [
            'id' => $email->id,
            'department_id' => $email->department_id,
            'department_name' => $email->department?->short_name,
            'campus_id' => $email->campus_id,
            'campus_name' => $email->campus?->short_name,
            'warehouse_id' => $email->warehouse_id,
            'warehouse_name' => $email->warehouse?->name,
            'receiver_name' => $email->receiver_name,
            'send_to_email' => is_array($email->send_to_email) ? $email->send_to_email : explode(',', $email->send_to_email ?? ''),
            'cc_to_email' => is_array($email->cc_to_email) ? $email->cc_to_email : explode(',', $email->cc_to_email ?? ''),
            'created_at' => $email->created_at?->toDateTimeString(),
            'updated_at' => $email->updated_at?->toDateTimeString(),
        ];

        return response()->json([
            'success' => true,
            'data' => $data
        ]);
    }

    // Update a Debit Note Email
    public function updateDebitNoteEmail(Request $request, $id): JsonResponse
    {
        $validated = $request->validate([
            'department_id'    => 'required|exists:departments,id',
            'warehouse_id'     => 'required|exists:warehouses,id',
            'campus_id'        => 'required|exists:campus,id',
            'receiver_name'    => 'required|string',
            'send_to_email'    => 'required|array|min:1',
            'send_to_email.*'  => 'email',
            'cc_to_email'      => 'nullable|array',
            'cc_to_email.*'    => 'email',
        ]);

        $email = DebitNoteEmail::findOrFail($id);

        $email->update([
            'department_id' => $validated['department_id'],
            'warehouse_id'  => $validated['warehouse_id'],
            'campus_id'     => $validated['campus_id'],
            'receiver_name' => $validated['receiver_name'],
            'send_to_email' => array_values($validated['send_to_email']),
            'cc_to_email'   => array_values($validated['cc_to_email'] ?? []),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Debit Note Email updated successfully',
            'data'    => $email
        ]);
    }

    public function deleteDebitNoteEmail(Request $request, $id): JsonResponse
    {
        $email = DebitNoteEmail::findOrFail($id);
        $email->delete();
        return response()->json([
            'success' => true,
            'message' => 'Debit Note Email deleted successfully',
        ]);
    }


    // Debit Note
    public function debitNoteIndex()
    {
        return view('Inventory.debit-note.debit-note-index');
    }

    public function getDebitNoteList(Request $request): JsonResponse
    {
        $validated = $this->validateDebitNoteListFilters($request);
        $limit = $validated['limit'] ?? 10;
        $page = $validated['page'] ?? 1;
        $query = $this->buildDebitNoteListQuery($validated);

        // ----------------------------
        // PAGINATION
        // ----------------------------
        $debitNotes = $query->paginate($limit, ['*'], 'page', $page);

        // ----------------------------
        // MAP DATA
        // ----------------------------
        $debitNotesMapped = $debitNotes->map(fn($note) => [
            'id' => $note->id,
            'reference_number' => $note->reference_number,
            'campus_name' => $note->campus->short_name ?? null,
            'warehouse_name' => $note->warehouse->name ?? null,
            'department_name' => $note->department->short_name ?? null,
            'debit_note_email' => isset($note->debitNoteEmail->send_to_email)
                ? str_replace(',', ' ', $note->debitNoteEmail->send_to_email)
                : null,
            'cc_email' => isset($note->debitNoteEmail->cc_to_email)
                ? str_replace(',', ' ', $note->debitNoteEmail->cc_to_email)
                : null,
            'start_date' => $note->start_date,
            'end_date' => $note->end_date,
            'status' => $note->status,
            'total_items' => $note->items->count(),
            'total_price' => number_format(
                $note->items->sum(fn($i) => $i->stockIssueItem->total_price ?? 0),
                4,
                '.',
                ''
            ),
            'created_by' => $note->creator->name ?? null,
            'created_at' => $note->created_at,
            'updated_at' => $note->updated_at,
        ]);

        // ----------------------------
        // RESPONSE
        // ----------------------------
        return response()->json([
            'data' => $debitNotesMapped,
            'recordsTotal' => $debitNotes->total(),
            'recordsFiltered' => $debitNotes->total(),
            'draw' => (int) ($validated['draw'] ?? 1),
        ]);
    }

    public function exportDebitNotesBulk(Request $request)
    {
        $validated = $this->validateDebitNoteListFilters($request);
        $query = $this->buildDebitNoteListQuery($validated);
        $debitNotes = $query->get();

        if ($debitNotes->isEmpty()) {
            return response()->json([
                'message' => 'No debit notes found for the selected filters.'
            ], 404);
        }

        $logoPath = public_path('img/logo/logo-dark.png');
        $tempDirectory = storage_path('app/temp');

        if (!is_dir($tempDirectory) && !mkdir($tempDirectory, 0777, true) && !is_dir($tempDirectory)) {
            return response()->json([
                'message' => 'Failed to prepare temporary export directory.'
            ], 500);
        }

        $zipFileName = 'debit_notes_' . now()->format('Ymd_His') . '.zip';
        $zipPath = $tempDirectory . DIRECTORY_SEPARATOR . $zipFileName;

        $zip = new ZipArchive();
        if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            return response()->json([
                'message' => 'Failed to create ZIP file.'
            ], 500);
        }

        $usedNames = [];

        foreach ($debitNotes as $note) {
            $excelContent = Excel::raw(
                new DebitNoteItemsExport($note, $logoPath),
                \Maatwebsite\Excel\Excel::XLSX
            );

            $fileName = $this->makeUniqueFileName(
                $this->makeDebitNoteExportFileName($note),
                $usedNames
            );

            $zip->addFromString($fileName, $excelContent);
        }

        $zip->close();

        return response()->download($zipPath, $zipFileName)->deleteFileAfterSend(true);
    }

    // public function sendDebitNoteEmails(Request $request): JsonResponse
    // {
    //     $validated = $request->validate([
    //         'warehouse_ids'    => 'required|array|min:1',
    //         'warehouse_ids.*'  => 'exists:warehouses,id',
    //         'department_ids'   => 'nullable|array',
    //         'department_ids.*' => 'exists:departments,id',
    //         'start_date'       => 'required|date',
    //         'end_date'         => 'required|date|after_or_equal:start_date',
    //     ]);

    //     $user = auth()->user();
    //     if (!$user || !$user->email) {
    //         return response()->json([
    //             'message' => 'Authenticated user email is required.'
    //         ], 422);
    //     }

    //     $debitNotes = DebitNote::with(['debitNoteEmail', 'items.stockIssueItem'])
    //         ->whereIn('warehouse_id', $validated['warehouse_ids'])
    //         ->whereBetween('start_date', [$validated['start_date'], $validated['end_date']])
    //         ->where('status', 'pending')
    //         ->when(!empty($validated['department_ids']), fn($q) => $q->whereIn('department_id', $validated['department_ids']))
    //         ->get();

    //     if ($debitNotes->isEmpty()) {
    //         return response()->json([
    //             'message' => 'No pending Debit Notes found for the selected filters.'
    //         ], 404);
    //     }

    //     $logoPath = public_path('img/logo/logo-dark.png');
    //     $successCount = 0;
    //     $failedNotes = [];

    //     foreach ($debitNotes as $note) {

    //         $toEmails = optional($note->debitNoteEmail)->send_to_email ?
    //             array_map('trim', explode(',', $note->debitNoteEmail->send_to_email)) : null;

    //         $ccEmails = optional($note->debitNoteEmail)->cc_to_email ?
    //             array_map('trim', explode(',', $note->debitNoteEmail->cc_to_email)) : [];

    //         if (!$toEmails) {
    //             $failedNotes[] = $note->reference_number . ' (No recipient)';
    //             continue;
    //         }

    //         // Generate Excel
    //         try {
    //             $excelContent = Excel::raw(
    //                 new DebitNoteItemsExport($note, $logoPath),
    //                 \Maatwebsite\Excel\Excel::XLSX
    //             );
    //         } catch (Throwable $e) {
    //             return response()->json([
    //                 'message' => 'Excel export failed. Process stopped.',
    //                 'debit_note' => $note->reference_number,
    //                 'error' => $e->getMessage(),
    //             ], 500);
    //         }

    //         // Send Email
    //         try {
    //             Mail::send(
    //                 'Inventory.debit-note.email-template',
    //                 ['note' => $note],
    //                 function ($message) use ($toEmails, $ccEmails, $note, $excelContent, $user) {
    //                     // Use system email as "from" and auth user as reply-to
    //                     $message->from(config('mail.from.address'), config('mail.from.name'));
    //                     $message->replyTo($user->email, $user->name ?? 'System');
    //                     $message->to($toEmails)
    //                             ->cc($ccEmails)
    //                             ->subject("Debit Note: {$note->reference_number}")
    //                             ->attachData(
    //                                 $excelContent,
    //                                 "DebitNote_{$note->reference_number}.xlsx",
    //                                 ['mime' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet']
    //                             );
    //                 }
    //             );

    //             // Mark as sent
    //             $note->update([
    //                 'status'    => 'sent',
    //                 'send_date' => now(),
    //             ]);

    //             $successCount++;
    //         } catch (Throwable $e) {
    //             $failedNotes[] = $note->reference_number . ' (' . $e->getMessage() . ')';
    //         }
    //     }

    //     return response()->json([
    //         'message' => "Emails sent successfully for {$successCount} debit notes.",
    //         'failed'  => $failedNotes,
    //     ]);
    // }

    public function sendDebitNoteEmails(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'warehouse_ids'    => 'required|array|min:1',
            'warehouse_ids.*'  => 'exists:warehouses,id',
            'department_ids'   => 'nullable|array',
            'department_ids.*' => 'exists:departments,id',
            'campus_ids'       => 'nullable|array',
            'campus_ids.*'     => 'exists:campus,id',
            'statuses'         => 'nullable|array',
            'statuses.*'       => 'string|in:pending,sending,sent',
            'start_date'       => 'required|date',
            'end_date'         => 'required|date|after_or_equal:start_date',
        ]);

        $user = auth()->user();
        if (!$user || !$user->email) {
            return response()->json([
                'message' => 'Authenticated user email is required.'
            ], 422);
        }

        // Fetch pending debit notes
        $statuses = array_values(array_intersect($validated['statuses'] ?? ['pending'], ['pending']));

        $debitNotes = DebitNote::with('debitNoteEmail')
            ->whereIn('warehouse_id', $validated['warehouse_ids'])
            ->whereBetween('start_date', [$validated['start_date'], $validated['end_date']])
            ->whereIn('status', $statuses)
            ->when(!empty($validated['department_ids']), fn($q) => $q->whereIn('department_id', $validated['department_ids']))
            ->when(!empty($validated['campus_ids']), fn($q) => $q->whereIn('campus_id', $validated['campus_ids']))
            ->get();

        if ($debitNotes->isEmpty()) {
            return response()->json([
                'message' => 'No pending debit notes found for the selected filters.'
            ], 404);
        }

        $logoPath = public_path('img/logo/logo-dark.png');
        $progressKey = "debit_note_progress_{$user->id}";

        $currentProgress = Cache::get($progressKey);
        if (!empty($currentProgress['finished']) && $currentProgress['finished'] === false) {
            return response()->json([
                'message' => 'Email sending is already in progress. Please wait until the current batch completes.'
            ], 409);
        }

        // Clear previous progress data and start a new send operation
        Cache::forget($progressKey);

        // Dispatch job for sending emails
        SendDebitNotesEmailJob::dispatch($debitNotes, $user->id, $logoPath);

        return response()->json([
            'message' => 'Email sending started. You can track progress now.'
        ]);
    }

    public function exportDebitNote(DebitNote $debitNote)
    {
        $debitNote->loadMissing(['department', 'campus']);

        $logoPath = public_path('img/logo/logo-dark.png');
        $fileName = $this->makeDebitNoteExportFileName($debitNote);

        return Excel::download(
            new DebitNoteItemsExport($debitNote, $logoPath),
            $fileName
        );
    }

    public function destroy(DebitNote $debitNote): JsonResponse
    {
        if ($debitNote->status === 'sending') {
            return response()->json([
                'message' => 'Cannot delete a debit note while email sending is in progress.'
            ], 409);
        }

        $debitNote->delete();

        return response()->json([
            'success' => true,
            'message' => 'Debit Note deleted successfully.',
        ]);
    }

    public function resendDebitNoteEmail(DebitNote $debitNote): JsonResponse
    {
        $user = auth()->user();
        if (!$user || !$user->email) {
            return response()->json([
                'message' => 'Authenticated user email is required.'
            ], 422);
        }

        if ($debitNote->status === 'sending') {
            return response()->json([
                'message' => 'This debit note is already being sent. Please wait until the current send completes.'
            ], 409);
        }

        $debitNote->load('debitNoteEmail');

        $toEmails = collect(optional($debitNote->debitNoteEmail)->send_to_email)
            ->map(fn ($email) => trim($email))
            ->filter()
            ->unique()
            ->values();

        if ($toEmails->isEmpty()) {
            return response()->json([
                'message' => 'This debit note has no recipient email configured.'
            ], 422);
        }

        $logoPath = public_path('img/logo/logo-dark.png');
        $progressKey = "debit_note_progress_{$user->id}";

        $currentProgress = Cache::get($progressKey);
        if (!empty($currentProgress['finished']) && $currentProgress['finished'] === false) {
            return response()->json([
                'message' => 'Email sending is already in progress. Please wait until the current send completes.'
            ], 409);
        }

        Cache::forget($progressKey);

        SendDebitNotesEmailJob::dispatch(collect([$debitNote]), $user->id, $logoPath, true);

        return response()->json([
            'message' => "Email resend started for debit note {$debitNote->reference_number}."
        ]);
    }

    /**
     * Get current progress of sending debit note emails
     */
    public function getEmailProgress(Request $request): JsonResponse
    {
        $user = auth()->user();
        $progress = Cache::get("debit_note_progress_{$user->id}", [
            'status' => 'No sending in progress.',
            'finished' => true,
        ]);

        return response()->json($progress);
    }

    private function buildDebitNoteEmailQuery(Request $request): Builder
    {
        $search = trim((string) $request->input('search', ''));
        $receiverName = trim((string) $request->input('receiver_name', ''));
        $departmentIds = collect($request->input('department_ids', []))
            ->filter(fn ($id) => filled($id))
            ->map(fn ($id) => (int) $id)
            ->values()
            ->all();
        $sortKey = (string) $request->input('sortColumn', 'created_at');
        $sortColumn = self::DEBIT_NOTE_EMAIL_SORT_COLUMNS[$sortKey] ?? self::DEBIT_NOTE_EMAIL_SORT_COLUMNS['created_at'];
        $sortDirection = strtolower((string) $request->input('sortDirection', 'desc')) === 'asc' ? 'asc' : 'desc';

        return DebitNoteEmail::query()
            ->with(['department', 'warehouse', 'campus'])
            ->leftJoin('departments', 'debit_note_emails.department_id', '=', 'departments.id')
            ->leftJoin('warehouses', 'debit_note_emails.warehouse_id', '=', 'warehouses.id')
            ->leftJoin('campus', 'debit_note_emails.campus_id', '=', 'campus.id')
            ->select('debit_note_emails.*')
            ->when(!empty($departmentIds), function (Builder $query) use ($departmentIds) {
                $query->whereIn('debit_note_emails.department_id', $departmentIds);
            })
            ->when($receiverName !== '', function (Builder $query) use ($receiverName) {
                $query->where('debit_note_emails.receiver_name', 'like', "%{$receiverName}%");
            })
            ->when($search !== '', function (Builder $query) use ($search) {
                $query->where(function (Builder $subQuery) use ($search) {
                    $subQuery->where('departments.name', 'like', "%{$search}%")
                        ->orWhere('departments.short_name', 'like', "%{$search}%")
                        ->orWhere('warehouses.name', 'like', "%{$search}%")
                        ->orWhere('campus.name', 'like', "%{$search}%")
                        ->orWhere('campus.short_name', 'like', "%{$search}%")
                        ->orWhere('debit_note_emails.receiver_name', 'like', "%{$search}%")
                        ->orWhere('debit_note_emails.send_to_email', 'like', "%{$search}%")
                        ->orWhere('debit_note_emails.cc_to_email', 'like', "%{$search}%");
                });
            })
            ->orderBy($sortColumn, $sortDirection);
    }

    private function normalizeEmailList(array|string|null $emails): array
    {
        $values = is_array($emails)
            ? $emails
            : explode(',', (string) $emails);

        return array_values(array_filter(array_map('trim', $values)));
    }

    private function validateDebitNoteListFilters(Request $request): array
    {
        return $request->validate([
            'search' => 'nullable|string|max:255',
            'sortColumn' => 'nullable|string',
            'sortDirection' => 'nullable|string|in:asc,desc',
            'limit' => 'nullable|integer|min:1|max:1000',
            'page' => 'nullable|integer|min:1',
            'draw' => 'nullable|integer',
            'warehouse_ids' => 'nullable|array',
            'warehouse_ids.*' => 'integer|exists:warehouses,id',
            'campus_ids' => 'nullable|array',
            'campus_ids.*' => 'integer|exists:campus,id',
            'department_ids' => 'nullable|array',
            'department_ids.*' => 'integer|exists:departments,id',
            'statuses' => 'nullable|array',
            'statuses.*' => 'string|in:pending,sending,sent',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date',
        ]);
    }

    private function buildDebitNoteListQuery(array $validated): Builder
    {
        $sortKey = $validated['sortColumn'] ?? 'id';
        $sortColumn = self::DEBIT_NOTE_LIST_SORT_COLUMNS[$sortKey] ?? self::DEBIT_NOTE_LIST_SORT_COLUMNS['id'];
        $sortDirection = $validated['sortDirection'] ?? 'desc';

        $query = DebitNote::with([
            'warehouse:id,name',
            'campus:id,short_name',
            'department:id,short_name,name',
            'debitNoteEmail:id,send_to_email,cc_to_email',
            'creator:id,name',
            'items.stockIssueItem'
        ])
        ->when($validated['search'] ?? null, function ($q, $search) {
            $q->where(function ($subQ) use ($search) {
                $subQ->where('reference_number', 'like', "%{$search}%")
                    ->orWhereHas('warehouse', fn($wQ) => $wQ->where('name', 'like', "%{$search}%"))
                    ->orWhereHas('campus', fn($cQ) => $cQ->where('name', 'like', "%{$search}%")->orWhere('short_name', 'like', "%{$search}%"))
                    ->orWhereHas('department', fn($dQ) => $dQ->where('name', 'like', "%{$search}%")->orWhere('short_name', 'like', "%{$search}%"))
                    ->orWhereHas('creator', fn($uQ) => $uQ->where('name', 'like', "%{$search}%"))
                    ->orWhereHas('debitNoteEmail', fn($eQ) => $eQ
                        ->where('send_to_email', 'like', "%{$search}%")
                        ->orWhere('cc_to_email', 'like', "%{$search}%")
                    )
                    ->orWhere('status', 'like', "%{$search}%");

                if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $search)) {
                    $subQ->orWhereDate('start_date', $search)
                        ->orWhereDate('end_date', $search);
                }
            });
        })
        ->when(!empty($validated['warehouse_ids']), fn($q) =>
            $q->whereIn('warehouse_id', $validated['warehouse_ids'])
        )
        ->when(!empty($validated['campus_ids']), fn($q) =>
            $q->whereIn('campus_id', $validated['campus_ids'])
        )
        ->when(!empty($validated['department_ids']), fn($q) =>
            $q->whereIn('department_id', $validated['department_ids'])
        )
        ->when(!empty($validated['statuses']), fn($q) =>
            $q->whereIn('status', $validated['statuses'])
        )
        ->when(!empty($validated['start_date']), fn($q) =>
            $q->whereDate('start_date', '>=', $validated['start_date'])
        )
        ->when(!empty($validated['end_date']), fn($q) =>
            $q->whereDate('end_date', '<=', $validated['end_date'])
        );

        if ($sortKey === 'warehouse_name') {
            $query->join('warehouses', 'debit_notes.warehouse_id', '=', 'warehouses.id')
                ->orderBy('warehouses.name', $sortDirection)
                ->select('debit_notes.*');
        } elseif ($sortKey === 'campus_name') {
            $query->join('campus', 'debit_notes.campus_id', '=', 'campus.id')
                ->orderBy('campus.short_name', $sortDirection)
                ->select('debit_notes.*');
        } elseif ($sortKey === 'department_name') {
            $query->join('departments', 'debit_notes.department_id', '=', 'departments.id')
                ->orderBy('departments.short_name', $sortDirection)
                ->select('debit_notes.*');
        } elseif ($sortKey === 'created_by') {
            $query->join('users', 'debit_notes.created_by', '=', 'users.id')
                ->orderBy('users.name', $sortDirection)
                ->select('debit_notes.*');
        } else {
            $query->orderBy($sortColumn, $sortDirection);
        }

        return $query;
    }

    private function makeDebitNoteExportFileName(DebitNote $debitNote): string
    {
        $departmentShortName = $debitNote->department?->short_name ?? 'Department';
        $campusShortName = $debitNote->campus?->short_name ?? 'Campus';
        $referenceNumber = $debitNote->reference_number ?? $debitNote->id;

        $fileName = "DebitNote_{$departmentShortName}_{$campusShortName}_{$referenceNumber}.xlsx";

        return preg_replace('/[\\\\\\/:\*\?"<>\|]+/', '_', $fileName);
    }

    private function makeUniqueFileName(string $fileName, array &$usedNames): string
    {
        if (!isset($usedNames[$fileName])) {
            $usedNames[$fileName] = 1;
            return $fileName;
        }

        $usedNames[$fileName]++;
        $pathInfo = pathinfo($fileName);
        $name = $pathInfo['filename'] ?? 'DebitNote';
        $extension = isset($pathInfo['extension']) ? '.' . $pathInfo['extension'] : '';

        return $name . '_' . $usedNames[$fileName] . $extension;
    }
}
