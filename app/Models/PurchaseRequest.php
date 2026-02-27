<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Support\Facades\DB;
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
        return $this->morphMany(Approval::class, 'approvable');
    }

    public function files()
    {
        return $this->morphMany(DocumentRelation::class, 'documentable');
    }

    public function restoreWithRelations(): void
    {
        if (!$this->trashed()) {
            throw new \LogicException('Purchase request is not deleted.');
        }

        DB::transaction(function () {

            $this->restore();

            $this->files()
                ->withTrashed()
                ->restore();

            $this->items()
                ->withTrashed()
                ->restore();

            $this->approvals()
                ->withTrashed()
                ->restore();
        });
    }

    public function forceDeleteWithRelations($fileServerService): void
    {
        if (!$this->trashed()) {
            throw new \LogicException('Purchase request must be deleted first.');
        }

        DB::transaction(function () use ($fileServerService) {

            // Permanently delete files
            foreach ($this->files()->withTrashed()->get() as $file) {
                if ($file->path) {
                    $fileServerService->deleteFile($file->path);
                }
                $file->forceDelete();
            }

            // Permanently delete items and detach pivot relations
            foreach ($this->items()->withTrashed()->get() as $item) {
                $item->campuses()->detach();
                $item->departments()->detach();
                $item->forceDelete();
            }

            // Permanently delete approvals
            $this->approvals()->withTrashed()->forceDelete();

            // Finally, delete main purchase request
            $this->forceDelete();
        });
    }

}
