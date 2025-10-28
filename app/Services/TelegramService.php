<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;
use Telegram\Bot\Laravel\Facades\Telegram;
use Telegram\Bot\Objects\Message;
use Telegram\Bot\Keyboard\Keyboard;
use App\Models\User;

class TelegramService
{
    /**
     * Send a message.
     *
     * @param string|int $chatId
     * @param string $text
     * @param array|null $inlineKeyboardRows  Array of rows, each row is array of button arrays
     * @param string $parseMode
     * @return Message|null
     */
    public function sendMessage(string|int $chatId, string $text, ?array $inlineKeyboardRows = null, string $parseMode = 'Markdown'): ?Message
    {
        try {
            $payload = [
                'chat_id' => $chatId,
                'text' => $text,
                'parse_mode' => $parseMode,
            ];

            if (!empty($inlineKeyboardRows)) {
                $payload['reply_markup'] = ['inline_keyboard' => $inlineKeyboardRows];
            }

            return Telegram::sendMessage($payload);
        } catch (\Exception $e) {
            $this->logError("sendMessage failed for {$chatId}", $e);
            return null;
        }
    }

    /**
     * Edit message text.
     *
     * @return Message|null
     */
    public function editMessageText(string|int $chatId, int $messageId, string $text, ?array $inlineKeyboardRows = null, string $parseMode = 'Markdown'): ?Message
    {
        try {
            $payload = [
                'chat_id' => $chatId,
                'message_id' => $messageId,
                'text' => $text,
                'parse_mode' => $parseMode,
            ];

            if (!empty($inlineKeyboardRows)) {
                $payload['reply_markup'] = ['inline_keyboard' => $inlineKeyboardRows];
            }

            return Telegram::editMessageText($payload);
        } catch (\Exception $e) {
            $this->logError("editMessageText failed for {$chatId} message {$messageId}", $e);
            return null;
        }
    }

    /**
     * Delete message.
     */
    public function deleteMessage(string|int $chatId, int $messageId): bool
    {
        try {
            Telegram::deleteMessage(['chat_id' => $chatId, 'message_id' => $messageId]);
            return true;
        } catch (\Exception $e) {
            $this->logError("deleteMessage failed for {$chatId} message {$messageId}", $e);
            return false;
        }
    }

    /**
     * Answer callback query.
     */
    public function answerCallbackQuery(string $callbackQueryId, string $text = '', bool $showAlert = false): bool
    {
        try {
            Telegram::answerCallbackQuery([
                'callback_query_id' => $callbackQueryId,
                'text' => $text,
                'show_alert' => $showAlert,
            ]);
            return true;
        } catch (\Exception $e) {
            $this->logError("answerCallbackQuery failed {$callbackQueryId}", $e);
            return false;
        }
    }

    /**
     * Send register button (used on /start).
     */
    public function sendRegisterButton(string|int $chatId): ?Message
    {
        $keyboard = [
            [
                ['text' => 'Register', 'callback_data' => 'register']
            ]
        ];

        $text = "📄 **Welcome to Document Transfer Bot!**\n\n🔔 Click below to register and start receiving notifications about your documents.\n\n➡️ Register now to stay updated!";

        // Use Telegram facade for consistency
        return $this->sendMessage($chatId, $text, $keyboard, 'Markdown');
    }

    /**
     * Register user from callback payload info.
     *
     * @param array $from  callback_query.from array
     * @param string|int $chatId
     * @param string $callbackQueryId
     * @param int|null $messageId
     * @return \App\Models\User|null
     */
    public function registerUser(array $from, string|int $chatId, string $callbackQueryId, ?int $messageId = null): ?User
    {
        try {
            $firstName = $from['first_name'] ?? '';
            $lastName = $from['last_name'] ?? '';
            $fullName = trim($firstName . ' ' . $lastName) ?: 'Telegram User';

            $user = User::firstOrCreate(
                ['telegram_id' => $chatId],
                [
                    'name' => $fullName,
                    'email' => "user{$chatId}@example.com",
                    'password' => bcrypt('password')
                ]
            );

            // answer callback
            $this->answerCallbackQuery($callbackQueryId, 'You are now registered! 🎉', false);

            // optionally edit original message
            if ($messageId) {
                $this->editMessageText($chatId, $messageId, '✅ You are registered successfully.');
            }

            return $user;
        } catch (\Exception $e) {
            $this->logError("registerUser failed for chat {$chatId}", $e);
            return null;
        }
    }

    /**
     * Notify a receiver (used to send first notification).
     *
     * @param \App\Models\DocumentTransfer $document
     * @param \App\Models\User $receiverUser
     * @param \App\Models\User $requesterUser
     * @param string $receiveCallbackData
     * @return Message|null
     */
    public function notifyReceiver($document, User $receiverUser, User $requesterUser, string $receiveCallbackData = null): ?Message
    {
        if (!$receiverUser->telegram_id) return null;

        $keyboard = null;
        if ($receiveCallbackData) {
            $keyboard = [
                [
                    ['text' => '✅ Mark as Received', 'callback_data' => $receiveCallbackData]
                ]
            ];
        }

        $text = $this->buildDocumentMessage($document, $receiverUser->name, $requesterUser->name);

        try {
            $response = $this->sendMessage($receiverUser->telegram_id, $text, $keyboard, 'Markdown');
            return $response;
        } catch (\Exception $e) {
            $this->logError("notifyReceiver failed for user {$receiverUser->id}", $e);
            return null;
        }
    }

    /**
     * Build document message text (reuse the same format).
     */
    public function buildDocumentMessage($document, string $receiverName, string $senderName, string $status = 'Pending', $receivedDate = null, $sentDate = null, bool $isCreatorNotification = false): string
    {
        $receivedDate = $receivedDate instanceof \Illuminate\Support\Carbon ? $receivedDate->format('M d, Y h:i A') : ($receivedDate ?? 'N/A');
        $sentDate = $sentDate instanceof \Illuminate\Support\Carbon ? $sentDate->format('M d, Y h:i A') : ($sentDate ?? 'N/A');

        if ($isCreatorNotification) {
            return "📢 *Document " . ($status === 'Completed' ? 'Completed' : 'Sent Back') . "*\n\n"
                . "📄 *Document:* {$document->project_name}\n"
                . "🆔 *Reference:* {$document->reference_no}\n"
                . "👤 *Sent Back by:* {$senderName}\n"
                . "✅ *Receiver Date:* {$receivedDate}\n"
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

    private function logError(string $message, \Throwable $e): void
    {
        Log::error($message, [
            'error' => $e->getMessage(),
            'file' => $e->getFile(),
            'line' => $e->getLine(),
            'trace' => $e->getTraceAsString(),
        ]);
    }
}