<?php

namespace App\Policies;

use App\Models\StockRequest;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class StockRequestPolicy
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
        return $user->can('stockRequest.view');
    }

    public function create(User $user): bool
    {
        return $user->can('stockRequest.create');
    }

    public function view(User $user, StockRequest $stockRequest): bool
    {
        return $user->can('stockRequest.view');
    }

    public function edit(User $user): bool
    {
        return $user->can('stockRequest.update');
    }

    public function update(User $user, StockRequest $stockRequest): bool
    {
        return $user->can('stockRequest.update');
    }

    public function delete(User $user, StockRequest $stockRequest): bool
    {
        return $user->can('stockRequest.delete');
    }

    public function restore(User $user, StockRequest $stockRequest): bool
    {
        return $user->can('stockRequest.restore');
    }
    public function forceDelete(User $user, StockRequest $stockRequest): bool
    {
        return $user->can('stockRequest.force_delete');
    }
}
