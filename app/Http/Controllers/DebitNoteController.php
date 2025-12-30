<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Maatwebsite\Excel\Facades\Excel;
use App\Imports\DebitNoteEmailImport;
use App\Models\DebitNoteEmail;
use App\Models\DebitNote;
use App\Models\DebitNoteItem;
use Illuminate\Support\Facades\Mail;
use App\Exports\DebitNoteItemsExport;
use Throwable;

class DebitNoteController extends Controller
{

    public function debitNoteEmailIndex()
    {
        return view('Inventory.debit-note.debit-note-email-index');
    }
    // Get list of Debit Note Emails
    public function getDebitNoteEmails(Request $request): JsonResponse
    {
        $query = DebitNoteEmail::with(['department', 'warehouse']);

        // Search
        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->whereHas('department', fn($q2) => $q2->where('name', 'like', "%{$search}%"))
                  ->orWhereHas('warehouse', fn($q2) => $q2->where('name', 'like', "%{$search}%"))
                  ->orWhere('send_to_email', 'like', "%{$search}%")
                  ->orWhere('cc_to_email', 'like', "%{$search}%");
            });
        }

        // Sorting
        $sortColumn = $request->input('sortColumn', 'created_at');
        $sortDirection = strtolower($request->input('sortDirection', 'desc'));
        $query->orderBy($sortColumn, $sortDirection);

        // Pagination
        $limit = max(1, (int) $request->input('limit', 10));
        $emails = $query->paginate($limit);

        // Map data for frontend
        $data = $emails->getCollection()->map(fn($item) => [
            'id' => $item->id,
            'department_id' => $item->department_id,
            'department_name' => $item->department?->name,
            'warehouse_id' => $item->warehouse_id,
            'warehouse_name' => $item->warehouse?->name,
            'receiver_name' => $item->receiver_name,
            'send_to_email' => implode(' ', array_map('trim', is_array($item->send_to_email) ? $item->send_to_email : explode(',', $item->send_to_email ?? ''))),
            'cc_to_email' => implode(' ', array_map('trim', is_array($item->cc_to_email) ? $item->cc_to_email : explode(',', $item->cc_to_email ?? ''))),
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
        $request->validate([
            'department_id' => 'required|exists:departments,id',
            'warehouse_id' => 'required|exists:warehouses,id',
            'receiver_name' => 'required',
            'send_to_email' => 'required|array',
            'send_to_email.*' => 'email',
            'cc_to_email' => 'nullable|array',
            'cc_to_email.*' => 'email',
        ]);

        $email = DebitNoteEmail::create([
            'department_id' => $request->input('department_id'),
            'warehouse_id' => $request->input('warehouse_id'),
            'receiver_name' => $request->input('receiver_name'),
            'send_to_email' => implode(',', $request->input('send_to_email', [])),
            'cc_to_email' => implode(',', $request->input('cc_to_email', [])),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Debit Note Email created successfully',
            'data' => $email
        ], 201);
    }

    // Get single Debit Note Email for editing
    public function editDebitNoteEmail($id): JsonResponse
    {
        $email = DebitNoteEmail::with(['department', 'warehouse'])->findOrFail($id);

        $data = [
            'id' => $email->id,
            'department_id' => $email->department_id,
            'department_name' => $email->department?->name,
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
        $request->validate([
            'department_id' => 'required|exists:departments,id',
            'warehouse_id' => 'required|exists:warehouses,id',
            'receiver_name' => 'required',
            'send_to_email' => 'required|array',
            'send_to_email.*' => 'email',
            'cc_to_email' => 'nullable|array',
            'cc_to_email.*' => 'email',
        ]);

        $email = DebitNoteEmail::findOrFail($id);

        $email->update([
            'department_id' => $request->input('department_id'),
            'warehouse_id' => $request->input('warehouse_id'),
            'receiver_name' => $request->input('receiver_name'),
            'send_to_email' => implode(',', $request->input('send_to_email', [])),
            'cc_to_email' => implode(',', $request->input('cc_to_email', [])),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Debit Note Email updated successfully',
            'data' => $email
        ]);
    }

    // Debit Note
    public function debitNoteIndex()
    {
        return view('Inventory.debit-note.debit-note-index');
    }

    public function getDebitNoteList(Request $request): JsonResponse
    {
        // ----------------------------
        // VALIDATION
        // ----------------------------
        $validated = $request->validate([
            'search' => 'nullable|string|max:255',
            'sortColumn' => 'nullable|string',
            'sortDirection' => 'nullable|string|in:asc,desc',
            'limit' => 'nullable|integer|min:1|max:1000',
            'page' => 'nullable|integer|min:1',
            'draw' => 'nullable|integer',

            // Filters from frontend
            'warehouse_ids' => 'nullable|array',
            'warehouse_ids.*' => 'integer|exists:warehouses,id',
            'department_ids' => 'nullable|array',
            'department_ids.*' => 'integer|exists:departments,id',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date',
        ]);

        $sortColumn = $validated['sortColumn'] ?? 'debit_notes.id';
        $sortDirection = $validated['sortDirection'] ?? 'desc';
        $limit = $validated['limit'] ?? 10;
        $page = $validated['page'] ?? 1;

        // ----------------------------
        // QUERY
        // ----------------------------
        $query = DebitNote::with([
            'warehouse',
            'department',
            'debitNoteEmail',
            'creator',
            'items.stockIssueItem'
        ])
        // Search filter
        ->when($validated['search'] ?? null, fn($q, $search) => $q->where(fn($subQ) =>
            $subQ->where('reference_number', 'like', "%{$search}%")
                ->orWhereHas('warehouse', fn($wQ) => $wQ->where('name', 'like', "%{$search}%"))
                ->orWhereHas('department', fn($dQ) => $dQ->where('name', 'like', "%{$search}%"))
        ))
        // Warehouse filter
        ->when(!empty($validated['warehouse_ids']), fn($q) =>
            $q->whereIn('warehouse_id', $validated['warehouse_ids'])
        )
        // Department filter
        ->when(!empty($validated['department_ids']), fn($q) =>
            $q->whereIn('department_id', $validated['department_ids'])
        )
        // Date filter
        ->when(!empty($validated['start_date']), fn($q) =>
            $q->whereDate('start_date', '>=', $validated['start_date'])
        )
        ->when(!empty($validated['end_date']), fn($q) =>
            $q->whereDate('end_date', '<=', $validated['end_date'])
        );

        // ----------------------------
        // SORTING
        // ----------------------------
        if ($sortColumn === 'warehouse_name') {
            $query->join('warehouses', 'debit_notes.warehouse_id', '=', 'warehouses.id')
                ->orderBy('warehouses.name', $sortDirection)
                ->select('debit_notes.*');
        } elseif ($sortColumn === 'department_name') {
            $query->join('departments', 'debit_notes.department_id', '=', 'departments.id')
                ->orderBy('departments.name', $sortDirection)
                ->select('debit_notes.*');
        } elseif ($sortColumn === 'created_by') {
            $query->join('users', 'debit_notes.created_by', '=', 'users.id')
                ->orderBy('users.name', $sortDirection)
                ->select('debit_notes.*');
        } else {
            $query->orderBy($sortColumn, $sortDirection);
        }

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

    //     $query = DebitNote::with(['debitNoteEmail', 'items.stockIssueItem'])
    //         ->whereIn('warehouse_id', $validated['warehouse_ids'])
    //         ->whereBetween('start_date', [$validated['start_date'], $validated['end_date']])
    //         ->where('status', 'pending');

    //     if (!empty($validated['department_ids'])) {
    //         $query->whereIn('department_id', $validated['department_ids']);
    //     }

    //     $debitNotes = $query->get();

    //     if ($debitNotes->isEmpty()) {
    //         return response()->json([
    //             'message' => 'No pending Debit Notes found for the selected filters.'
    //         ], 404);
    //     }

    //     $user = auth()->user();

    //     if (!$user || !$user->email) {
    //         return response()->json([
    //             'message' => 'Authenticated user email is required to send emails.'
    //         ], 422);
    //     }

    //     $logoPath     = public_path('img/logo/logo-dark.png');
    //     $successCount = 0;
    //     $failedNotes  = [];

    //     foreach ($debitNotes as $note) {

    //         /** ----------------------------------------
    //          * 1️⃣ Validate recipient first
    //          * ------------------------------------- */
    //         $to = $note->debitNoteEmail->send_to_email ?? null;
    //         $cc = $note->debitNoteEmail->cc_to_email ?? null;

    //         if (!$to) {
    //             $failedNotes[] = $note->reference_number . ' (No recipient)';
    //             continue;
    //         }

    //         $toEmails = array_map('trim', explode(',', $to));
    //         $ccEmails = $cc ? array_map('trim', explode(',', $cc)) : [];

    //         /** ----------------------------------------
    //          * 2️⃣ Generate Excel (STOP if error)
    //          * ------------------------------------- */
    //         try {
    //             $excelContent = Excel::raw(
    //                 new DebitNoteItemsExport($note, $logoPath),
    //                 \Maatwebsite\Excel\Excel::XLSX
    //             );
    //         } catch (Throwable $e) {

    //             // ❌ STOP EVERYTHING if Excel fails
    //             return response()->json([
    //                 'message' => 'Excel export failed. Process stopped.',
    //                 'debit_note' => $note->reference_number,
    //                 'error' => $e->getMessage(),
    //             ], 500);
    //         }

    //         /** ----------------------------------------
    //          * 3️⃣ Send email
    //          * ------------------------------------- */
    //         try {
    //             Mail::send(
    //                 'Inventory.debit-note.email-template',
    //                 ['note' => $note],
    //                 function ($message) use ($toEmails, $ccEmails, $note, $excelContent, $user) {
    //                     $message->from($user->email, $user->name ?? 'System');
    //                     $message->to($toEmails)
    //                             ->cc($ccEmails)
    //                             ->subject("Debit Note: {$note->reference_number}")
    //                             ->attachData(
    //                                 $excelContent,
    //                                 "DebitNote_{$note->reference_number}.xlsx",
    //                                 [
    //                                     'mime' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'
    //                                 ]
    //                             );
    //                 }
    //             );

    //             $note->update([
    //                 'status'    => 'sent',
    //                 'send_date' => now(),
    //             ]);

    //             $successCount++;

    //         } catch (Throwable $e) {
    //             $failedNotes[] = $note->reference_number . ' (' . $e->getMessage() . ')';
    //             continue;
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
            'start_date'       => 'required|date',
            'end_date'         => 'required|date|after_or_equal:start_date',
        ]);

        $user = auth()->user();
        if (!$user || !$user->email) {
            return response()->json([
                'message' => 'Authenticated user email is required.'
            ], 422);
        }

        $debitNotes = DebitNote::with(['debitNoteEmail', 'items.stockIssueItem'])
            ->whereIn('warehouse_id', $validated['warehouse_ids'])
            ->whereBetween('start_date', [$validated['start_date'], $validated['end_date']])
            ->where('status', 'pending')
            ->when(!empty($validated['department_ids']), fn($q) => $q->whereIn('department_id', $validated['department_ids']))
            ->get();

        if ($debitNotes->isEmpty()) {
            return response()->json([
                'message' => 'No pending Debit Notes found for the selected filters.'
            ], 404);
        }

        $logoPath = public_path('img/logo/logo-dark.png');
        $successCount = 0;
        $failedNotes = [];

        foreach ($debitNotes as $note) {

            $toEmails = optional($note->debitNoteEmail)->send_to_email ? 
                array_map('trim', explode(',', $note->debitNoteEmail->send_to_email)) : null;

            $ccEmails = optional($note->debitNoteEmail)->cc_to_email ? 
                array_map('trim', explode(',', $note->debitNoteEmail->cc_to_email)) : [];

            if (!$toEmails) {
                $failedNotes[] = $note->reference_number . ' (No recipient)';
                continue;
            }

            // Generate Excel
            try {
                $excelContent = Excel::raw(
                    new DebitNoteItemsExport($note, $logoPath),
                    \Maatwebsite\Excel\Excel::XLSX
                );
            } catch (Throwable $e) {
                return response()->json([
                    'message' => 'Excel export failed. Process stopped.',
                    'debit_note' => $note->reference_number,
                    'error' => $e->getMessage(),
                ], 500);
            }

            // Send Email
            try {
                Mail::send(
                    'Inventory.debit-note.email-template',
                    ['note' => $note],
                    function ($message) use ($toEmails, $ccEmails, $note, $excelContent, $user) {
                        // Use system email as "from" and auth user as reply-to
                        $message->from(config('mail.from.address'), config('mail.from.name'));
                        $message->replyTo($user->email, $user->name ?? 'System');
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

                // Mark as sent
                $note->update([
                    'status'    => 'sent',
                    'send_date' => now(),
                ]);

                $successCount++;
            } catch (Throwable $e) {
                $failedNotes[] = $note->reference_number . ' (' . $e->getMessage() . ')';
            }
        }

        return response()->json([
            'message' => "Emails sent successfully for {$successCount} debit notes.",
            'failed'  => $failedNotes,
        ]);
    }
}
