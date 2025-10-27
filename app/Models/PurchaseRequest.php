<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class PurchaseRequest extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $table = 'purchase_requests';
    protected $fillable = [
        'reference_no',
        'request_date',
        'deadline_date',
        'purpose',
        'deadline',
        'approval_status',
        'is_urgent',
        'created_by',
        'position_id',
        'updated_by',
        'deleted_by',
    ];

    public function items()
    {
        return $this->hasMany(PurchaseRequestItem::class, 'purchase_request_id');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function position()
    {
        return $this->belongsTo(Position::class, 'position_id');
    }

    public function updater()
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function deleter()
    {
        return $this->belongsTo(User::class, 'deleted_by');
    }

    public function approvals()
    {
        return $this->morphMany(Approval::class, 'approvable')->orderBy('ordinal');
    }

    public function files()
    {
        return $this->morphMany(DocumentRelation::class, 'documentable');
    }

}
