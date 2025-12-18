<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class DigitalDocsApproval extends Model
{
    use HasFactory;
    use SoftDeletes;
    protected $table = 'digital_docs_approvals';
    protected $fillable = [
        'reference_no',
        'description',
        'sharepoint_file_id',
        'sharepoint_file_name',
        'sharepoint_file_url',
        'sharepoint_file_ui_url',
        'sharepoint_drive_id',
        'document_type',
        'approval_status',
        'created_by',
        'position_id',
        'updated_by',
        'deleted_by',
    ];

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
    public function creatorPosition()
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
        return $this->morphMany(Approval::class, 'approvable');
    }
}
