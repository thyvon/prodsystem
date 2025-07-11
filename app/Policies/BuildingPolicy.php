<?php

namespace App\Policies;

use App\Models\Building;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class BuildingPolicy
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
        return $user->can('building.view');
    }

    public function view(User $user, Building $building): bool
    {
        return $user->can('building.view');
    }

    public function create(User $user): bool
    {
        return $user->can('building.create');
    }

    public function update(User $user, Building $building): bool
    {
        return $user->can('building.update');
    }

    public function delete(User $user, Building $building): bool
    {
        return $user->can('building.delete');
    }
}