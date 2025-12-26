<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Maatwebsite\Excel\Facades\Excel;
use App\Imports\DebitNoteEmailImport;
use App\Models\DebitNoteEmail;

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
            'send_to_email' => 'required|array',
            'send_to_email.*' => 'email',
            'cc_to_email' => 'nullable|array',
            'cc_to_email.*' => 'email',
        ]);

        $email = DebitNoteEmail::create([
            'department_id' => $request->input('department_id'),
            'warehouse_id' => $request->input('warehouse_id'),
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
            'send_to_email' => 'required|array',
            'send_to_email.*' => 'email',
            'cc_to_email' => 'nullable|array',
            'cc_to_email.*' => 'email',
        ]);

        $email = DebitNoteEmail::findOrFail($id);

        $email->update([
            'department_id' => $request->input('department_id'),
            'warehouse_id' => $request->input('warehouse_id'),
            'send_to_email' => implode(',', $request->input('send_to_email', [])),
            'cc_to_email' => implode(',', $request->input('cc_to_email', [])),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Debit Note Email updated successfully',
            'data' => $email
        ]);
    }
}
