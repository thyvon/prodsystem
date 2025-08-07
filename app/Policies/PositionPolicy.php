<?php

namespace App\Policies;

use App\Models\Position;
use App\Models\User;

class PositionPolicy
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
        return $user->can('position.view');
    }

    public function view(User $user, Position $position): bool
    {
        return $user->can('position.view');
    }

    public function create(User $user): bool
    {
        return $user->can('position.create');
    }

    public function update(User $user, Position $position): bool
    {
        return $user->can('position.update');
    }

    public function delete(User $user, Position $position): bool
    {
        return $user->can('position.delete');
    }
}
