<?php

namespace App\Policies;

use App\Models\MainStockBeginning;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class MainStockBeginningPolicy
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
        return $user->can('mainStockBeginning.view');
    }

    public function create(User $user): bool
    {
        return $user->can('mainStockBeginning.create');
    }

    public function view(User $user, MainStockBeginning $mainStockBeginning): bool
    {
        return $user->can('mainStockBeginning.view');
    }

    public function edit(User $user): bool
    {
        return $user->can('mainStockBeginning.update');
    }

    public function update(User $user, MainStockBeginning $mainStockBeginning): bool
    {
        return $user->can('mainStockBeginning.update');
    }

    public function delete(User $user, MainStockBeginning $mainStockBeginning): bool
    {
        return $user->can('mainStockBeginning.delete');
    }

    public function restore(User $user, MainStockBeginning $mainStockBeginning): bool
    {
        return $user->can('mainStockBeginning.restore');
    }
    public function forceDelete(User $user, MainStockBeginning $mainStockBeginning): bool
    {
        return $user->can('mainStockBeginning.forceDelete');
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
