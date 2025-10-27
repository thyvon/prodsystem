<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Budget extends Model
{
    use HasFactory;
    use SoftDeletes;
    protected $table = 'budgets';
    protected $fillable = [
        'name',
        'description',
        'total_amount',
        'start_date',
        'end_date',
    ];

    public function budgetItems()
    {
        return $this->hasMany(BudgetItem::class);
    }
}
