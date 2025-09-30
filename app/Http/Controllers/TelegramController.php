<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Telegram\Bot\Laravel\Facades\Telegram;
use App\Models\DocumentTransfer;

class TelegramController extends Controller
{
    /**
     * Handle Telegram webhook
     */
    public function webhook(Request $request)
    {
        $update = $request->all();

        try {
            // Only handle callback queries
            if (!isset($update['callback_query'])) {
                return response()->json([]);
            }

            $callback = $update['callback_query'];
            $callbackData = $callback['data'] ?? null;
            $telegramUserId = $callback['from']['id'] ?? null;
            $messageId = $callback['message']['message_id'] ?? null;
            $chatId = $callback['message']['chat']['id'] ?? null;

            // Only handle "receive_" actions
            if (!$callbackData || !str_starts_with($callbackData, 'receive_')) {
                return response()->json([]);
            }

            // Extract document_id and receiver_id
            [$documentId, $receiverId] = explode('-', str_replace('receive_', '', $callbackData));

            // Load document with receivers and creator
            $document = DocumentTransfer::with(['receivers.receiver', 'creator'])->find($documentId);
            if (!$document) {
                return $this->answerCallback($callback['id'], 'Document not found');
            }

            // Use model method to mark as received
            $result = $document->markAsReceived($receiverId, $telegramUserId);

            if (!$result['success']) {
                return $this->answerCallback($callback['id'], $result['message']);
            }

            $receiver = $result['receiver'];

            // Answer callback
            $this->answerCallback($callback['id'], 'Document received successfully');

            // Prepare message text including received date
            $messageText = $document->telegramMessageText($receiver);

            // Edit Telegram message
            Telegram::editMessageText([
                'chat_id' => $chatId,
                'message_id' => $messageId,
                'text' => $messageText,
                'parse_mode' => 'Markdown',
            ]);

            return response()->json([]);
        } catch (\Exception $e) {
            Log::error('Telegram Webhook Error', [
                'error' => $e->getMessage(),
                'update' => $update,
            ]);

            return response()->json([]);
        }
    }

    /**
     * Answer Telegram callback query
     */
    private function answerCallback(string $callbackQueryId, string $message)
    {
        Telegram::answerCallbackQuery([
            'callback_query_id' => $callbackQueryId,
            'text' => $message,
            'show_alert' => true,
        ]);

        return response()->json([]);
    }
}
