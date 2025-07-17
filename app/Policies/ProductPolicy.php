<?php

namespace App\Policies;

use App\Models\Product;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class ProductPolicy
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
        return $user->can('product.view');
    }

    public function create(User $user): bool
    {
        return $user->can('product.create');
    }

    public function view(User $user, Product $product): bool
    {
        return $user->can('product.view');
    }

    public function edit(User $user): bool
    {
        return $user->can('product.update');
    }

    public function update(User $user, Product $product): bool
    {
        return $user->can('product.update');
    }

    public function delete(User $user, Product $product): bool
    {
        return $user->can('product.delete');
    }

    public function restore(User $user, Product $product): bool
    {
        return $user->can('product.restore');
    }
    public function forceDelete(User $user, Product $product): bool
    {
        return $user->can('product.force_delete');
    }
}

