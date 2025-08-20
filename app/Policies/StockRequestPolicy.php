<?php

namespace App\Policies;

use App\Models\StockRequest;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class StockRequestPolicy
{
    // Admins bypass all checks
    public function before(User $user, $ability)
    {
        if ($user->hasRole('admin')) {
            return true;
        }
    }

    public function viewAny(User $user): bool
    {
        return $user->can('stockRequest.view');
    }

    public function view(User $user, StockRequest $stockRequest): bool
    {
        return $user->can('stockRequest.view') &&
            // $user->defaultWarehouse()?->id === $stockRequest->warehouse_id &&
            $user->hasWarehouseAccess($stockRequest->warehouse_id) ||
            $user->can('stockRequest.review') ||
            $user->can('stockRequest.check') ||
            $user->can('stockRequest.approve');
    }

    public function create(User $user): bool
    {
        return $user->can('stockRequest.create');
    }

    public function update(User $user, StockRequest $stockRequest): bool
    {
        return $user->can('stockRequest.update') &&
            // $user->defaultWarehouse()?->id === $stockRequest->warehouse_id &&
            $user->hasWarehouseAccess($stockRequest->warehouse_id) &&
            $stockRequest->approval_status === 'Pending' &&
            $stockRequest->created_by === $user->id;
    }

    public function delete(User $user, StockRequest $stockRequest): bool
    {
        return $user->can('stockRequest.delete') &&
            // $user->defaultWarehouse()?->id === $stockRequest->warehouse_id &&
            $user->hasWarehouseAccess($stockRequest->warehouse_id) &&
            $stockRequest->approval_status === 'Pending' &&
            $stockRequest->created_by === $user->id;
    }

    public function restore(User $user, StockRequest $stockRequest): bool
    {
        return $user->can('stockRequest.restore') &&
            // $user->defaultWarehouse()?->id === $stockRequest->warehouse_id &&
            $user->hasWarehouseAccess($stockRequest->warehouse_id);
    }

    public function forceDelete(User $user, StockRequest $stockRequest): bool
    {
        return $user->can('stockRequest.forceDelete') &&
            // $user->defaultWarehouse()?->id === $stockRequest->warehouse_id &&
            $user->hasWarehouseAccess($stockRequest->warehouse_id);
    }

    public function review(User $user, StockRequest $stockRequest): bool
    {
        return $user->can('stockRequest.review');
    }

    public function check(User $user, StockRequest $stockRequest): bool
    {
        return $user->can('stockRequest.check');
    }

    public function approve(User $user, StockRequest $stockRequest): bool
    {
        return $user->can('stockRequest.approve');
    }

    public function reassign(User $user, StockRequest $stockRequest): bool
    {
        return $user->can('stockRequest.reassign');
    }
}
