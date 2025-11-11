<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DocumentRelation extends Model
{
    use HasFactory;

    protected $table = 'document_relations';
    // Allow mass assignment for these fields
    protected $fillable = [
        'documentable_type',
        'documentable_id',
        'document_name',
        'document_reference',
        // 'sharepoint_file_id',
        'file_name',
        'path',
        // 'sharepoint_drive_id',
    ];

    /**
     * Polymorphic relationship
     */
    public function documentable()
    {
        return $this->morphTo();
    }
}
