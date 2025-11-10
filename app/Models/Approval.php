<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Approval extends Model
{
    use HasFactory;

    protected $table = 'approvals';
    protected $fillable = [
        'approvable_type',
        'approvable_id',
        'document_name',
        'document_reference',
        'request_type',
        'approval_status',
        'comment',
        'ordinal',
        'requester_id',
        'responder_id',
        'position_id',
        'responded_date',
        'is_seen'
    ];

    public function approvable()
    {
        return $this->morphTo();
    }

    public function requester()
    {
        return $this->belongsTo(User::class, 'requester_id');
    }

    public function responder()
    {
        return $this->belongsTo(User::class, 'responder_id');
    }

    public function responderPosition()
    {
        return $this->belongsTo(Position::class, 'position_id');
    }
}
