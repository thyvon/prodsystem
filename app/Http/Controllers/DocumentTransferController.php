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
use Telegram\Bot\Keyboard\Keyboard;
use Telegram\Bot\Laravel\Facades\Telegram;

class DocumentTransferController extends Controller
{
    private const DEFAULT_LIMIT = 10;
    private const MAX_LIMIT = 1000;

    public function index(): View
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

        $query = DocumentTransfer::with(['receivers.receiver', 'creator'])
            ->whereNull('deleted_at');

        if (!empty($validated['search'])) {
            $search = $validated['search'];
            $query->where(function ($q) use ($search) {
                $q->where('reference_no', 'like', "%$search%")
                  ->orWhere('document_type', 'like', "%$search%")
                  ->orWhere('project_name', 'like', "%$search%")
                  ->orWhere('description', 'like', "%$search%");
            });
        }

        $recordsFiltered = $query->count();
        $sortColumn = $validated['sortColumn'] ?? 'id';
        $sortDirection = $validated['sortDirection'] ?? 'desc';
        $query->orderBy($sortColumn, $sortDirection);

        $limit = $validated['limit'] ?? self::DEFAULT_LIMIT;
        $page = $validated['page'] ?? 1;

        $documentTransfers = $query->paginate($limit, ['*'], 'page', $page);

        $data = $documentTransfers->map(fn($transfer) => [
            'id' => $transfer->id,
            'reference_no' => $transfer->reference_no,
            'document_type' => $transfer->document_type,
            'project_name' => $transfer->project_name,
            'description' => $transfer->description,
            'receivers' => $transfer->receivers
                ->sortBy(fn($r) => [$r->id, $r->created_at])
                ->map(fn($r) => [
                    'receiver_id' => $r->receiver_id,
                    'name' => $r->receiver->name ?? 'N/A',
                    'email' => $r->receiver->email,
                    'status' => $r->status,
                    'received_date' => $r->sent_date ?? $r->received_date,
                ]),
            'created_by' => $transfer->creator->name ?? null,
            'status' => $transfer->status,
            'created_at' => $transfer->created_at,
            'updated_at' => $transfer->updated_at,
        ]);

        return response()->json([
            'data' => $data,
            'recordsTotal' => DocumentTransfer::whereNull('deleted_at')->count(),
            'recordsFiltered' => $recordsFiltered,
            'draw' => (int) ($validated['draw'] ?? 1),
        ]);
    }

    public function form(DocumentTransfer $documentTransfer = null): View
    {
        return view('document-transfer.form', compact('documentTransfer'));
    }

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
                $documentTransfer = DocumentTransfer::create([
                    'reference_no' => $this->generateReferenceNo(),
                    'document_type' => $validated['document_type'],
                    'project_name' => $validated['project_name'],
                    'description' => $validated['description'],
                    'status' => 'Pending',
                    'is_send_back' => $validated['is_send_back'] ?? 0,
                    'created_by' => auth()->id(),
                ]);

                $receivers = array_map(fn($r) => [
                    'documents_id' => $documentTransfer->id,
                    'document_reference' => $documentTransfer->reference_no,
                    'document_name' => $documentTransfer->project_name,
                    'status' => 'Pending',
                    'requester_id' => auth()->id(),
                    'receiver_id' => $r['receiver_id'],
                    'received_date' => null,
                    'created_at' => now(),
                    'updated_at' => now(),
                ], $validated['receivers']);

                DocumentTransferResponse::insert($receivers);

                // Notify first receiver
                $firstReceiver = collect($receivers)->sortBy(['id', 'created_at'])->first();
                $user = User::find($firstReceiver['receiver_id']);
                $documentTransfer->notifyReceiver($user, 'receive');

                return response()->json([
                    'message' => 'Document transfer created successfully.',
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
            'is_send_back' => 'nullable|boolean',
        ];
    }

    public function getReceivers(): JsonResponse
    {
        return response()->json(User::whereNotNull('telegram_id')
            ->where('id', '!=', auth()->id())
            ->select('id', 'name', 'telegram_id')
            ->get());
    }

    private function generateReferenceNo(): string
    {
        $date = now()->format('Ymd');
        $count = DocumentTransfer::whereDate('created_at', now())->count() + 1;
        return 'DOC-' . $date . '-' . str_pad($count, 4, '0', STR_PAD_LEFT);
    }

    // 🔹 Receive action via Telegram
    public function receive(Request $request)
    {
        [$action, $documentId, $receiverId] = explode('_', $request->input('callback_data'));
        $chatId = $request->input('chat_id');
        $messageId = $request->input('message_id');

        $document = DocumentTransfer::findOrFail($documentId);
        $result = $document->updateReceiverStatus((int)$receiverId, (int)$chatId, 'Received');

        $text = $result['success']
            ? "✅ Document received successfully:\n\n*{$document->project_name}*"
            : "❌ Failed to receive document:\n{$result['message']}";

        Telegram::editMessageText([
            'chat_id' => $chatId,
            'message_id' => $messageId,
            'text' => $text,
            'parse_mode' => 'Markdown',
        ]);

        if ($result['success'] && $document->is_send_back) {
            $document->notifyReceiver(User::find($receiverId), 'sendback');
        }

        return response()->json(['success' => $result['success'], 'message' => $text]);
    }

    // 🔹 Send Back action via Telegram
    public function sendBack(Request $request)
    {
        [$action, $documentId, $receiverId] = explode('_', $request->input('callback_data'));
        $chatId = $request->input('chat_id');
        $messageId = $request->input('message_id');

        $document = DocumentTransfer::findOrFail($documentId);
        $result = $document->updateReceiverStatus((int)$receiverId, (int)$chatId, 'Sent Back');

        $text = $result['success']
            ? "🔄 Document sent back successfully:\n\n*{$document->project_name}*"
            : "❌ Failed to send back document:\n{$result['message']}";

        Telegram::editMessageText([
            'chat_id' => $chatId,
            'message_id' => $messageId,
            'text' => $text,
            'parse_mode' => 'Markdown',
        ]);

        if ($result['success'] && $document->creator?->telegram_id) {
            Telegram::sendMessage([
                'chat_id' => $document->creator->telegram_id,
                'text' => "📢 Document Sent Back\nDocument: {$document->project_name}\nReference: {$document->reference_no}\nReceiver: {$result['receiver']->receiver->name}",
                'parse_mode' => 'Markdown',
            ]);
        }

        return response()->json(['success' => $result['success'], 'message' => $text]);
    }
}
