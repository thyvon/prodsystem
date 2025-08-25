<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class StockRequest extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $table = 'stock_requests';

    protected $fillable = [
        'request_date',
        'request_number',
        'campus_id',
        'warehouse_id',
        'type',
        'purpose',
        'approval_status',
        'created_by',
        'position_id',
        'updated_by',
        'deleted_by',
    ];

    public function campus()
    {
        return $this->belongsTo(Campus::class , 'campus_id');
    }
    public function warehouse()
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function stockRequestItems()
    {
        return $this->hasMany(StockRequestItem::class);
    }
    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
    public function updatedBy()
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
    public function creatorPosition()
    {
        return $this->belongsTo(Position::class, 'position_id');
    }
    public function deletedBy()
    {
        return $this->belongsTo(User::class, 'deleted_by');
    }

    public function approvals()
    {
        return $this->morphMany(Approval::class, 'approvable')->orderBy('ordinal');
    }

    public function scopeSearch($query, ?string $search)
    {
        if (!$search) return $query;

        return $query->where(function ($q) use ($search) {
            $q->where('request_number', 'like', "%{$search}%")
            ->orWhereHas('warehouse', fn($q2) => $q2->where('name', 'like', "%{$search}%"))
            ->orWhereHas('stockRequestItems.productVariant.product', fn($q3) => $q3->where(function ($q4) use ($search) {
                $q4->where('name', 'like', "%{$search}%")
                    ->orWhere('khmer_name', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%")
                    ->orWhere('item_code', 'like', "%{$search}%")
                    ->orWhereHas('unit', fn($q5) => $q5->where('name', 'like', "%{$search}%"));
            }));
        });
    }

    public function scopeCampusFilter($query, bool $isAdmin, array $campusIds)
    {
        return $isAdmin ? $query : $query->whereIn('campus_id', $campusIds);
    }
}