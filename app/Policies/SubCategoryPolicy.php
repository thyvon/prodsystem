<?php

namespace App\Policies;

use App\Models\SubCategory;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class SubCategoryPolicy
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
        return $user->can('subCategory.view');
    }

    public function view(User $user, SubCategory $subCategory): bool
    {
        return $user->can('subCategory.view');
    }

    public function create(User $user): bool
    {
        return $user->can('subCategory.create');
    }

    public function update(User $user, SubCategory $subCategory): bool
    {
        return $user->can('subCategory.update');
    }

    public function delete(User $user, SubCategory $subCategory): bool
    {
        return $user->can('subCategory.delete');
    }
}
