<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Telegram\Bot\Laravel\Facades\Telegram;
use Telegram\Bot\Keyboard\Keyboard;
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
            // Handle callback queries (button clicks)
            if (isset($update['callback_query'])) {
                $callbackQuery = $update['callback_query'];
                $callbackData = $callbackQuery['data'] ?? null;
                $telegramUserId = $callbackQuery['from']['id'] ?? null;
                $messageId = $callbackQuery['message']['message_id'] ?? null;
                $chatId = $callbackQuery['message']['chat']['id'] ?? null;

                if (!$callbackData || !str_starts_with($callbackData, 'receive_')) {
                    return response()->json([]);
                }

                // Parse document_id and receiver_id
                [$documentId, $receiverId] = explode('-', str_replace('receive_', '', $callbackData));

                // Load document transfer
                $documentTransfer = DocumentTransfer::with(['receivers.receiver'])->find($documentId);
                if (!$documentTransfer) {
                    $this->answerCallback($callbackQuery['id'], 'Document not found');
                    return response()->json([]);
                }

                // Find current receiver
                $receiver = $documentTransfer->receivers->firstWhere('receiver_id', $receiverId);
                if (!$receiver) {
                    $this->answerCallback($callbackQuery['id'], 'You are not authorized to receive this document');
                    return response()->json([]);
                }

                // Ensure sequential receiving
                $allReceivers = $documentTransfer->receivers->sortBy('id')->values();
                $currentIndex = $allReceivers->search(fn($r) => $r->id == $receiver->id);

                if ($currentIndex > 0 && $allReceivers[$currentIndex - 1]->status !== 'Received') {
                    $this->answerCallback($callbackQuery['id'], 'Previous receivers must receive first');
                    return response()->json([]);
                }

                // Update current receiver
                if ($receiver->status !== 'Received') {
                    $receiver->update([
                        'status' => 'Received',
                        'received_date' => now(),
                    ]);
                }

                // Update document status if last receiver
                $lastReceiver = $allReceivers->last();
                if ($receiver->id === $lastReceiver->id) {
                    $documentTransfer->update(['status' => 'Completed']);
                } else {
                    // Notify next receiver
                    $nextReceiver = $allReceivers[$currentIndex + 1] ?? null;
                    if ($nextReceiver && $nextReceiver->receiver->telegram_id) {
                        $this->sendTelegramNotification($nextReceiver->receiver->telegram_id, $documentTransfer);
                    }
                }

                // Answer callback
                $this->answerCallback($callbackQuery['id'], 'Document received successfully');

                // Optionally edit the Telegram message
                Telegram::editMessageText([
                    'chat_id' => $chatId,
                    'message_id' => $messageId,
                    'text' => "✅ Document *{$documentTransfer->project_name}* received.",
                    'parse_mode' => 'Markdown',
                ]);

                return response()->json([]);
            }

            return response()->json([]);
        } catch (\Exception $e) {
            Log::error('Telegram Webhook Error', [
                'error' => $e->getMessage(),
                'update' => $update
            ]);
            return response()->json([]);
        }
    }

    /**
     * Send notification to receiver
     */
    private function sendTelegramNotification(int $chatId, DocumentTransfer $documentTransfer)
    {
        $keyboard = Keyboard::make()
            ->inline()
            ->row(
                Keyboard::inlineButton([
                    'text' => 'Mark as Received',
                    'callback_data' => 'receive_'.$documentTransfer->id.'-'.$this->getReceiverId($chatId, $documentTransfer)
                ])
            );

        Telegram::sendMessage([
            'chat_id' => $chatId,
            'text' => "📄 You are next to receive document: *{$documentTransfer->project_name}*\nReference: {$documentTransfer->reference_no}",
            'parse_mode' => 'Markdown',
            'reply_markup' => $keyboard,
        ]);
    }

    /**
     * Find receiver ID by telegram chat_id
     */
    private function getReceiverId(int $chatId, DocumentTransfer $documentTransfer)
    {
        $receiver = $documentTransfer->receivers->firstWhere('receiver.telegram_id', $chatId);
        return $receiver ? $receiver->receiver_id : null;
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
