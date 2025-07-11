<?php

namespace App\Policies;

use App\Models\Campus;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class CampusPolicy
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
        return $user->can('campus.view');
    }

    public function view(User $user, Campus $campus): bool
    {
        return $user->can('campus.view');
    }

    public function create(User $user): bool
    {
        return $user->can('campus.create');
    }

    public function update(User $user, Campus $campus): bool
    {
        return $user->can('campus.update');
    }

    public function delete(User $user, Campus $campus): bool
    {
        return $user->can('campus.delete');
    }
}