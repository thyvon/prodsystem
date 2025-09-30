<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Http\JsonResponse;
use Illuminate\View\View;
use App\Models\DocumentTransfer;
use App\Models\DocumentsReceiver;
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

        $query = DocumentTransfer::with(['receivers.receiver', 'receivers.requester'])
            ->whereNull('deleted_at');

        if ($search = $validated['search'] ?? null) {
            $query->where(fn($q) => $q->where('reference_no', 'like', "%$search%")
                ->orWhere('document_type', 'like', "%$search%")
                ->orWhere('project_name', 'like', "%$search%")
                ->orWhere('description', 'like', "%$search%"));
        }

        $transfers = $query->orderBy($validated['sortColumn'] ?? 'id', $validated['sortDirection'] ?? 'desc')
            ->paginate($validated['limit'] ?? self::DEFAULT_LIMIT, ['*'], 'page', $validated['page'] ?? 1);

        return response()->json([
            'data' => $transfers->map(fn($t) => [
                'id' => $t->id,
                'reference_no' => $t->reference_no,
                'document_type' => $t->document_type,
                'project_name' => $t->project_name,
                'description' => $t->description,
                'receivers' => $t->receivers->map(fn($r) => [
                    'receiver_id' => $r->receiver_id,
                    'name' => $r->receiver->name ?? 'N/A',
                    'email' => $r->receiver->email ?? null,
                    'status' => $r->status,
                    'received_date' => $r->received_date,
                    'sent_date' => $r->sent_date,
                    'owner_received_status' => $r->owner_received_status,
                    'owner_received_date' => $r->owner_received_date,
                    'telegram_message_id' => $r->telegram_message_id,
                    'telegram_creator_message_id' => $r->telegram_creator_message_id,
                ]),
                'created_by' => $t->receivers->first()->requester->name ?? 'N/A',
                'status' => $t->status,
                'created_at' => $t->created_at,
                'updated_at' => $t->updated_at,
            ]),
            'recordsTotal' => DocumentTransfer::whereNull('deleted_at')->count(),
            'recordsFiltered' => $query->count(),
            'draw' => (int) ($validated['draw'] ?? 1),
        ]);
    }

    public function form(DocumentTransfer $documentTransfer = null): View
    {
        return view('document-transfer.form', compact('documentTransfer'));
    }

    public function store(Request $request): JsonResponse
    {
        $validated = Validator::make($request->all(), [
            'document_type' => 'required|string|max:255',
            'project_name' => 'required|string|max:255',
            'description' => 'nullable|string|max:1000',
            'is_send_back' => 'nullable|boolean',
            'receivers' => 'required|array|min:1',
            'receivers.*.receiver_id' => 'required|exists:users,id',
        ])->validate();

        return $this->executeTransaction(
            fn() => $this->createDocumentTransfer($validated),
            'Failed to create document transfer',
            auth()->user()->telegram_id ?? null
        );
    }

    public function getReceivers(): JsonResponse
    {
        return response()->json(
            User::whereNotNull('telegram_id')
                ->where('id', '!=', auth()->id())
                ->select('id', 'name', 'telegram_id')
                ->get()
        );
    }

    public function receive(Request $request): JsonResponse
    {
        [$documentId, $receiverId] = explode('-', str_replace('receive_', '', $request->input('callback_data')));
        $chatId = $request->input('chat_id');
        $messageId = $request->input('message_id');
        $callbackQueryId = $request->input('callback_query_id');

        $data = $this->validateDocumentAndReceiver($documentId, $receiverId, $chatId, $callbackQueryId);
        if ($data instanceof JsonResponse) {
            return $data;
        }
        ['document' => $document, 'receiver' => $receiver, 'user' => $user, 'requester' => $requester] = $data;

        return $this->executeTransaction(
            function () use ($receiver, $document, $user, $requester, $chatId, $messageId, $callbackQueryId) {
                $receivedDate = now();
                $receiver->update(['status' => 'Received', 'received_date' => $receivedDate]);

                $this->updateTelegramMessage(
                    $chatId,
                    $receiver->telegram_message_id ?? $messageId,
                    $this->buildTelegramMessage($document, $user->name, $requester->name, 'Received', $receivedDate)
                );
                $receiver->update(['telegram_message_id' => Telegram::getMessageId()]);

                if ($document->is_send_back) {
                    $response = Telegram::sendMessage([
                        'chat_id' => $chatId,
                        'text' => "You can send back this document if needed.",
                        'parse_mode' => 'Markdown',
                        'reply_markup' => Keyboard::make()->inline()->row([
                            Keyboard::inlineButton(['text' => '🔄 Send Back', 'callback_data' => "sendback_{$document->id}-{$receiver->receiver_id}"])
                        ]),
                    ]);
                    $receiver->update(['telegram_message_id' => $response->getMessageId()]);
                }

                Telegram::answerCallbackQuery(['callback_query_id' => $callbackQueryId, 'text' => "✅ Document marked as Received.", 'show_alert' => false]);
                return response()->json(['success' => true]);
            },
            "Failed to process receive for document {$documentId}",
            $user->telegram_id,
            $callbackQueryId,
            "❌ Failed to mark document as received."
        );
    }

    public function sendBack(Request $request): JsonResponse
    {
        [$documentId, $receiverId] = explode('-', str_replace('sendback_', '', $request->input('callback_data')));
        $chatId = $request->input('chat_id');
        $messageId = $request->input('message_id');
        $callbackQueryId = $request->input('callback_query_id');

        $data = $this->validateDocumentAndReceiver($documentId, $receiverId, $chatId, $callbackQueryId);
        if ($data instanceof JsonResponse) {
            return $data;
        }
        ['document' => $document, 'receiver' => $receiver, 'user' => $user, 'requester' => $requester] = $data;

        return $this->executeTransaction(
            function () use ($receiver, $document, $user, $requester, $chatId, $messageId, $callbackQueryId) {
                $sentDate = now();
                $receiver->update(['status' => 'Sent Back', 'sent_date' => $sentDate]);

                $this->updateTelegramMessage(
                    $chatId,
                    $receiver->telegram_message_id ?? $messageId,
                    $this->buildTelegramMessage($document, $user->name, $requester->name, 'Sent Back', $receiver->received_date, $sentDate)
                );
                $receiver->update(['telegram_message_id' => Telegram::getMessageId()]);

                if ($requester && $requester->telegram_id) {
                    $nextReceiver = $this->getNextReceiver($document, $receiver->receiver_id);
                    $buttons = [
                        Keyboard::inlineButton(['text' => '✅ Receive to Complete', 'callback_data' => "complete_{$document->id}"])
                    ];
                    if ($nextReceiver) {
                        $buttons[] = Keyboard::inlineButton(['text' => '➡️ Send to Next Receiver', 'callback_data' => "sendto_{$document->id}-{$nextReceiver->receiver_id}"]);
                    }

                    $response = Telegram::sendMessage([
                        'chat_id' => $requester->telegram_id,
                        'text' => $this->buildTelegramMessage($document, $requester->name, $user->name, 'Sent Back', $receiver->received_date, $sentDate, true),
                        'parse_mode' => 'Markdown',
                        'reply_markup' => Keyboard::make()->inline()->row($buttons),
                    ]);
                    $receiver->update(['telegram_creator_message_id' => $response->getMessageId()]);
                }

                Telegram::answerCallbackQuery(['callback_query_id' => $callbackQueryId, 'text' => "✅ Document marked as Sent Back.", 'show_alert' => false]);
                return response()->json(['success' => true]);
            },
            "Failed to process send back for document {$documentId}",
            $user->telegram_id,
            $callbackQueryId,
            "❌ Failed to send back document."
        );
    }

    public function completeDocument(Request $request): JsonResponse
    {
        $documentId = str_replace('complete_', '', $request->input('callback_data'));
        $chatId = $request->input('chat_id');
        $messageId = $request->input('message_id');
        $callbackQueryId = $request->input('callback_query_id');

        $data = $this->validateDocumentAndSentBackReceiver($documentId, $chatId, $callbackQueryId);
        if ($data instanceof JsonResponse) {
            return $data;
        }
        ['document' => $document, 'receiver' => $receiver, 'user' => $user, 'requester' => $requester] = $data;

        return $this->executeTransaction(
            function () use ($document, $receiver, $requester, $user, $chatId, $messageId, $callbackQueryId) {
                $receivedDate = now();
                $document->update(['status' => 'Completed']);
                $receiver->update([
                    'owner_received_status' => 'Received',
                    'owner_received_date' => $receivedDate
                ]);

                $this->updateTelegramMessage(
                    $chatId,
                    $receiver->telegram_creator_message_id ?? $messageId,
                    $this->buildTelegramMessage($document, $requester->name, $user->name, 'Completed', $receivedDate, $receiver->sent_date, true)
                );
                $receiver->update(['telegram_creator_message_id' => Telegram::getMessageId()]);

                Telegram::answerCallbackQuery(['callback_query_id' => $callbackQueryId, 'text' => "✅ Document marked as Completed.", 'show_alert' => false]);
                return response()->json(['success' => true]);
            },
            "Failed to complete document {$documentId}",
            $requester->telegram_id,
            $callbackQueryId,
            "❌ Failed to mark document as completed."
        );
    }

    public function sendToNextReceiver(Request $request): JsonResponse
    {
        [$documentId, $receiverId] = explode('-', str_replace('sendto_', '', $request->input('callback_data')));
        $chatId = $request->input('chat_id');
        $callbackQueryId = $request->input('callback_query_id');

        $document = DocumentTransfer::with('receivers.receiver', 'receivers.requester')->find($documentId);
        if (!$document) {
            return $this->telegramAlert($callbackQueryId, "❌ Document not found.");
        }

        $sentBackReceiver = $document->receivers->sortByDesc('sent_date')->first();
        if (!$sentBackReceiver || $sentBackReceiver->status != 'Sent Back') {
            return $this->telegramAlert($callbackQueryId, "❌ No valid sent back receiver found.");
        }

        $nextReceiver = $document->receivers->firstWhere('receiver_id', $receiverId);
        if (!$nextReceiver) {
            return $this->telegramAlert($callbackQueryId, "❌ Next receiver not found.");
        }

        if (!$nextReceiver->receiver) {
            return $this->handleUserNotFound("Next receiver user not found for receiver_id {$receiverId}", $sentBackReceiver->requester->telegram_id, $callbackQueryId, "❌ Next receiver user not found.");
        }

        $requester = $sentBackReceiver->requester;
        if (!$requester) {
            return $this->handleUserNotFound("Requester user not found for document {$documentId}", null, $callbackQueryId, "❌ Requester user not found.");
        }

        if ($requester->telegram_id != $chatId || $requester->id != $sentBackReceiver->requester_id) {
            return $this->telegramAlert($callbackQueryId, "❌ You are not authorized to send this document.");
        }

        return $this->executeTransaction(
            function () use ($sentBackReceiver, $nextReceiver, $document, $callbackQueryId) {
                $sentBackReceiver->update([
                    'owner_received_status' => 'Received',
                    'owner_received_date' => now()
                ]);

                $this->notifyFirstReceiver($nextReceiver, $document);

                Telegram::answerCallbackQuery(['callback_query_id' => $callbackQueryId, 'text' => "✅ Document sent to {$nextReceiver->receiver->name}.", 'show_alert' => false]);
                return response()->json(['success' => true]);
            },
            "Failed to send document {$documentId} to next receiver",
            $requester->telegram_id,
            $callbackQueryId,
            "❌ Failed to send document to next receiver."
        );
    }

    private function createDocumentTransfer(array $validated): JsonResponse
    {
        $documentTransfer = DocumentTransfer::create([
            'reference_no' => $this->generateReferenceNo(),
            'document_type' => $validated['document_type'],
            'project_name' => $validated['project_name'],
            'description' => $validated['description'],
            'status' => 'Pending',
            'is_send_back' => $validated['is_send_back'] ?? false,
            'created_by' => auth()->id(),
        ]);

        $receivers = array_map(fn($r) => [
            'documents_id' => $documentTransfer->id,
            'document_reference' => $documentTransfer->reference_no,
            'document_name' => $documentTransfer->project_name,
            'status' => 'Pending',
            'requester_id' => auth()->id(),
            'receiver_id' => $r['receiver_id'],
            'created_at' => now(),
            'updated_at' => now(),
        ], $validated['receivers']);

        DocumentsReceiver::insert($receivers);
        $this->notifyFirstReceiver($receivers, $documentTransfer);

        return response()->json([
            'message' => 'Document transfer created successfully.',
            'data' => $documentTransfer->load('receivers.receiver', 'receivers.requester'),
        ], 201);
    }

    private function validateDocumentAndReceiver(string $documentId, string $receiverId, string $chatId, string $callbackQueryId): array|JsonResponse
    {
        $document = DocumentTransfer::with('receivers.receiver', 'receivers.requester')->find($documentId);
        if (!$document) {
            return $this->telegramAlert($callbackQueryId, "❌ Document not found.");
        }

        $receiver = $document->receivers->firstWhere('receiver_id', $receiverId);
        if (!$receiver) {
            return $this->telegramAlert($callbackQueryId, "❌ You are not authorized to receive this document.");
        }

        $user = $receiver->receiver;
        if (!$user) {
            return $this->handleUserNotFound("Receiver user not found for receiver_id {$receiverId}", $receiver->requester->telegram_id, $callbackQueryId, "❌ Receiver user not found.");
        }

        $requester = $receiver->requester;
        if (!$requester) {
            return $this->handleUserNotFound("Requester user not found for document {$documentId}", null, $callbackQueryId, "❌ Requester user not found.");
        }

        return compact('document', 'receiver', 'user', 'requester');
    }

    private function validateDocumentAndSentBackReceiver(string $documentId, string $chatId, string $callbackQueryId): array|JsonResponse
    {
        $document = DocumentTransfer::with('receivers.receiver', 'receivers.requester')->find($documentId);
        if (!$document) {
            return $this->telegramAlert($callbackQueryId, "❌ Document not found.");
        }

        $receiver = $document->receivers->sortByDesc('sent_date')->first();
        if (!$receiver || $receiver->status != 'Sent Back') {
            return $this->telegramAlert($callbackQueryId, "❌ No valid sent back receiver found.");
        }

        $user = $receiver->receiver;
        if (!$user) {
            return $this->handleUserNotFound("Receiver user not found for receiver_id {$receiver->receiver_id}", $receiver->requester->telegram_id, $callbackQueryId, "❌ Receiver user not found.");
        }

        $requester = $receiver->requester;
        if (!$requester) {
            return $this->handleUserNotFound("Requester user not found for document {$documentId}", null, $callbackQueryId, "❌ Requester user not found.");
        }

        if ($requester->telegram_id != $chatId || $requester->id != $receiver->requester_id) {
            return $this->telegramAlert($callbackQueryId, "❌ You are not authorized to complete this document.");
        }

        return compact('document', 'receiver', 'user', 'requester');
    }

    private function notifyFirstReceiver($receiversData, DocumentTransfer $document): void
    {
        $firstReceiverData = is_object($receiversData) && method_exists($receiversData, 'toArray')
            ? collect([$receiversData->toArray()])->first()
            : collect($receiversData)->first();

        if (!$firstReceiverData) {
            return;
        }

        $receiverId = $firstReceiverData['receiver_id'] ?? null;
        $user = User::find($receiverId);
        if (!$user || !$user->telegram_id) {
            return;
        }

        $requester = DocumentsReceiver::where('documents_id', $document->id)->first()->requester;
        if (!$requester || !$requester->telegram_id) {
            return;
        }

        try {
            $response = Telegram::sendMessage([
                'chat_id' => $user->telegram_id,
                'text' => $this->buildTelegramMessage($document, $user->name, $requester->name),
                'parse_mode' => 'Markdown',
                'reply_markup' => Keyboard::make()->inline()->row([
                    Keyboard::inlineButton(['text' => '✅ Mark as Received', 'callback_data' => "receive_{$document->id}-{$user->id}"])
                ]),
            ]);

            DocumentsReceiver::where('documents_id', $document->id)
                ->where('receiver_id', $user->id)
                ->update(['telegram_message_id' => $response->getMessageId()]);
        } catch (\Exception $e) {
            $this->logAndNotifyError("Failed to notify receiver {$receiverId}", $e, $requester->telegram_id);
        }
    }

    private function executeTransaction(callable $callback, string $errorMessage, ?string $telegramId, ?string $callbackQueryId = null, ?string $alertText = null): JsonResponse
    {
        try {
            return DB::transaction($callback);
        } catch (\Exception $e) {
            $this->logAndNotifyError($errorMessage, $e, $telegramId);
            if ($callbackQueryId && $alertText) {
                return $this->telegramAlert($callbackQueryId, $alertText);
            }
            return response()->json(['success' => false, 'message' => $errorMessage], 500);
        }
    }

    private function updateTelegramMessage(string $chatId, ?int $messageId, string $text): void
    {
        if ($messageId) {
            Telegram::editMessageText([
                'chat_id' => $chatId,
                'message_id' => $messageId,
                'text' => $text,
                'parse_mode' => 'Markdown',
            ]);
        }
    }

    private function handleUserNotFound(string $message, ?string $telegramId, string $callbackQueryId, string $alertText): JsonResponse
    {
        $this->logAndNotifyError($message, new \Exception("User not found"), $telegramId);
        return $this->telegramAlert($callbackQueryId, $alertText);
    }

    private function telegramAlert(string $callbackQueryId, string $text): JsonResponse
    {
        try {
            Telegram::answerCallbackQuery(['callback_query_id' => $callbackQueryId, 'text' => $text, 'show_alert' => true]);
        } catch (\Exception $e) {
            $this->logAndNotifyError("Failed to send Telegram alert", $e);
        }
        return response()->json(['success' => false, 'message' => $text]);
    }

    private function logAndNotifyError(string $message, \Exception $e, ?string $telegramId = null): void
    {
        Log::error($message, [
            'error' => $e->getMessage(),
            'file' => $e->getFile(),
            'line' => $e->getLine(),
            'trace' => $e->getTraceAsString(),
        ]);

        if ($telegramId) {
            try {
                Telegram::sendMessage(['chat_id' => $telegramId, 'text' => "⚠️ {$message}: {$e->getMessage()}", 'parse_mode' => 'Markdown']);
            } catch (\Exception $telegramException) {
                Log::error("Failed to notify user of error: {$telegramException->getMessage()}");
            }
        }
    }

    private function generateReferenceNo(): string
    {
        return 'DOC-' . now()->format('Ymd') . '-' . str_pad(
            DocumentTransfer::whereDate('created_at', now())->count() + 1,
            4,
            '0',
            STR_PAD_LEFT
        );
    }

    private function buildTelegramMessage(
        DocumentTransfer $document,
        string $receiverName,
        string $senderName,
        string $status = 'Pending',
        $receivedDate = null,
        $sentDate = null,
        bool $isCreatorNotification = false
    ): string {
        $receivedDate = $receivedDate instanceof \Illuminate\Support\Carbon ? $receivedDate->format('Y-m-d H:i') : ($receivedDate ?? 'N/A');
        $sentDate = $sentDate instanceof \Illuminate\Support\Carbon ? $sentDate->format('Y-m-d H:i') : ($sentDate ?? 'N/A');

        if ($isCreatorNotification) {
            return "📢 *Document " . ($status === 'Completed' ? 'Completed' : 'Sent Back') . "*\n\n"
                . "📄 *Document:* {$document->project_name}\n"
                . "🆔 *Reference:* {$document->reference_no}\n"
                . "👤 *Sent Back by:* {$senderName}\n"
                . "✅ *Owner Received Date:* {$receivedDate}\n"
                . "🗓️ *Send Back Date:* {$sentDate}";
        }

        $message = "📢 *Dear {$receiverName},*\n\n"
            . "📄 *Document:* {$document->project_name}\n"
            . "�ID *Reference:* {$document->reference_no}\n"
            . "📝 *Description:* {$document->description}\n"
            . "📂 *Document Type:* {$document->document_type}\n"
            . "👤 *Sent From:* {$senderName}\n"
            . "🔄 *Status:* {$status}";

        if (in_array($status, ['Received', 'Sent Back'])) {
            $message .= "\n✅ *Received Date:* {$receivedDate}";
        }

        if ($status === 'Sent Back') {
            $message .= "\n🗓️ *Send Back Date:* {$sentDate}";
        }

        return $message;
    }

    private function getNextReceiver(DocumentTransfer $document, int $currentReceiverId)
    {
        $receivers = $document->receivers->sortBy('id')->values();
        $currentIndex = $receivers->search(fn($receiver) => $receiver->receiver_id === $currentReceiverId);

        return ($currentIndex !== false && $currentIndex < $receivers->count() - 1)
            ? $receivers->get($currentIndex + 1)
            : null;
    }

    public function webhook(Request $request): JsonResponse
    {
        $callbackData = $request->input('callback_query.data');
        if (!$callbackData) {
            return response()->json(['success' => false]);
        }

        $request->merge([
            'chat_id' => $request->input('callback_query.from.id'),
            'message_id' => $request->input('callback_query.message.message_id'),
            'callback_query_id' => $request->input('callback_query.id'),
            'callback_data' => $callbackData,
        ]);

        return match (true) {
            str_starts_with($callbackData, 'receive_') => $this->receive($request),
            str_starts_with($callbackData, 'sendback_') => $this->sendBack($request),
            str_starts_with($callbackData, 'sendto_') => $this->sendToNextReceiver($request),
            str_starts_with($callbackData, 'complete_') => $this->completeDocument($request),
            default => response()->json(['success' => false]),
        };
    }
}