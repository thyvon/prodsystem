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

        if (str_starts_with($callbackData, 'receive_')) {
            [$documentId, $receiverId] = explode('-', str_replace('receive_', '', $callbackData));

            $document = DocumentTransfer::with(['receivers.receiver'])->findOrFail($documentId);
            $result = $document->markAsReceived((int)$receiverId, (int)$telegramUserId);

            if ($result['success']) {
                $keyboard = Keyboard::make()->inline();
                if ($document->is_send_back == 1) {
                    $keyboard->row([
                        Keyboard::inlineButton([
                            'text' => '🔄 Send Back',
                            'callback_data' => 'sendback_'.$document->id.'-'.$receiverId
                        ])
                    ]);
                }

                Telegram::editMessageText([
                    'chat_id' => $telegramUserId,
                    'message_id' => $messageId,
                    'text' => $document->telegramMessageText($result['receiver']) . "\n\n✅ Document received",
                    'parse_mode' => 'Markdown',
                    'reply_markup' => $keyboard
                ]);
            }

        } elseif (str_starts_with($callbackData, 'sendback_')) {
            [$documentId, $receiverId] = explode('-', str_replace('sendback_', '', $callbackData));

            $document = DocumentTransfer::with(['receivers.receiver', 'creator'])->findOrFail($documentId);
            $result = $document->sendBack((int)$receiverId, (int)$telegramUserId);

            if ($result['success']) {
                $creator = $document->creator;
                if ($creator && $creator->telegram_id) {
                    Telegram::sendMessage([
                        'chat_id' => $creator->telegram_id,
                        'text' => "📢 *Document Sent Back*\n\n"
                            .$document->telegramMessageText($result['receiver']),
                        'parse_mode' => 'Markdown',
                    ]);
                }

                Telegram::editMessageText([
                    'chat_id' => $telegramUserId,
                    'message_id' => $messageId,
                    'text' => $document->telegramMessageText($result['receiver']) . "\n\n🔄 Document sent back",
                    'parse_mode' => 'Markdown',
                ]);
            }
        }

        return response()->json();
    }
}
