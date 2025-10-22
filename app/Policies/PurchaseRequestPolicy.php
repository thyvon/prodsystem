<?php

namespace App\Policies;

use App\Models\PurchaseRequest;
use App\Models\User;
use Illuminate\Auth\Access\Response;
use Illuminate\Auth\Access\HandlesAuthorization;

class PurchaseRequestPolicy
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
        return $user->can('purchaseRequest.view');
    }

    public function view(User $user, PurchaseRequest $purchaseRequest): bool
    {
        return $user->can('purchaseRequest.view');
    }

    public function create(User $user): bool
    {
        return $user->can('purchaseRequest.create');
    }

    public function update(User $user, PurchaseRequest $purchaseRequest): bool
    {
        return $user->can('purchaseRequest.update') &&
            $purchaseRequest->created_by === $user->id;
    }

    public function delete(User $user, PurchaseRequest $purchaseRequest): bool
    {
        return $user->can('purchaseRequest.delete') &&
            $purchaseRequest->created_by === $user->id;
    }

    public function restore(User $user, PurchaseRequest $purchaseRequest): bool
    {
        return $user->can('purchaseRequest.restore');
    }

    public function forceDelete(User $user, PurchaseRequest $purchaseRequest): bool
    {
        return $user->can('purchaseRequest.forceDelete');
    }

    public function review(User $user, PurchaseRequest $purchaseRequest): bool
    {
        return $user->can('purchaseRequest.review');
    }

    public function check(User $user, PurchaseRequest $purchaseRequest): bool
    {
        return $user->can('purchaseRequest.check');
    }

    public function approve(User $user, PurchaseRequest $purchaseRequest): bool
    {
        return $user->can('purchaseRequest.approve');
    }

    public function initial(User $user, PurchaseRequest $purchaseRequest): bool
    {
        return $user->can('purchaseRequest.initial');
    }

    public function verify(User $user, PurchaseRequest $purchaseRequest): bool
    {
        return $user->can('purchaseRequest.verify');
    }

    public function acknowledge(User $user, PurchaseRequest $purchaseRequest): bool
    {
        return $user->can('purchaseRequest.acknowledge');
    }

    public function reassign(User $user, PurchaseRequest $purchaseRequest): bool
    {
        return $user->can('purchaseRequest.reassign');
    }

    /**
     * 🔎 Scope: Filter stock transfers based on user permissions
     */
    // public function scopeViewable($query, User $user)
    // {
    //     // Admin bypass
    //     if ($user->hasRole('admin')) {
    //         return $query;
    //     }

    //     $defaultWarehouseId = $user->defaultWarehouse()?->id;
    //     $defaultCampusId    = $user->defaultCampus()?->id;
    //     $userWarehouseIds   = $user->warehouses->pluck('id')->toArray();
    //     $userCampusIds      = $user->campus->pluck('id')->toArray();
    //     $userId             = $user->id;

    //     $query->where(function ($q) use ($userId, $defaultWarehouseId, $userWarehouseIds, $userCampusIds, $defaultCampusId, $user) {
    //         $hasAny = false;

    //         // Own transfers
    //         if ($user->can('purchaseRequest.viewOwnRecord')) {
    //             $q->orWhere('created_by', $userId);
    //             $hasAny = true;
    //         }

    //         // Default warehouse
    //         if ($user->can('purchaseRequest.viewByDefaultWarehouse') && $defaultWarehouseId) {
    //             $q->orWhere('warehouse_id', $defaultWarehouseId);
    //             $hasAny = true;
    //         }

    //         // User warehouse access
    //         if ($user->can('purchaseRequest.viewByWarehouseAccess') && !empty($userWarehouseIds)) {
    //             $q->orWhereIn('warehouse_id', $userWarehouseIds);
    //             $hasAny = true;
    //         }

    //         // User campus access
    //         if ($user->can('purchaseRequest.viewByCampusAccess') && !empty($userCampusIds)) {
    //             $q->orWhereHas('warehouse.building.campus', function ($cQ) use ($userCampusIds) {
    //                 $cQ->whereIn('id', $userCampusIds);
    //             });
    //             $hasAny = true;
    //         }

    //         // Default campus of creator
    //         if ($user->can('purchaseRequest.viewByDefaultCampus')) {
    //             $defaultCampusId = $user->defaultCampus()?->id;

    //             if ($defaultCampusId) {
    //                 $q->orWhereHas('createdBy.campus', function ($cq) use ($defaultCampusId) {
    //                     $cq->where('campus_user.is_default', true)
    //                        ->where('campus.id', $defaultCampusId);
    //                 });
    //                 $hasAny = true;
    //             }
    //         }

    //         // View all
    //         if ($user->can('purchaseRequest.viewAllRecord')) {
    //             $q->orWhereRaw('1=1');
    //             $hasAny = true;
    //         }

    //         // If no permissions, return no records
    //         if (!$hasAny) {
    //             $q->whereRaw('1=0');
    //         }
    //     });

    //     // Debug log
    //     Log::info('DigitalDocsApprovalScopeViewable Query: ' . $query->toSql());
    //     Log::info('Bindings: ' . implode(', ', $query->getBindings()));

    //     return $query;
    // }
}
