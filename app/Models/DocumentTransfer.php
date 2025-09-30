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

    /**
     * 🔹 Update receiver status (Receive or Send Back) and send Telegram feedback.
     */
    public function updateReceiverStatus(int $receiverId, int $telegramUserId, string $status): array
    {
        $receiver = $this->receivers()->with('receiver')->firstWhere('receiver_id', $receiverId);

        if (!$receiver || !$receiver->receiver) {
            return ['success' => false, 'message' => 'Receiver not found or unauthorized.'];
        }

        if ($receiver->receiver->telegram_id != $telegramUserId) {
            return ['success' => false, 'message' => 'This Telegram account is not authorized'];
        }

        if ($receiver->status === $status) {
            return ['success' => true, 'receiver' => $receiver];
        }

        $updateData = ['status' => $status];
        if ($status === 'Received') $updateData['received_date'] = now();
        if ($status === 'Sent Back') $updateData['sent_date'] = now();

        $receiver->update($updateData);

        // Update document status
        if ($status === 'Received' && $this->receivers()->count() === $this->receivers()->where('status', 'Received')->count()) {
            $this->update(['status' => 'Completed']);
        } elseif ($status === 'Sent Back') {
            $this->update(['status' => 'Sent Back']);
        }

        // Notify creator if document is sent back
        if ($status === 'Sent Back' && $this->creator?->telegram_id) {
            Telegram::sendMessage([
                'chat_id' => $this->creator->telegram_id,
                'text' => "📢 Document Sent Back\nDocument: {$this->project_name}\nReference: {$this->reference_no}\nReceiver: {$receiver->receiver->name}",
                'parse_mode' => 'Markdown',
            ]);
        }

        return ['success' => true, 'receiver' => $receiver, 'document' => $this];
    }

    /**
     * 🔹 Generate Telegram message text
     */
    public function telegramMessageText($receiverData): string
    {
        $receivedDate = $receiverData->received_date
            ? $receiverData->received_date->format('Y-m-d H:i')
            : null;

        return "📢 *Dear {$receiverData->receiver->name},*\n\n"
            ."📄 *You have a new document!*\n\n"
            ."📝 *Description:* {$this->description}\n"
            ."📂 *Document Type:* {$this->document_type}\n"
            ."🏷️ *Project:* {$this->project_name}\n"
            ."👤 *Sent From:* {$this->creator->name}\n"
            ."🆔 *Reference:* {$this->reference_no}"
            .($receivedDate ? "\n\n✅ *Received Date:* {$receivedDate}" : '');
    }
}
