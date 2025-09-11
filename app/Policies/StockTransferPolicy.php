<?php

namespace App\Policies;

use App\Models\StockTransfer;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Support\Facades\Log;

class StockTransferPolicy
{
    use HandlesAuthorization;

    /**
     * Admins bypass all checks.
     */
    public function before(User $user, $ability)
    {
        if ($user->hasRole('admin')) {
            return true;
        }
    }

    public function viewAny(User $user): bool
    {
        return $user->can('stockTransfer.view');
    }

    public function view(User $user, StockTransfer $stockTransfer): bool
    {
        return $user->can('stockTransfer.view') &&
            (
                $stockTransfer->created_by === $user->id ||
                $user->defaultWarehouse()?->id === $stockTransfer->warehouse_id ||
                $user->hasWarehouseAccess($stockTransfer->warehouse_id) ||
                $user->campus()->pluck('id')->contains($stockTransfer->warehouse->building->campus_id)
            ) ||
            $user->can('stockTransfer.review') ||
            $user->can('stockTransfer.check') ||
            $user->can('stockTransfer.approve');
    }

    public function create(User $user): bool
    {
        return $user->can('stockTransfer.create');
    }

    public function update(User $user, StockTransfer $stockTransfer): bool
    {
        return $user->can('stockTransfer.update') &&
            $user->hasWarehouseAccess($stockTransfer->warehouse_id) &&
            $stockTransfer->approval_status === 'Pending' &&
            $stockTransfer->created_by === $user->id;
    }

    public function delete(User $user, StockTransfer $stockTransfer): bool
    {
        return $user->can('stockTransfer.delete') &&
            $user->hasWarehouseAccess($stockTransfer->warehouse_id) &&
            $stockTransfer->approval_status === 'Pending' &&
            $stockTransfer->created_by === $user->id;
    }

    public function restore(User $user, StockTransfer $stockTransfer): bool
    {
        return $user->can('stockTransfer.restore') &&
            $user->hasWarehouseAccess($stockTransfer->warehouse_id);
    }

    public function forceDelete(User $user, StockTransfer $stockTransfer): bool
    {
        return $user->can('stockTransfer.forceDelete') &&
            $user->hasWarehouseAccess($stockTransfer->warehouse_id);
    }

    public function review(User $user, StockTransfer $stockTransfer): bool
    {
        return $user->can('stockTransfer.review');
    }

    public function check(User $user, StockTransfer $stockTransfer): bool
    {
        return $user->can('stockTransfer.check');
    }

    public function approve(User $user, StockTransfer $stockTransfer): bool
    {
        return $user->can('stockTransfer.approve');
    }

    public function reassign(User $user, StockTransfer $stockTransfer): bool
    {
        return $user->can('stockTransfer.reassign');
    }

    /**
     * 🔎 Scope: Filter stock transfers based on user permissions
     */
    public function scopeViewable($query, User $user)
    {
        // Admin bypass
        if ($user->hasRole('admin')) {
            return $query;
        }

        $defaultWarehouseId = $user->defaultWarehouse()?->id;
        $defaultCampusId    = $user->defaultCampus()?->id;
        $userWarehouseIds   = $user->warehouses->pluck('id')->toArray();
        $userCampusIds      = $user->campus->pluck('id')->toArray();
        $userId             = $user->id;

        $query->where(function ($q) use ($userId, $defaultWarehouseId, $userWarehouseIds, $userCampusIds, $defaultCampusId, $user) {
            $hasAny = false;

            // Own transfers
            if ($user->can('stockTransfer.viewOwnRecord')) {
                $q->orWhere('created_by', $userId);
                $hasAny = true;
            }

            // Default warehouse
            if ($user->can('stockTransfer.viewByDefaultWarehouse') && $defaultWarehouseId) {
                $q->orWhere('warehouse_id', $defaultWarehouseId);
                $hasAny = true;
            }

            // User warehouse access
            if ($user->can('stockTransfer.viewByWarehouseAccess') && !empty($userWarehouseIds)) {
                $q->orWhereIn('warehouse_id', $userWarehouseIds);
                $hasAny = true;
            }

            // User campus access
            if ($user->can('stockTransfer.viewByCampusAccess') && !empty($userCampusIds)) {
                $q->orWhereHas('warehouse.building.campus', function ($cQ) use ($userCampusIds) {
                    $cQ->whereIn('id', $userCampusIds);
                });
                $hasAny = true;
            }

            // Default campus of creator
            if ($user->can('stockTransfer.viewByDefaultCampus')) {
                $defaultCampusId = $user->defaultCampus()?->id;

                if ($defaultCampusId) {
                    $q->orWhereHas('createdBy.campus', function ($cq) use ($defaultCampusId) {
                        $cq->where('campus_user.is_default', true)
                           ->where('campus.id', $defaultCampusId);
                    });
                    $hasAny = true;
                }
            }

            // View all
            if ($user->can('stockTransfer.viewAllRecord')) {
                $q->orWhereRaw('1=1');
                $hasAny = true;
            }

            // If no permissions, return no records
            if (!$hasAny) {
                $q->whereRaw('1=0');
            }
        });

        // Debug log
        Log::info('StockTransfer ScopeViewable Query: ' . $query->toSql());
        Log::info('Bindings: ' . implode(', ', $query->getBindings()));

        return $query;
    }
}