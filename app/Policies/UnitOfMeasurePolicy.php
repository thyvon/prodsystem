<?php

namespace App\Policies;

use App\Models\UnitOfMeasure;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class UnitOfMeasurePolicy
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
        return $user->can('unitOfMeasure.view');
    }

    public function view(User $user, UnitOfMeasure $unitOfMeasure): bool
    {
        return $user->can('unitOfMeasure.view');
    }

    public function create(User $user): bool
    {
        return $user->can('unitOfMeasure.create');
    }

    public function update(User $user, UnitOfMeasure $unitOfMeasure): bool
    {
        return $user->can('unitOfMeasure.update');
    }

    public function delete(User $user, UnitOfMeasure $unitOfMeasure): bool
    {
        return $user->can('unitOfMeasure.delete');
    }
}
