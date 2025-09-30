<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class DocumentTransfer extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'document_transfers';

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

    // Relationships
    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater()
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function deleter()
    {
        return $this->belongsTo(User::class, 'deleted_by');
    }

    public function receivers()
    {
        return $this->hasMany(DocumentTransferResponse::class, 'documents_id');
    }

    /**
     * Mark a receiver as received
     */
    public function markAsReceived(int $receiverId, int $telegramUserId): array
    {
        $receiver = $this->receivers()->with('receiver')->firstWhere('receiver_id', $receiverId);

        if (!$receiver || !$receiver->receiver) {
            return ['success' => false, 'message' => 'You are not authorized to receive this document'];
        }

        // Validate Telegram ID
        if ($receiver->receiver->telegram_id != $telegramUserId) {
            return ['success' => false, 'message' => 'This Telegram account is not authorized'];
        }

        // Prevent double receiving
        if ($receiver->status !== 'Received') {
            $receiver->update([
                'status' => 'Received',
                'received_date' => now(),
            ]);
        }

        // Update document status if last receiver
        $allReceivers = $this->receivers()->orderBy('id')->get();
        if ($receiver->id === $allReceivers->last()->id) {
            $this->update(['status' => 'Completed']);
        }

        return [
            'success' => true,
            'receiver' => $receiver,
            'document' => $this,
        ];
    }

    /**
     * Generate Telegram message text including received date
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
