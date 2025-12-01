<?php

namespace App\Policies;

use App\Models\StockCount;
use App\Models\User;
use Illuminate\Auth\Access\Response;
use Illuminate\Auth\Access\HandlesAuthorization;

class StockCountPolicy
{
    use HandlesAuthorization;

    /**
     * Admins bypass all checks.
     */
    public function before(User $user, $ability)
    {
        if ($user->hasRole('admin')) {
            return true;
        }
    }

    public function viewAny(User $user): bool
    {
        return $user->can('stockCount.view');
    }

    public function view(User $user,StockCount $stockCount): bool
    {
        return $user->can('stockCount.view');
    }

    public function create(User $user): bool
    {
        return $user->can('stockCount.create');
    }

    public function update(User $user,StockCount $stockCount): bool
    {
        return $user->can('stockCount.update') &&
            $stockCount->created_by === $user->id;
    }

    public function delete(User $user,StockCount $stockCount): bool
    {
        return $user->can('stockCount.delete') &&
            $stockCount->created_by === $user->id;
    }

    public function restore(User $user,StockCount $stockCount): bool
    {
        return $user->can('stockCount.restore');
    }

    public function forceDelete(User $user,StockCount $stockCount): bool
    {
        return $user->can('stockCount.forceDelete');
    }

    public function initial(User $user,StockCount $stockCount): bool
    {
        return $user->can('stockCount.initial');
    }

    public function approve(User $user,StockCount $stockCount): bool
    {
        return $user->can('stockCount.approve');
    }

    public function reassign(User $user,StockCount $stockCount): bool
    {
        return $user->can('stockCount.reassign');
    }
}
