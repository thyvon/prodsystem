<?php

namespace App\Policies;

use App\Models\VariantAttribute;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class ProductVariantAttributePolicy
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
        return $user->can('productVariantAttribute.view');
    }

    public function view(User $user, VariantAttribute $productVariantAttribute): bool
    {
        return $user->can('productVariantAttribute.view');
    }

    public function create(User $user): bool
    {
        return $user->can('productVariantAttribute.create');
    }

    public function update(User $user, VariantAttribute $productVariantAttribute): bool
    {
        return $user->can('productVariantAttribute.update');
    }

    public function delete(User $user, VariantAttribute $productVariantAttribute): bool
    {
        return $user->can('productVariantAttribute.delete');
    }
}
