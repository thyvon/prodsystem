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

        $query = DocumentTransfer::with(['receivers.receiver', 'creator'])->whereNull('deleted_at');

        if ($search = $validated['search'] ?? null) {
            $query->where(fn($q) => $q->where('reference_no', 'like', "%$search%")
                ->orWhere('document_type', 'like', "%$search%")
                ->orWhere('project_name', 'like', "%$search%")
                ->orWhere('description', 'like', "%$search%"));
        }

        $documentTransfers = $query->orderBy(
            $validated['sortColumn'] ?? 'id',
            $validated['sortDirection'] ?? 'desc'
        )->paginate($validated['limit'] ?? self::DEFAULT_LIMIT, ['*'], 'page', $validated['page'] ?? 1);

        return response()->json([
            'data' => $documentTransfers->map(fn($transfer) => [
                'id' => $transfer->id,
                'reference_no' => $transfer->reference_no,
                'document_type' => $transfer->document_type,
                'project_name' => $transfer->project_name,
                'description' => $transfer->description,
                'receivers' => $transfer->receivers->map(fn($r) => [
                    'receiver_id' => $r->receiver_id,
                    'name' => $r->receiver->name ?? 'N/A',
                    'email' => $r->receiver->email ?? null,
                    'status' => $r->status,
                    'received_date' => $r->received_date,
                    'sent_date' => $r->sent_date,
                    'telegram_message_id' => $r->telegram_message_id,
                    'telegram_creator_message_id' => $r->telegram_creator_message_id,
                ]),
                'created_by' => $transfer->creator->name ?? null,
                'status' => $transfer->status,
                'created_at' => $transfer->created_at,
                'updated_at' => $transfer->updated_at,
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

        try {
            return DB::transaction(function () use ($validated) {
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

                DocumentTransferResponse::insert($receivers);
                $this->notifyFirstReceiver($receivers, $documentTransfer);

                return response()->json([
                    'message' => 'Document transfer created successfully.',
                    'data' => $documentTransfer->load('receivers.receiver'),
                ], 201);
            });
        } catch (\Exception $e) {
            $this->logAndNotifyError('Failed to create document transfer', $e, auth()->user()->telegram_id);
            return response()->json(['message' => 'Failed to create document transfer.', 'error' => $e->getMessage()], 500);
        }
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

    private function generateReferenceNo(): string
    {
        return 'DOC-' . now()->format('Ymd') . '-' . str_pad(
            DocumentTransfer::whereDate('created_at', now())->count() + 1,
            4,
            '0',
            STR_PAD_LEFT
        );
    }

    private function notifyFirstReceiver(array $receivers, DocumentTransfer $documentTransfer): void
    {
        $firstReceiver = collect($receivers)->first();
        if (!$firstReceiver) return;

        $user = User::find($firstReceiver['receiver_id']);
        if (!$user || !$user->telegram_id) return;

        $creator = auth()->user();
        $message = $this->buildTelegramMessage($documentTransfer, $user->name, $creator->name);

        try {
            $response = Telegram::sendMessage([
                'chat_id' => $user->telegram_id,
                'text' => $message,
                'parse_mode' => 'Markdown',
                'reply_markup' => Keyboard::make()->inline()->row([
                    Keyboard::inlineButton(['text' => '✅ Mark as Received', 'callback_data' => "receive_{$documentTransfer->id}-{$user->id}"])
                ]),
            ]);

            $documentTransfer->receivers()->where('receiver_id', $user->id)
                ->update(['telegram_message_id' => $response->getMessageId()]);
        } catch (\Exception $e) {
            $this->logAndNotifyError("Failed to notify receiver {$user->id}", $e, $creator->telegram_id);
        }
    }

    public function receive(Request $request): JsonResponse
    {
        [$documentId, $receiverId] = explode('-', str_replace('receive_', '', $request->input('callback_data')));
        $chatId = $request->input('chat_id');
        $messageId = $request->input('message_id');
        $callbackQueryId = $request->input('callback_query_id');

        $document = DocumentTransfer::with('receivers.receiver', 'creator')->find($documentId);
        if (!$document) {
            return $this->telegramAlert($callbackQueryId, "❌ Document not found.");
        }

        $receiver = $document->receivers->firstWhere('receiver_id', $receiverId);
        if (!$receiver) {
            return $this->telegramAlert($callbackQueryId, "❌ You are not authorized to receive this document.");
        }

        $user = $receiver->receiver;
        $creator = $document->creator;
        $receivedDate = now();

        try {
            DB::transaction(function () use ($receiver, $receivedDate, $document, $user, $creator, $chatId, $messageId, $callbackQueryId) {
                $receiver->update(['status' => 'Received', 'received_date' => $receivedDate]);

                $response = Telegram::editMessageText([
                    'chat_id' => $chatId,
                    'message_id' => $receiver->telegram_message_id ?? $messageId,
                    'text' => $this->buildTelegramMessage($document, $user->name, $creator->name, 'Received', $receivedDate),
                    'parse_mode' => 'Markdown',
                ]);

                $receiver->update(['telegram_message_id' => $response->getMessageId()]);

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
            });

            Telegram::answerCallbackQuery(['callback_query_id' => $callbackQueryId, 'text' => "✅ Document marked as Received.", 'show_alert' => false]);
            return response()->json(['success' => true]);
        } catch (\Exception $e) {
            $this->logAndNotifyError("Failed to process receive for document {$documentId}", $e, $user->telegram_id);
            return $this->telegramAlert($callbackQueryId, "❌ Failed to mark document as received.");
        }
    }

    public function sendBack(Request $request): JsonResponse
    {
        [$documentId, $receiverId] = explode('-', str_replace('sendback_', '', $request->input('callback_data')));
        $chatId = $request->input('chat_id');
        $messageId = $request->input('message_id');
        $callbackQueryId = $request->input('callback_query_id');

        $document = DocumentTransfer::with('receivers.receiver', 'creator')->find($documentId);
        if (!$document) {
            return $this->telegramAlert($callbackQueryId, "❌ Document not found.");
        }

        $receiver = $document->receivers->firstWhere('receiver_id', $receiverId);
        if (!$receiver) {
            return $this->telegramAlert($callbackQueryId, "❌ You are not authorized.");
        }

        $user = $receiver->receiver;
        $creator = $document->creator;
        $sentDate = now();

        try {
            DB::transaction(function () use ($receiver, $sentDate, $document, $user, $creator, $chatId, $messageId) {
                $receiver->update(['status' => 'Sent Back', 'sent_date' => $sentDate]);

                $response = Telegram::editMessageText([
                    'chat_id' => $chatId,
                    'message_id' => $receiver->telegram_message_id ?? $messageId,
                    'text' => $this->buildTelegramMessage($document, $user->name, $creator->name, 'Sent Back', $receiver->received_date, $sentDate),
                    'parse_mode' => 'Markdown',
                ]);

                $receiver->update(['telegram_message_id' => $response->getMessageId()]);

                if ($creator && $creator->telegram_id) {
                    $response = Telegram::sendMessage([
                        'chat_id' => $creator->telegram_id,
                        'text' => $this->buildTelegramMessage($document, $creator->name, $user->name, 'Sent Back', $receiver->received_date, $sentDate, true),
                        'parse_mode' => 'Markdown',
                    ]);

                    $receiver->update(['telegram_creator_message_id' => $response->getMessageId()]);
                }
            });

            Telegram::answerCallbackQuery(['callback_query_id' => $callbackQueryId, 'text' => "✅ Document marked as Sent Back.", 'show_alert' => false]);
            return response()->json(['success' => true]);
        } catch (\Exception $e) {
            $this->logAndNotifyError("Failed to process send back for document {$documentId}", $e, $user->telegram_id);
            return $this->telegramAlert($callbackQueryId, "❌ Failed to send back document.");
        }
    }

    public function sendToNextReceiver(Request $request): JsonResponse
    {
        [$documentId, $receiverId] = explode('-', str_replace('sendto_', '', $request->input('callback_data')));
        $callbackQueryId = $request->input('callback_query_id');

        $document = DocumentTransfer::with('receivers.receiver')->find($documentId);
        if (!$document) {
            return $this->telegramAlert($callbackQueryId, "❌ Document not found.");
        }

        $nextReceiver = $document->receivers->firstWhere('receiver_id', $receiverId);
        if (!$nextReceiver) {
            return $this->telegramAlert($callbackQueryId, "❌ Next receiver not found.");
        }

        $this->notifyFirstReceiver([$nextReceiver->toArray()], $document);
        Telegram::answerCallbackQuery(['callback_query_id' => $callbackQueryId, 'text' => "✅ Document sent to {$nextReceiver->receiver->name}.", 'show_alert' => false]);
        return response()->json(['success' => true]);
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
        $errorDetails = [
            'error' => $e->getMessage(),
            'file' => $e->getFile(),
            'line' => $e->getLine(),
            'trace' => $e->getTraceAsString(),
        ];

        Log::error($message, $errorDetails);

        $debugChatId = env('TELEGRAM_DEBUG_CHAT_ID');
        if ($debugChatId) {
            try {
                Telegram::sendMessage([
                    'chat_id' => $debugChatId,
                    'text' => "⚠️ *{$message}*\n\nError: {$e->getMessage()}\nFile: {$e->getFile()}:{$e->getLine()}\nTrace: ```\n{$e->getTraceAsString()}\n```",
                    'parse_mode' => 'Markdown',
                ]);
            } catch (\Exception $telegramException) {
                Log::error("Failed to send Telegram debug alert: {$telegramException->getMessage()}");
            }
        }

        if ($telegramId) {
            try {
                Telegram::sendMessage(['chat_id' => $telegramId, 'text' => "⚠️ {$message}: {$e->getMessage()}", 'parse_mode' => 'Markdown']);
            } catch (\Exception $telegramException) {
                Log::error("Failed to notify user of error: {$telegramException->getMessage()}");
            }
        }
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
            return "📢 *Document Sent Back*\n\n"
                . "📄 *Document:* {$document->project_name}\n"
                . "🆔 *Reference:* {$document->reference_no}\n"
                . "👤 *Sent Back by:* {$senderName}\n"
                . "✅ *Received Date:* {$receivedDate}\n"
                . "🗓️ *Send Back Date:* {$sentDate}";
        }

        $message = "📢 *Dear {$receiverName},*\n\n"
            . "📄 *Document:* {$document->project_name}\n"
            . "🆔 *Reference:* {$document->reference_no}\n"
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
            default => response()->json(['success' => false]),
        };
    }
}