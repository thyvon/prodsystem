<?php

namespace App\Policies;

use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Auth\Access\Response;

class WarehousePolicy
{
    /**
     * Grant all abilities to admin users before other checks.
     */
    public function before(User $user, $ability)
    {
        if ($user->hasRole('admin')) {
            return true; // Admin bypasses all policy checks
        }
    }

    public function viewAny(User $user): bool
    {
        return $user->can('warehouse.view');
    }

    public function create(User $user): bool
    {
        return $user->can('warehouse.create');
    }

    public function view(User $user, Warehouse $warehouse): bool
    {
        return $user->can('warehouse.view');
    }

    public function edit(User $user): bool
    {
        return $user->can('warehouse.update');
    }

    public function update(User $user, Warehouse $warehouse): bool
    {
        return $user->can('warehouse.update');
    }

    public function delete(User $user, Warehouse $warehouse): bool
    {
        return $user->can('warehouse.delete');
    }

    public function restore(User $user, Warehouse $warehouse): bool
    {
        return $user->can('warehouse.restore');
    }
    public function forceDelete(User $user, Warehouse $warehouse): bool
    {
        return $user->can('warehouse.force_delete');
    }
}
