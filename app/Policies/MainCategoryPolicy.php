<?php

namespace App\Policies;

use App\Models\MainCategory;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class MainCategoryPolicy
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
        return $user->can('mainCategory.view');
    }

    public function view(User $user, MainCategory $mainCategory): bool
    {
        return $user->can('mainCategory.view');
    }

    public function create(User $user): bool
    {
        return $user->can('mainCategory.create');
    }

    public function update(User $user, MainCategory $mainCategory): bool
    {
        return $user->can('mainCategory.update');
    }

    public function delete(User $user, MainCategory $mainCategory): bool
    {
        return $user->can('mainCategory.delete');
    }
}
