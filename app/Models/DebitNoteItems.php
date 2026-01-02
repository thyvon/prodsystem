<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DebitNoteItems extends Model
{
    use HasFactory;
    protected $fillable = [
        'debit_note_id',
        'stock_issue_id',
        'stock_issue_item_id',
        'transaction_date',
        'item_code',
        'description',
        'quantity',
        'uom',
        'unit_price',
        'total_price',
        'requester_name',
        'campus_name',
        'division_name',
        'department_name',
        'reference_no',
        'remarks',
    ];

    public function debitNote()
    {
        return $this->belongsTo(DebitNote::class, 'debit_note_id');
    }

    public function stockIssue()
    {
        return $this->belongsTo(StockIssue::class, 'stock_issue_id');
    }

    public function stockIssueItem()
    {
        return $this->belongsTo(StockIssueItem::class, 'stock_issue_item_id');
    }
}
