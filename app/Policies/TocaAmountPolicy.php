<?php

namespace App\Policies;

use App\Models\TocaAmount;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class TocaAmountPolicy
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

    public function view(User $user, TocaAmount $tocaAmount): bool
    {
        return $user->can('toca.view');
    }

    public function create(User $user): bool
    {
        return $user->can('toca.create');
    }

    public function update(User $user, TocaAmount $tocaAmount): bool
    {
        return $user->can('toca.update');
    }

    public function delete(User $user, TocaAmount $tocaAmount): bool
    {
        return $user->can('toca.delete');
    }
}
