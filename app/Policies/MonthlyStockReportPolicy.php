<?php

namespace App\Policies;

use App\Models\MonthlyStockReport;
use App\Models\User;
use Illuminate\Auth\Access\Response;
use Illuminate\Auth\Access\HandlesAuthorization;

class MonthlyStockReportPolicy
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
        return $user->can('monthlyStockReport.view');
    }

    public function view(User $user, MonthlyStockReport $monthlyStockReport): bool
    {
        return $user->can('monthlyStockReport.view');
    }

    public function create(User $user): bool
    {
        return $user->can('monthlyStockReport.create');
    }

    public function update(User $user, MonthlyStockReport $monthlyStockReport): bool
    {
        return $user->can('monthlyStockReport.update') &&
            $monthlyStockReport->created_by === $user->id;
    }

    public function delete(User $user, MonthlyStockReport $monthlyStockReport): bool
    {
        return $user->can('monthlyStockReport.delete') &&
            $monthlyStockReport->created_by === $user->id;
    }

    public function restore(User $user, MonthlyStockReport $monthlyStockReport): bool
    {
        return $user->can('monthlyStockReport.restore');
    }

    public function forceDelete(User $user, MonthlyStockReport $monthlyStockReport): bool
    {
        return $user->can('monthlyStockReport.forceDelete');
    }

    public function initial(User $user, MonthlyStockReport $monthlyStockReport): bool
    {
        return $user->can('monthlyStockReport.initial');
    }

    public function verify(User $user, MonthlyStockReport $monthlyStockReport): bool
    {
        return $user->can('monthlyStockReport.verify');
    }

    public function check(User $user, MonthlyStockReport $monthlyStockReport): bool
    {
        return $user->can('monthlyStockReport.check');
    }

    public function acknowledge(User $user, MonthlyStockReport $monthlyStockReport): bool
    {
        return $user->can('monthlyStockReport.acknowledge');
    }

    public function reassign(User $user, MonthlyStockReport $monthlyStockReport): bool
    {
        return $user->can('monthlyStockReport.reassign');
    }
}
