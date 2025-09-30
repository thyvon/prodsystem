<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Telegram\Bot\Laravel\Facades\Telegram;
use App\Models\DocumentTransfer;

class TelegramController extends Controller
{
    public function webhook(Request $request)
    {
        $update = $request->all();

        try {
            if (!isset($update['callback_query'])) return response()->json([]);

            $callback = $update['callback_query'];
            $callbackData = $callback['data'] ?? null;
            $telegramUserId = $callback['from']['id'] ?? null;
            $messageId = $callback['message']['message_id'] ?? null;
            $chatId = $callback['message']['chat']['id'] ?? null;

            if (!$callbackData || !str_starts_with($callbackData, 'receive_')) return response()->json([]);

            [$documentId, $receiverId] = explode('-', str_replace('receive_', '', $callbackData));

            $document = DocumentTransfer::with(['receivers.receiver', 'created_by'])->find($documentId);
            if (!$document) {
                return $this->answerCallback($callback['id'], 'Document not found');
            }

            $result = $document->markAsReceived($receiverId, $telegramUserId);

            if (!$result['success']) {
                return $this->answerCallback($callback['id'], $result['message']);
            }

            $this->answerCallback($callback['id'], 'Document received successfully');

            $receivedDate = $result['receiver']->received_date->format('Y-m-d H:i');

            $messageText = "📢 *Dear {$result['receiver']->receiver->name},*\n\n"
                ."📄 *You have a new document!*\n\n"
                ."📝 *Description:* {$result['document']->description}\n"
                ."📂 *Document Type:* {$result['document']->document_type}\n"
                ."🏷️ *Project:* {$result['document']->project_name}\n"
                ."👤 *Sent From:* {$result['document']->created_by->name}\n"
                ."🆔 *Reference:* {$result['document']->reference_no}\n\n"
                ."✅ *Received Date:* {$receivedDate}";

            Telegram::editMessageText([
                'chat_id' => $chatId,
                'message_id' => $messageId,
                'text' => $messageText,
                'parse_mode' => 'Markdown',
            ]);

            return response()->json([]);
        } catch (\Exception $e) {
            Log::error('Telegram Webhook Error', ['error' => $e->getMessage(), 'update' => $update]);
            return response()->json([]);
        }
    }

    private function answerCallback(string $callbackQueryId, string $message)
    {
        Telegram::answerCallbackQuery([
            'callback_query_id' => $callbackQueryId,
            'text' => $message,
            'show_alert' => true,
        ]);
    }
}
