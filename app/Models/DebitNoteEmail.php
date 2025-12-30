<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DebitNoteEmail extends Model
{
    use HasFactory;
    protected $table = 'debit_note_emails';
    protected $fillable = [
        'department_id',
        'warehouse_id',
        'send_to_email',
        'receiver_name',
        'cc_to_email',
    ];

    protected $casts = [
        'send_to_email' => 'array',
        'cc_to_email'   => 'array',
    ];

    public function department()
    {
        return $this->belongsTo(Department::class);
    }

    public function warehouse()
    {
        return $this->belongsTo(Warehouse::class);
    }
}
