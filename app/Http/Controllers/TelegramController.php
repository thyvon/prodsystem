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

            // Extract document_id and receiver_id from callback data
            [$documentId, $receiverId] = explode('-', str_replace('receive_', '', $callbackData));

            // Load document transfer with receivers
            $document = DocumentTransfer::with('receivers.receiver')->find($documentId);
            if (!$document) {
                $this->answerCallback($callback['id'], 'Document not found');
                return response()->json([]);
            }

            // Find the receiver
            $receiver = $document->receivers->firstWhere('receiver_id', $receiverId);
            if (!$receiver || !$receiver->receiver) {
                $this->answerCallback($callback['id'], 'You are not authorized to receive this document');
                return response()->json([]);
            }

            // Validate Telegram ID matches
            if ($receiver->receiver->telegram_id != $telegramUserId) {
                $this->answerCallback($callback['id'], 'This Telegram account is not authorized');
                return response()->json([]);
            }

            // Update receiver status
            if ($receiver->status !== 'Received') {
                $receiver->update([
                    'status' => 'Received',
                    'received_date' => now(),
                ]);
            }

            // Update document status if last receiver
            $allReceivers = $document->receivers->sortBy('id')->values();
            if ($receiver->id === $allReceivers->last()->id) {
                $document->update(['status' => 'Completed']);
            }

            // Answer callback
            $this->answerCallback($callback['id'], 'Document received successfully');

            // Edit Telegram message to show received
            Telegram::editMessageText([
                'chat_id' => $chatId,
                'message_id' => $messageId,
                'text' => "✅ Document *{$document->project_name}* received.",
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
    }
}
