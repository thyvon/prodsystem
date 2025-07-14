<?php

namespace App\Policies;

use App\Models\Division;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class DivisionPolicy
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
        return $user->can('division.view');
    }

    public function view(User $user, Division $division): bool
    {
        return $user->can('division.view');
    }

    public function create(User $user): bool
    {
        return $user->can('division.create');
    }

    public function update(User $user, Division $division): bool
    {
        return $user->can('division.update');
    }

    public function delete(User $user, Division $division): bool
    {
        return $user->can('division.delete');
    }
}
