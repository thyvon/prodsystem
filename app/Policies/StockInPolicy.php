<?php

namespace App\Policies;

use App\Models\User;
use App\Models\StockIn;

class StockInPolicy
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
        return $user->can('stockIn.view');
    }

    public function view(User $user, StockIn $stockIn): bool
    {
        return $user->can('stockIn.view') &&
            $user->hasWarehouseAccess($stockIn->warehouse_id) &&
            $user->defaultWarehouse()?->id === $stockIn->warehouse_id;
    }

    public function create(User $user): bool
    {
        return $user->can('stockIn.create');
    }

    public function update(User $user, StockIn $stockIn): bool
    {
        return $user->can('stockIn.update') &&
            $user->hasWarehouseAccess($stockIn->warehouse_id) &&
            $user->defaultWarehouse()?->id === $stockIn->warehouse_id &&
            $stockIn->created_by === $user->id;
    }

    public function delete(User $user, StockIn $stockIn): bool
    {
        return $user->can('stockIn.delete') &&
            $user->hasWarehouseAccess($stockIn->warehouse_id) &&
            $user->defaultWarehouse()?->id === $stockIn->warehouse_id &&
            $stockIn->created_by === $user->id;
    }

    public function restore(User $user, StockIn $stockIn): bool
    {
        return $user->can('stockIn.restore') &&
            $user->hasWarehouseAccess($stockIn->warehouse_id) &&
            $user->defaultWarehouse()?->id === $stockIn->warehouse_id &&
            $stockIn->created_by === $user->id;
    }

    public function forceDelete(User $user, StockIn $stockIn): bool
    {
        return $user->can('stockIn.forceDelete') &&
            $user->hasWarehouseAccess($stockIn->warehouse_id) &&
            $user->defaultWarehouse()?->id === $stockIn->warehouse_id &&
            $stockIn->created_by === $user->id;
    }
}
