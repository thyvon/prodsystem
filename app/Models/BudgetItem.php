<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class BudgetItem extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $table = 'budget_items';
    protected $fillable = [
        'budget_id',
        'reference_no',
        'description',
        'amount_allocated',
        'amount_used',
        'amount_remaining',
    ];

    public function budget()
    {
        return $this->belongsTo(Budget::class);
    }
}
