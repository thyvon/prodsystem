<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class DocumentsReceiver extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $table = 'documents_receivers';
    protected $fillable = [
        'documents_id',
        'telegram_message_id',
        'telegram_creator_message_id',
        'document_reference',
        'document_name',
        'status',
        'requester_id',
        'receiver_id',
        'sent_date',
        'received_date',
        'owner_receive_status',
        'owner_received_date',
    ];

    public function document()
    {
        return $this->belongsTo(DocumentTransfer::class, 'documents_id');
    }

    public function requester()
    {
        return $this->belongsTo(User::class, 'requester_id');
    }

    public function receiver()
    {
        return $this->belongsTo(User::class, 'receiver_id');
    }
}
