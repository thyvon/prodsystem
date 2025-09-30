<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\DocumentTransfer;
use Telegram\Bot\Laravel\Facades\Telegram;
use Telegram\Bot\Keyboard\Keyboard;

class TelegramController extends Controller
{
    public function webhook(Request $request)
    {
        $callbackData = $request->input('callback_query.data');
        $telegramUserId = $request->input('callback_query.from.id');
        $messageId = $request->input('callback_query.message.message_id');

        if (!$callbackData) return response()->json();

        // 🔹 Parse action and IDs
        if (preg_match('/^(receive|sendback)_(\d+)_(\d+)$/', $callbackData, $matches)) {
            [$full, $action, $documentId, $receiverId] = $matches;

            $document = DocumentTransfer::with(['receivers.receiver', 'creator'])->findOrFail($documentId);

            // 🔹 Determine status
            $status = $action === 'receive' ? 'Received' : 'Sent Back';
            $result = $document->updateReceiverStatus((int)$receiverId, (int)$telegramUserId, $status);

            $text = $result['success']
                ? ($status === 'Received'
                    ? "✅ Document received successfully:\n\n*{$document->project_name}*"
                    : "🔄 Document sent back successfully:\n\n*{$document->project_name}*")
                : "❌ Failed: {$result['message']}";

            // 🔹 Send back button if allowed
            $keyboard = Keyboard::make()->inline();
            if ($status === 'Received' && $result['success'] && $document->is_send_back) {
                $keyboard->row([
                    Keyboard::inlineButton([
                        'text' => '🔄 Send Back',
                        'callback_data' => "sendback_{$document->id}_{$receiverId}"
                    ])
                ]);
            }

            Telegram::editMessageText([
                'chat_id' => $telegramUserId,
                'message_id' => $messageId,
                'text' => $text,
                'parse_mode' => 'Markdown',
                'reply_markup' => $keyboard
            ]);
        }

        return response()->json();
    }
}
