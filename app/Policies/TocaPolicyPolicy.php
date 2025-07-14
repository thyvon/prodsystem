<?php

namespace App\Policies;

use App\Models\TocaPolicy;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class TocaPolicyPolicy
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
        return $user->can('toca.view');
    }

    public function view(User $user, TocaPolicy $tocaPolicy): bool
    {
        return $user->can('toca.view');
    }

    public function create(User $user): bool
    {
        return $user->can('toca.create');
    }

    public function update(User $user, TocaPolicy $tocaPolicy): bool
    {
        return $user->can('toca.update');
    }

    public function delete(User $user, TocaPolicy $tocaPolicy): bool
    {
        return $user->can('toca.delete');
    }
}
