<?php

namespace App\Policies;

use App\Models\User;
use App\Models\DigitalDocsApproval;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Support\Facades\Log;

class DigitalDocsAprovalPolicy
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
        return $user->can('digitalDocsApproval.view');
    }

    public function view(User $user, DigitalDocsApproval$digitalDocsApproval): bool
    {
        return $user->can('digitalDocsApproval.view');
    }

    public function create(User $user): bool
    {
        return $user->can('digitalDocsApproval.create');
    }

    public function update(User $user, DigitalDocsApproval$digitalDocsApproval): bool
    {
        return $user->can('digitalDocsApproval.update') &&
            $digitalDocsApproval->created_by === $user->id;
    }

    public function delete(User $user, DigitalDocsApproval$digitalDocsApproval): bool
    {
        return $user->can('digitalDocsApproval.delete') &&
            $digitalDocsApproval->created_by === $user->id;
    }

    public function restore(User $user, DigitalDocsApproval$digitalDocsApproval): bool
    {
        return $user->can('digitalDocsApproval.restore');
    }

    public function forceDelete(User $user, DigitalDocsApproval$digitalDocsApproval): bool
    {
        return $user->can('digitalDocsApproval.forceDelete');
    }

    public function review(User $user, DigitalDocsApproval$digitalDocsApproval): bool
    {
        return $user->can('digitalDocsApproval.review');
    }

    public function check(User $user, DigitalDocsApproval$digitalDocsApproval): bool
    {
        return $user->can('digitalDocsApproval.check');
    }

    public function approve(User $user, DigitalDocsApproval$digitalDocsApproval): bool
    {
        return $user->can('digitalDocsApproval.approve');
    }

    public function reassign(User $user, DigitalDocsApproval$digitalDocsApproval): bool
    {
        return $user->can('digitalDocsApproval.reassign');
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
    //         if ($user->can('digitalDocsApproval.viewOwnRecord')) {
    //             $q->orWhere('created_by', $userId);
    //             $hasAny = true;
    //         }

    //         // Default warehouse
    //         if ($user->can('digitalDocsApproval.viewByDefaultWarehouse') && $defaultWarehouseId) {
    //             $q->orWhere('warehouse_id', $defaultWarehouseId);
    //             $hasAny = true;
    //         }

    //         // User warehouse access
    //         if ($user->can('digitalDocsApproval.viewByWarehouseAccess') && !empty($userWarehouseIds)) {
    //             $q->orWhereIn('warehouse_id', $userWarehouseIds);
    //             $hasAny = true;
    //         }

    //         // User campus access
    //         if ($user->can('digitalDocsApproval.viewByCampusAccess') && !empty($userCampusIds)) {
    //             $q->orWhereHas('warehouse.building.campus', function ($cQ) use ($userCampusIds) {
    //                 $cQ->whereIn('id', $userCampusIds);
    //             });
    //             $hasAny = true;
    //         }

    //         // Default campus of creator
    //         if ($user->can('digitalDocsApproval.viewByDefaultCampus')) {
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
    //         if ($user->can('digitalDocsApproval.viewAllRecord')) {
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
