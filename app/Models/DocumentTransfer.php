<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Models\User;
use App\Models\DocumentTransferResponse;
use Telegram\Bot\Keyboard\Keyboard;
use Telegram\Bot\Laravel\Facades\Telegram;

class DocumentTransfer extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'reference_no',
        'document_type',
        'project_name',
        'description',
        'status',
        'is_send_back',
        'created_by',
        'updated_by',
        'deleted_by',
    ];

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function receivers()
    {
        return $this->hasMany(DocumentTransferResponse::class, 'documents_id');
    }

    // 🔹 Update receiver status (Received or Sent Back)
    public function updateReceiverStatus(int $receiverId, string $status): array
    {
        $receiver = $this->receivers()->with('receiver')->firstWhere('receiver_id', $receiverId);

        if (!$receiver || !$receiver->receiver) {
            return ['success' => false, 'message' => 'Receiver not found or unauthorized.'];
        }

        if ($receiver->status === $status) {
            return ['success' => true, 'receiver' => $receiver];
        }

        $updateData = ['status' => $status];
        if ($status === 'Received') $updateData['received_date'] = now();
        if ($status === 'Sent Back') $updateData['sent_date'] = now();

        $receiver->update($updateData);

        // Update document status
        if ($status === 'Received') {
            if ($this->receivers()->count() === $this->receivers()->where('status', 'Received')->count()) {
                $this->update(['status' => 'Completed']);
            }
        } elseif ($status === 'Sent Back') {
            $this->update(['status' => 'Sent Back']);
        }

        return ['success' => true, 'receiver' => $receiver, 'document' => $this];
    }

    // 🔹 Notify receiver via Telegram
    public function notifyReceiver(User $user, string $action = 'receive'): void
    {
        if (!$user || !$user->telegram_id) return;

        $receiverData = $this->receivers()->where('receiver_id', $user->id)->first();
        if (!$receiverData) return;

        $message = $this->telegramMessageText($receiverData);

        $keyboard = Keyboard::make()->inline()->row([
            Keyboard::inlineButton([
                'text' => $action === 'receive' ? '✅ Mark as Received' : '🔄 Send Back',
                'callback_data' => "{$action}_{$this->id}_{$user->id}"
            ])
        ]);

        Telegram::sendMessage([
            'chat_id' => $user->telegram_id,
            'text' => $message,
            'parse_mode' => 'Markdown',
            'reply_markup' => $keyboard,
        ]);
    }

    // 🔹 Generate Telegram message text
    public function telegramMessageText($receiverData): string
    {
        $receivedDate = $receiverData->received_date
            ? $receiverData->received_date->format('Y-m-d H:i')
            : null;

        $message = "📢 *Dear {$receiverData->receiver->name},*\n\n"
            ."📄 *You have a new document!*\n\n"
            ."📝 *Description:* {$this->description}\n"
            ."📂 *Document Type:* {$this->document_type}\n"
            ."🏷️ *Project:* {$this->project_name}\n"
            ."👤 *Sent From:* {$this->creator->name}\n"
            ."🆔 *Reference:* {$this->reference_no}"
            .($receivedDate ? "\n\n✅ *Received Date:* {$receivedDate}" : '');

        return $message;
    }
}
