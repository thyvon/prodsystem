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

        $query = DocumentTransfer::with(['receivers.receiver', 'creator', 'updater'])
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
                    'email' => $r->receiver->email ?? null,
                    'status' => $r->status,
                    'received_date' => $r->received_date ?? null,
                    'sent_date' => $r->sent_date ?? null,
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
                    'sent_date' => null,
                    'telegram_message_id' => null,
                    'created_at' => now(),
                    'updated_at' => now(),
                ], $validated['receivers']);

                DocumentTransferResponse::insert($receivers);

                $this->notifyFirstReceiver($receivers, $documentTransfer);

                return response()->json([
                    'message' => 'Document transfer created successfully.',
                    'data' => $documentTransfer->load('receivers.receiver'),
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

    // 🔹 Telegram notifications
    private function notifyFirstReceiver(array $receivers, DocumentTransfer $documentTransfer): void
    {
        $firstReceiver = collect($receivers)->sortBy(['id', 'created_at'])->first();
        if (!$firstReceiver) return;

        $user = User::find($firstReceiver['receiver_id']);
        if (!$user || !$user->telegram_id) return;

        $creator = auth()->user();
        $message = "📢 *Dear {$user->name},*\n\n"
            ."📄 *You have a new document!*\n\n"
            ."📝 *Description:* {$documentTransfer->description}\n"
            ."📂 *Document Type:* {$documentTransfer->document_type}\n"
            ."🏷️ *Project:* {$documentTransfer->project_name}\n"
            ."👤 *Sent From:* {$creator->name}\n"
            ."🆔 *Reference:* {$documentTransfer->reference_no}";

        $keyboard = Keyboard::make()->inline()->row([
            Keyboard::inlineButton([
                'text' => '✅ Mark as Received',
                'callback_data' => "receive_{$documentTransfer->id}-{$user->id}"
            ])
        ]);

        $response = Telegram::sendMessage([
            'chat_id' => $user->telegram_id,
            'text' => $message,
            'parse_mode' => 'Markdown',
            'reply_markup' => $keyboard,
        ]);

        // Save message_id for later deletion
        $documentTransfer->receivers()->where('receiver_id', $user->id)
            ->update(['telegram_message_id' => $response->getMessageId()]);
    }

    private function notifyNextReceiver(DocumentTransfer $documentTransfer): void
    {
        $nextReceiver = $documentTransfer->receivers
            ->where('status', 'Pending')
            ->sortBy(['id', 'created_at'])
            ->first();

        if (!$nextReceiver) return;

        $this->notifyFirstReceiver([$nextReceiver->toArray()], $documentTransfer);
    }

    // 🔹 Receive via Telegram
    public function receive(Request $request): JsonResponse
    {
        [$documentId, $receiverId] = explode('-', str_replace('receive_', '', $request->input('callback_data')));
        $chatId = $request->input('chat_id');
        $messageId = $request->input('message_id');
        $callbackQueryId = $request->input('callback_query_id');

        $document = DocumentTransfer::with('receivers.receiver', 'creator')->find($documentId);
        if (!$document) {
            Telegram::answerCallbackQuery([
                'callback_query_id' => $callbackQueryId,
                'text' => "❌ Document not found.",
                'show_alert' => true
            ]);
            return response()->json();
        }

        $receiver = $document->receivers->firstWhere('receiver_id', $receiverId);
        if (!$receiver) {
            Telegram::answerCallbackQuery([
                'callback_query_id' => $callbackQueryId,
                'text' => "❌ You are not authorized to receive this document.",
                'show_alert' => true
            ]);
            return response()->json();
        }

        $user = $receiver->receiver;
        $creator = $document->creator;

        $status = $receiver->status === 'Received' ? 'Already Received' : 'Received';

        $receiver->update([
            'status' => 'Received',
            'received_date' => now(),
        ]);

        // Update Telegram message
        $message = "📢 *Dear {$user->name},*\n\n"
            ."📄 *You have a new document!*\n\n"
            ."📝 *Description:* {$document->description}\n"
            ."📂 *Document Type:* {$document->document_type}\n"
            ."🏷️ *Project:* {$document->project_name}\n"
            ."👤 *Sent From:* {$creator->name}\n"
            ."🆔 *Reference:* {$document->reference_no}\n\n"
            ."🔄 *Status:* {$status}";

        Telegram::editMessageText([
            'chat_id' => $chatId,
            'message_id' => $messageId,
            'text' => $message,
            'parse_mode' => 'Markdown',
        ]);

        // Send Back button if enabled
        if ($document->is_send_back) {
            $keyboard = Keyboard::make()->inline()->row([
                Keyboard::inlineButton([
                    'text' => '🔄 Send Back',
                    'callback_data' => 'sendback_'.$document->id.'-'.$receiverId,
                ])
            ]);

            $response = Telegram::sendMessage([
                'chat_id' => $chatId,
                'text' => "You can send back this document if needed.",
                'reply_markup' => $keyboard,
            ]);

            // Save message_id for potential deletion
            $receiver->update(['telegram_message_id' => $response->getMessageId()]);
        }

        return response()->json(['success' => true, 'message' => $message]);
    }

    // 🔹 Send Back via Telegram
    public function sendBack(Request $request): JsonResponse
    {
        [$documentId, $receiverId] = explode('-', str_replace('sendback_', '', $request->input('callback_data')));
        $chatId = $request->input('chat_id');
        $messageId = $request->input('message_id');
        $callbackQueryId = $request->input('callback_query_id');

        $document = DocumentTransfer::with('receivers.receiver', 'creator')->find($documentId);
        if (!$document) {
            Telegram::answerCallbackQuery([
                'callback_query_id' => $callbackQueryId,
                'text' => "❌ Document not found.",
                'show_alert' => true
            ]);
            return response()->json();
        }

        $receiver = $document->receivers->firstWhere('receiver_id', $receiverId);
        if (!$receiver) {
            Telegram::answerCallbackQuery([
                'callback_query_id' => $callbackQueryId,
                'text' => "❌ You are not authorized to send back this document.",
                'show_alert' => true
            ]);
            return response()->json();
        }

        // Update receiver status
        $receiver->update([
            'status' => 'Sent Back',
            'sent_back_date' => now(),
        ]);

        $user = $receiver->receiver;
        $creator = $document->creator;

        // Keep original received date if exists
        $receivedDate = $receiver->received_date ? $receiver->received_date->format('Y-m-d H:i') : 'N/A';

        // Edit receiver's old message
        if ($receiver->telegram_message_id) {
            $receiverMessage = "📢 *Dear {$user->name},*\n\n"
                ."📄 *You have a new document!*\n\n"
                ."📝 *Description:* {$document->description}\n"
                ."📂 *Document Type:* {$document->document_type}\n"
                ."🏷️ *Project:* {$document->project_name}\n"
                ."👤 *Sent From:* {$creator->name}\n"
                ."🆔 *Reference:* {$document->reference_no}\n\n"
                ."✅ *Received Date:* {$receivedDate}\n"
                ."🔄 *Status:* Sent Back\n"
                ."🗓️ *Sent Back Date:* ".now()->format('Y-m-d H:i');

            try {
                Telegram::editMessageText([
                    'chat_id' => $chatId,
                    'message_id' => $receiver->telegram_message_id,
                    'text' => $receiverMessage,
                    'parse_mode' => 'Markdown',
                ]);
            } catch (\Exception $e) {
                Log::error("Failed to edit receiver Telegram message: ".$e->getMessage());
            }
        }

        // Edit creator's original message if exists
        $creatorReceiver = $document->receivers->firstWhere('receiver_id', $creator->id);
        if ($creator && $creator->telegram_id && $creatorReceiver && $creatorReceiver->telegram_message_id) {
            $creatorMessage = "📢 Document Status Update\n\n"
                ."Document: *{$document->project_name}*\n"
                ."Reference: {$document->reference_no}\n"
                ."Sent Back by: {$user->name}\n"
                ."✅ Received Date: {$receivedDate}\n"
                ."🗓️ Sent Back Date: ".now()->format('Y-m-d H:i');

            try {
                Telegram::editMessageText([
                    'chat_id' => $creator->telegram_id,
                    'message_id' => $creatorReceiver->telegram_message_id,
                    'text' => $creatorMessage,
                    'parse_mode' => 'Markdown',
                ]);
            } catch (\Exception $e) {
                Log::error("Failed to edit creator Telegram message: ".$e->getMessage());
            }
        }

        // Answer callback query
        Telegram::answerCallbackQuery([
            'callback_query_id' => $callbackQueryId,
            'text' => "✅ Document marked as Sent Back.",
            'show_alert' => false
        ]);

        return response()->json(['success' => true, 'message' => 'Document marked as Sent Back']);
    }

    // 🔹 Send to next receiver via Telegram
    public function sendToNextReceiver(Request $request): JsonResponse
    {
        [$documentId, $receiverId] = explode('-', str_replace('sendto_', '', $request->input('callback_data')));
        $callbackQueryId = $request->input('callback_query_id');

        $document = DocumentTransfer::with('receivers.receiver')->find($documentId);
        if (!$document) {
            Telegram::answerCallbackQuery([
                'callback_query_id' => $callbackQueryId,
                'text' => "❌ Document not found.",
                'show_alert' => true
            ]);
            return response()->json();
        }

        $nextReceiver = $document->receivers->firstWhere('receiver_id', $receiverId);
        if (!$nextReceiver) {
            Telegram::answerCallbackQuery([
                'callback_query_id' => $callbackQueryId,
                'text' => "❌ Next receiver not found or already received.",
                'show_alert' => true
            ]);
            return response()->json();
        }

        $this->notifyFirstReceiver([$nextReceiver->toArray()], $document);

        Telegram::answerCallbackQuery([
            'callback_query_id' => $callbackQueryId,
            'text' => "✅ Document sent to {$nextReceiver->receiver->name}.",
            'show_alert' => true
        ]);

        return response()->json(['success' => true]);
    }

    // 🔹 Update receivers via UI
    public function updateReceiversOrReceive(Request $request, DocumentTransfer $documentTransfer): JsonResponse
    {
        $validated = $request->validate([
            'receivers' => 'required|array|min:1',
            'receivers.*.receiver_id' => 'required|integer|exists:users,id',
            'receivers.*.status' => 'nullable|string|in:Pending,Received,Completed',
        ]);

        $validated['receivers'] = collect($validated['receivers'])->unique('receiver_id')->values()->all();
        $submittedIds = collect($validated['receivers'])->pluck('receiver_id')->toArray();
        $existingReceivers = $documentTransfer->receivers()->get();

        // Delete removed receivers
        $documentTransfer->receivers()->whereNotIn('receiver_id', $submittedIds)->delete();

        foreach ($validated['receivers'] as $input) {
            $receiver = $existingReceivers->firstWhere('receiver_id', $input['receiver_id']);
            if ($receiver) {
                $receiver->update([
                    'status' => $input['status'] ?? $receiver->status,
                    'owner_receive_status' => $receiver->status === 'Received' ? 'Received' : ($input['status'] ?? $receiver->owner_receive_status),
                    'owner_received_date' => $receiver->status === 'Received' ? $receiver->owner_received_date ?? now() : $receiver->owner_received_date,
                ]);
            } else {
                $receiver = $documentTransfer->receivers()->create([
                    'receiver_id' => $input['receiver_id'],
                    'documents_id' => $documentTransfer->id,
                    'document_reference' => $documentTransfer->reference_no,
                    'document_name' => $documentTransfer->project_name,
                    'requester_id' => auth()->id(),
                    'status' => $input['status'] ?? 'Pending',
                    'owner_receive_status' => 'Pending',
                    'owner_received_date' => null,
                    'telegram_message_id' => null,
                ]);
            }

            // Delete old message if exists
            if (!empty($receiver->telegram_message_id)) {
                try {
                    Telegram::deleteMessage([
                        'chat_id' => $receiver->receiver->telegram_id,
                        'message_id' => $receiver->telegram_message_id,
                    ]);
                } catch (\Exception $e) {
                    Log::error("Failed to delete old Telegram message: ".$e->getMessage());
                }
            }
        }

        $documentTransfer->load(['receivers.receiver' => fn($q) => $q->orderBy('id')]);

        // Notify the next pending receiver
        $this->notifyNextReceiver($documentTransfer);

        return response()->json([
            'message' => 'Receivers updated successfully.',
            'data' => $documentTransfer->receivers,
        ]);
    }

    // 🔹 Webhook entry
    public function webhook(Request $request)
    {
        $callbackData = $request->input('callback_query.data');
        $telegramUserId = $request->input('callback_query.from.id');
        $messageId = $request->input('callback_query.message.message_id');
        $callbackQueryId = $request->input('callback_query.id');

        if (!$callbackData) return response()->json();

        $request->merge([
            'chat_id' => $telegramUserId,
            'message_id' => $messageId,
            'callback_query_id' => $callbackQueryId,
            'callback_data' => $callbackData,
        ]);

        if (str_starts_with($callbackData, 'receive_')) return $this->receive($request);
        if (str_starts_with($callbackData, 'sendback_')) return $this->sendBack($request);
        if (str_starts_with($callbackData, 'sendto_')) return $this->sendToNextReceiver($request);

        return response()->json();
    }

    // 🔹 Helper to edit Telegram message
    private function telegramEditMessage($chatId, $messageId, $text)
    {
        try {
            Telegram::editMessageText([
                'chat_id' => $chatId,
                'message_id' => $messageId,
                'text' => $text,
                'parse_mode' => 'Markdown',
            ]);
        } catch (\Exception $e) {
            Log::error('Telegram editMessageText failed', ['error' => $e->getMessage()]);
        }

        return response()->json(['success' => true, 'message' => $text]);
    }
}
