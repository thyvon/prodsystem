<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Http\JsonResponse;
use Illuminate\View\View;
use App\Models\DocumentTransfer;
use App\Models\DocumentTransferResponse;
use App\Models\User;

use Telegram\Bot\Laravel\Facades\Telegram;
use Telegram\Bot\Keyboard\Keyboard;

class DocumentTransferController extends Controller
{
    private const ALLOWED_SORT_COLUMNS = [
        'reference_no',
        'document_type',
        'project_name',
        'status',
        'created_at',
    ];
    private const DEFAULT_SORT_COLUMN = 'created_at';
    private const DEFAULT_SORT_DIRECTION = 'desc';
    private const DEFAULT_LIMIT = 10;
    private const MAX_LIMIT = 1000;
    private const DATE_FORMAT = 'Y-m-d';
    public function index()
    {
        return view('document-transfer.index');
    }

    public function getDocumentTransfers(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'search' => 'nullable|string|max:255',
            'sortColumn' => 'nullable|string',
            'sortDirection' => 'nullable|string|in:asc,desc',
            'limit' => 'nullable|integer|min:1|max:' . self::MAX_LIMIT,
            'page' => 'nullable|integer|min:1',
            'draw' => 'nullable|integer',
        ]);

        $sortColumn = $validated['sortColumn'] ?? 'document_transfers.id';
        $sortDirection = $validated['sortDirection'] ?? 'desc';

        // Base query
        $query = DocumentTransfer::with(['receivers.receiver', 'creator', 'updater'])
            ->whereNull('document_transfers.deleted_at');

        // Search filter
        if (!empty($validated['search'])) {
            $search = $validated['search'];
            $query->where(function ($q) use ($search) {
                $q->where('reference_no', 'like', "%{$search}%")
                ->orWhere('document_type', 'like', "%{$search}%")
                ->orWhere('project_name', 'like', "%{$search}%")
                ->orWhere('description', 'like', "%{$search}%");
            });
        }

        // Save total filtered count before pagination
        $recordsFiltered = $query->count();

        // Sorting
        if ($sortColumn === 'created_by') {
            $query->join('users', 'document_transfers.created_by', '=', 'users.id')
                ->orderBy('users.name', $sortDirection)
                ->select('document_transfers.*');
        } else {
            $query->orderBy($sortColumn, $sortDirection);
        }

        // Pagination
        $limit = $validated['limit'] ?? self::DEFAULT_LIMIT;
        $page = $validated['page'] ?? 1;

        $documentTransfers = $query->paginate($limit, ['*'], 'page', $page);

        $documentTransfersMapped = $documentTransfers->map(fn($transfer) => [
            'id' => $transfer->id,
            'reference_no' => $transfer->reference_no,
            'document_type' => $transfer->document_type,
            'project_name' => $transfer->project_name,
            'description' => $transfer->description,
            'receivers' => $transfer->receivers
            ->sortBy(fn($r) => [$r->id, $r->created_at])
            ->map(fn($r) => [
                'receiver_id' => $r->receiver_id,
                'name'        => $r->receiver->name ?? 'N/A',
                'email'       => $r->receiver->email ?? null,
                'status'      => $r->status,
                'received_date' => $r->sent_date ?? $r->received_date,
            ]),
            'created_by' => $transfer->creator->name ?? null,
            'created_at' => $transfer->created_at,
            'updated_at' => $transfer->updated_at,
            'status' => $transfer->status,
        ]);

        return response()->json([
            'data' => $documentTransfersMapped,
            'recordsTotal' => DocumentTransfer::whereNull('deleted_at')->count(),
            'recordsFiltered' => $recordsFiltered,
            'draw' => (int) ($validated['draw'] ?? 1),
        ]);
    }

    public function form( DocumentTransfer $documentTransfer = null): View
    {
        return view('document-transfer.form', compact('documentTransfer'));
    }

    // public function store(Request $request): JsonResponse
    // {     
    //     $validated = Validator::make($request->all(), array_merge(
    //         $this->documentTransferValidationRules(),
    //         [
    //             'receivers' => 'required|array|min:1',
    //             'receivers.*.receiver_id' => 'required|exists:users,id',
    //         ]
    //     ))->validate();

    //     try {
    //         return DB::transaction(function () use ($validated) {
    //             $referenceNo = $this->generateReferenceNo();
    //             $documentTransfer = DocumentTransfer::create([
    //                 'reference_no' => $referenceNo,
    //                 'document_type' => $validated['document_type'],
    //                 'project_name' => $validated['project_name'],
    //                 'description' => $validated['description'],
    //                 'status' => 'Pending',
    //                 'created_by' => auth()->id(),
    //             ]);

    //             $receivers = array_map(function ($receiver) use ($documentTransfer) {
    //                 return [
    //                     'documents_id' => $documentTransfer->id,
    //                     'document_reference' => $documentTransfer->reference_no,
    //                     'document_name' => $documentTransfer->project_name,
    //                     'status' => 'Pending',
    //                     'requester_id' => auth()->id(),
    //                     'receiver_id' => $receiver['receiver_id'],
    //                     'received_date' => null,
    //                 ];
    //             }, $validated['receivers']);
    //             DocumentTransferResponse::insert($receivers);
    //             return response()->json([
    //                 'message' => 'Document transfer created successfully.',
    //                 'data' => $documentTransfer->load('receivers'),
    //             ], 201);
    //         });
    //     } catch (\Exception $e) {
    //         Log::error('Failed to create document transfer', ['error' => $e->getMessage()]);
    //         return response()->json([
    //             'message' => 'Failed to create document transfer.',
    //             'error' => $e->getMessage(),
    //         ], 500);
    //     }
    // }
    public function store(Request $request): JsonResponse
    {     
        $validated = Validator::make($request->all(), array_merge(
            $this->documentTransferValidationRules(),
            [
                'receivers' => 'required|array|min:1',
                'receivers.*.receiver_id' => 'required|exists:users,id',
            ]
        ))->validate();

        try {
            return DB::transaction(function () use ($validated) {
                $referenceNo = $this->generateReferenceNo();
                $documentTransfer = DocumentTransfer::create([
                    'reference_no' => $referenceNo,
                    'document_type' => $validated['document_type'],
                    'project_name' => $validated['project_name'],
                    'description' => $validated['description'],
                    'status' => 'Pending',
                    'created_by' => auth()->id(),
                ]);

                $receivers = array_map(function ($receiver) use ($documentTransfer) {
                    return [
                        'documents_id' => $documentTransfer->id,
                        'document_reference' => $documentTransfer->reference_no,
                        'document_name' => $documentTransfer->project_name,
                        'status' => 'Pending',
                        'requester_id' => auth()->id(),
                        'receiver_id' => $receiver['receiver_id'],
                        'received_date' => null,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ];
                }, $validated['receivers']);

                DocumentTransferResponse::insert($receivers);

                // -----------------------------
                // Telegram: Notify first receiver only
                // -----------------------------
                $firstReceiverData = collect($receivers)
                    ->sortBy(['id', 'created_at'])
                    ->first();

                if ($firstReceiverData) {
                    $user = User::find($firstReceiverData['receiver_id']);
                    if ($user && $user->telegram_id) {
                        $keyboard = Keyboard::make()
                            ->inline()
                            ->row(
                                Keyboard::inlineButton([
                                    'text' => 'Mark as Received',
                                    'callback_data' => 'receive_'.$documentTransfer->id.'-'.$user->id
                                ])
                            );

                        Telegram::sendMessage([
                            'chat_id' => $user->telegram_id,
                            'text' => "📄 You have a new document: *{$documentTransfer->project_name}*\nReference: {$documentTransfer->reference_no}",
                            'parse_mode' => 'Markdown',
                            'reply_markup' => $keyboard,
                        ]);
                    }
                }

                return response()->json([
                    'message' => 'Document transfer created successfully. Notification sent to first receiver.',
                    'data' => $documentTransfer->load('receivers'),
                ], 201);
            });
        } catch (\Exception $e) {
            Log::error('Failed to create document transfer', ['error' => $e->getMessage()]);
            return response()->json([
                'message' => 'Failed to create document transfer.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }


    private function documentTransferValidationRules(): array
    {
        return [
            'document_type' => 'required|string|max:255',
            'project_name' => 'required|string|max:255',
            'description' => 'nullable|string|max:1000',
        ];
    }

    public function getReceivers(): JsonResponse
    {
        $users = DB::table('users')
            ->select('id', 'name', 'telegram_id')
            ->whereNotNull('telegram_id')
            ->where('id', '!=', auth()->id())
            ->get();

        return response()->json($users);
    }

    private function generateReferenceNo(): string
    {
        $createdAt = time(); // current timestamp
        $date = date('Ymd', $createdAt);
        $count = DocumentTransfer::whereDate('created_at', date('Y-m-d', $createdAt))->count() + 1;
        return 'DOC-' . $date . '-' . str_pad($count, 4, '0', STR_PAD_LEFT);
    }

    public function receive(Request $request): JsonResponse
    {
        // This assumes Telegram webhook sends `callback_data` for the button
        $callbackData = $request->input('callback_data'); 

        if (!str_starts_with($callbackData, 'receive_')) {
            return response()->json(['message' => 'Invalid callback data'], 400);
        }

        // Parse document_id and receiver_id from the callback_data
        [$documentId, $receiverId] = explode('-', str_replace('receive_', '', $callbackData));

        $documentTransfer = DocumentTransfer::find($documentId);
        if (!$documentTransfer) {
            return response()->json(['message' => 'Document not found'], 404);
        }

        $allReceivers = $documentTransfer->receivers()->orderBy('id')->get();
        $currentIndex = $allReceivers->search(fn($r) => $r->receiver_id == $receiverId);

        if ($currentIndex === false) {
            return response()->json(['message' => 'You are not authorized to receive this document'], 403);
        }

        $currentReceiver = $allReceivers[$currentIndex];

        // Ensure previous receivers have received
        if ($currentIndex > 0) {
            $previousReceiver = $allReceivers[$currentIndex - 1];
            if ($previousReceiver->status !== 'Received') {
                return response()->json(['message' => 'Previous receivers must receive the document first'], 400);
            }
        }

        if ($currentReceiver->status !== 'Received') {
            $currentReceiver->update([
                'status' => 'Received',
                'received_date' => now(), // record the receive timestamp
            ]);
        }

        // Update document status if last receiver
        $lastReceiver = $allReceivers->last();
        if ($currentReceiver->id === $lastReceiver->id) {
            $documentTransfer->update(['status' => 'Completed']);
        } else {
            // Notify the next receiver via Telegram
            $nextReceiver = $allReceivers[$currentIndex + 1] ?? null;
            if ($nextReceiver && $nextReceiver->receiver->telegram_id) {
                $keyboard = Keyboard::make()
                    ->inline()
                    ->row(
                        Keyboard::inlineButton([
                            'text' => 'Mark as Received',
                            'callback_data' => 'receive_'.$documentTransfer->id.'-'.$nextReceiver->receiver_id
                        ])
                    );

                Telegram::sendMessage([
                    'chat_id' => $nextReceiver->receiver->telegram_id,
                    'text' => "📄 You are next to receive document: *{$documentTransfer->project_name}*\nReference: {$documentTransfer->reference_no}",
                    'parse_mode' => 'Markdown',
                    'reply_markup' => $keyboard,
                ]);
            }
        }

        return response()->json([
            'message' => 'Document transfer received successfully',
            'data' => $documentTransfer->load('receivers'),
        ]);
    }

    /**
     * Add or reassign receivers for a document transfer
     */
    public function updateReceiversOrReceive(Request $request, DocumentTransfer $documentTransfer): JsonResponse
    {
        $validated = $request->validate([
            'receivers' => 'required|array',
            'receivers.*.id' => 'required|integer|exists:users,id', // user id
            'receivers.*.status' => 'nullable|string|in:Pending,Received,Completed',
        ]);

        $submittedIds = collect($validated['receivers'])->pluck('id')->toArray();

        $existingReceivers = $documentTransfer->receivers()->get();

        // 1️⃣ Delete receivers not in submittedIds
        $documentTransfer->receivers()->whereNotIn('receiver_id', $submittedIds)->delete();

        // 2️⃣ Update existing or create new
        foreach ($validated['receivers'] as $inputReceiver) {
            $receiver = $existingReceivers->firstWhere('receiver_id', $inputReceiver['id']);

            if ($receiver) {
                // Update status if not Completed
                if ($receiver->status === 'Received') {
                    $receiver->update([
                        'owner_receive_status' => 'Received',
                        'owner_received_date' => now(),
                    ]);
                }
                elseif ($receiver->status !== 'Received') {
                    $receiver->update([
                        'status' => $inputReceiver['status'] ?? 'Pending',
                    ]);
                }
            } else {
                // Add new receiver
                
                $documentTransfer->receivers()->create([
                    'receiver_id' => $inputReceiver['id'],
                    'documents_id' => $documentTransfer->id,
                    'document_reference' => $documentTransfer->reference_no,
                    'document_name' => $documentTransfer->project_name,
                    'requester_id' => auth()->id(),
                    'status' => $inputReceiver['status'] ?? 'Pending',
                    'owner_receive_status' => 'Pending',
                    'owner_received_date' => null,
                ]);
            }
        }

        // Reload receivers ordered by id
        $documentTransfer->load(['receivers' => fn($q) => $q->orderBy('id')]);

        return response()->json([
            'message' => 'Receivers updated successfully.',
            'data' => $documentTransfer->receivers,
        ]);
    }

}
