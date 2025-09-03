<?php

namespace App\Policies;

use App\Models\StockRequest;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Fancate\Support\Log;

class StockRequestPolicy
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
        return $user->can('stockRequest.view');
    }

    public function view(User $user, StockRequest $stockRequest): bool
    {
        return $user->can('stockRequest.view') &&
            (
                $stockRequest->created_by === $user->id ||
                $user->defaultWarehouse()?->id === $stockRequest->warehouse_id ||
                $user->hasWarehouseAccess($stockRequest->warehouse_id) ||
                $user->campus()->pluck('id')->contains($stockRequest->warehouse->building->campus_id)
            ) ||
            $user->can('stockRequest.review') ||
            $user->can('stockRequest.check') ||
            $user->can('stockRequest.approve');
    }

    public function create(User $user): bool
    {
        return $user->can('stockRequest.create');
    }

    public function update(User $user, StockRequest $stockRequest): bool
    {
        return $user->can('stockRequest.update') &&
            $user->hasWarehouseAccess($stockRequest->warehouse_id) &&
            $stockRequest->approval_status === 'Pending' &&
            $stockRequest->created_by === $user->id;
    }

    public function delete(User $user, StockRequest $stockRequest): bool
    {
        return $user->can('stockRequest.delete') &&
            $user->hasWarehouseAccess($stockRequest->warehouse_id) &&
            $stockRequest->approval_status === 'Pending' &&
            $stockRequest->created_by === $user->id;
    }

    public function restore(User $user, StockRequest $stockRequest): bool
    {
        return $user->can('stockRequest.restore') &&
            $user->hasWarehouseAccess($stockRequest->warehouse_id);
    }

    public function forceDelete(User $user, StockRequest $stockRequest): bool
    {
        return $user->can('stockRequest.forceDelete') &&
            $user->hasWarehouseAccess($stockRequest->warehouse_id);
    }

    public function review(User $user, StockRequest $stockRequest): bool
    {
        return $user->can('stockRequest.review');
    }

    public function check(User $user, StockRequest $stockRequest): bool
    {
        return $user->can('stockRequest.check');
    }

    public function approve(User $user, StockRequest $stockRequest): bool
    {
        return $user->can('stockRequest.approve');
    }

    public function reassign(User $user, StockRequest $stockRequest): bool
    {
        return $user->can('stockRequest.reassign');
    }

    /**
     * 🔎 Scope: Filter stock requests based on user permissions
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

        // Own requests
        if ($user->can('stockRequest.viewOwnRecord')) {
            $q->orWhere('created_by', $userId);
            $hasAny = true;
        }

        // Default warehouse
        if ($user->can('stockRequest.viewByDefaultWarehouse') && $defaultWarehouseId) {
            $q->orWhere('warehouse_id', $defaultWarehouseId);
            $hasAny = true;
        }

        // User warehouse access
        if ($user->can('stockRequest.viewByWarehouseAccess') && !empty($userWarehouseIds)) {
            $q->orWhereIn('warehouse_id', $userWarehouseIds);
            $hasAny = true;
        }

        // User campus access
        if ($user->can('stockRequest.viewByCampusAccess') && !empty($userCampusIds)) {
            $q->orWhereHas('warehouse.building.campus', function ($cQ) use ($userCampusIds) {
                $cQ->whereIn('id', $userCampusIds);
            });
            $hasAny = true;
        }

        // Default campus of creator
        if ($user->can('stockRequest.viewByDefaultCampus')) {
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
        if ($user->can('stockRequest.viewAllRecord')) {
            $q->orWhereRaw('1=1');
            $hasAny = true;
        }

        // If no permissions, return no records
        if (!$hasAny) {
            $q->whereRaw('1=0');
        }
    });

    // Debug log
    \Log::info('StockRequest ScopeViewable Query: ' . $query->toSql());
    \Log::info('Bindings: ' . implode(', ', $query->getBindings()));

    return $query;
}

}
