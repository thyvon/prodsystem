<?php

namespace App\Policies;

use App\Models\MainStockBeginning;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class MainStockBeginningPolicy
{
    // Admins bypass all checks
    public function before(User $user, $ability)
    {
        if ($user->hasRole('admin')) {
            return true;
        }
    }

    public function viewAny(User $user): bool
    {
        return $user->can('mainStockBeginning.view');
    }

    public function view(User $user, MainStockBeginning $mainStockBeginning): bool
    {
        return $user->can('mainStockBeginning.view') &&
               $user->hasWarehouseAccess($mainStockBeginning->warehouse_id);
    }

    public function create(User $user): bool
    {
        return $user->can('mainStockBeginning.create');
    }

    public function update(User $user, MainStockBeginning $mainStockBeginning): bool
    {
        return $user->can('mainStockBeginning.update') &&
            $user->defaultWarehouse()?->id === $mainStockBeginning->warehouse_id &&
            $user->hasWarehouseAccess($mainStockBeginning->warehouse_id);
    }

    public function delete(User $user, MainStockBeginning $mainStockBeginning): bool
    {
        return $user->can('mainStockBeginning.update') &&
            $user->defaultWarehouse()?->id === $mainStockBeginning->warehouse_id &&
            $user->hasWarehouseAccess($mainStockBeginning->warehouse_id);
    }

    public function restore(User $user, MainStockBeginning $mainStockBeginning): bool
    {
        return $user->can('mainStockBeginning.update') &&
            $user->defaultWarehouse()?->id === $mainStockBeginning->warehouse_id &&
            $user->hasWarehouseAccess($mainStockBeginning->warehouse_id);
    }

    public function forceDelete(User $user, MainStockBeginning $mainStockBeginning): bool
    {
        return $user->can('mainStockBeginning.update') &&
            $user->defaultWarehouse()?->id === $mainStockBeginning->warehouse_id &&
            $user->hasWarehouseAccess($mainStockBeginning->warehouse_id);
    }

    public function review(User $user, MainStockBeginning $mainStockBeginning): bool
    {
        return $user->can('mainStockBeginning.review');
    }

    public function check(User $user, MainStockBeginning $mainStockBeginning): bool
    {
        return $user->can('mainStockBeginning.check');
    }

    public function approve(User $user, MainStockBeginning $mainStockBeginning): bool
    {
        return $user->can('mainStockBeginning.approve');
    }

    public function reassign(User $user, MainStockBeginning $mainStockBeginning): bool
    {
        return $user->can('mainStockBeginning.reassign');
    }
}
