<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DebitNote extends Model
{
    use HasFactory;

    protected $fillable = [
        'reference_number',
        'send_date',
        'debit_note_email_id',
        'warehouse_id',
        'campus_id',
        'department_id',
        'start_date',
        'end_date',
        'status',
        'created_by',
    ];

    public function debitNoteEmail()
    {
        return $this->belongsTo(DebitNoteEmail::class, 'debit_note_email_id');
    }

    public function warehouse()
    {
        return $this->belongsTo(Warehouse::class, 'warehouse_id');
    }
    public function department()
    {
        return $this->belongsTo(Department::class, 'department_id');
    }
    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function items()
    {
        return $this->hasMany(DebitNoteItems::class, 'debit_note_id');
    }

    public function campus()
    {
        return $this->belongsTo(Campus::class, 'campus_id');
    }
}
