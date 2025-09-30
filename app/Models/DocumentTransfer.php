<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class DocumentTransfer extends Model
{
    use HasFactory;
    use SoftDeletes;

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

}
