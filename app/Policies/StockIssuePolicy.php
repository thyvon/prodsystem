<?php

namespace App\Policies;

use App\Models\User;
use App\Models\StockIssue;

class StockIssuePolicy
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
        return $user->can('stockIssue.view');
    }

    public function view(User $user, StockIssue $stockIssue): bool
    {
        return $user->can('stockIssue.view') &&
            $user->hasWarehouseAccess($stockIssue->stockRequest?->warehouse?->id);
    }

    public function create(User $user): bool
    {
        return $user->can('stockIssue.create');
    }

    public function update(User $user, StockIssue $stockIssue): bool
    {
        return $user->can('stockIssue.update') &&
            $user->hasWarehouseAccess($stockIssue->stockRequest?->warehouse?->id) &&
            $stockIssue->created_by === $user->id;
    }

    public function delete(User $user, StockIssue $stockIssue): bool
    {
        return $user->can('stockIssue.delete') &&
            $user->hasWarehouseAccess($stockIssue->stockRequest?->warehouse?->id) &&
            $stockIssue->created_by === $user->id;
    }

    public function restore(User $user, StockIssue $stockIssue): bool
    {
        return $user->can('stockIssue.restore') &&
            $user->hasWarehouseAccess($stockIssue->stockRequest?->warehouse?->id);
    }

    public function forceDelete(User $user, StockIssue $stockIssue): bool
    {
        return $user->can('stockIssue.forceDelete') &&
            $user->hasWarehouseAccess($stockIssue->stockRequest?->warehouse?->id);
    }
}
