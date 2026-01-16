<?php

namespace App\Policies;

use App\Models\PurchaseRequest;
use App\Models\User;
use Illuminate\Auth\Access\Response;
use Illuminate\Auth\Access\HandlesAuthorization;

class PurchaseRequestPolicy
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
        return $user->can('purchaseRequest.view');
    }

    public function view(User $user, PurchaseRequest $purchaseRequest): bool
    {
        return $user->can('purchaseRequest.view');
    }

    public function create(User $user): bool
    {
        return $user->can('purchaseRequest.create');
    }

        public function assignPurchaser(User $user): bool
    {
        return $user->can('purchaseRequest.assignPurchaser');
    }

    public function update(User $user, PurchaseRequest $purchaseRequest): bool
    {
        return $user->can('purchaseRequest.update') &&
            $purchaseRequest->created_by === $user->id;
    }

    public function delete(User $user, PurchaseRequest $purchaseRequest): bool
    {
        return $user->can('purchaseRequest.delete') &&
            $purchaseRequest->created_by === $user->id;
    }

    public function restore(User $user, PurchaseRequest $purchaseRequest): bool
    {
        return $user->can('purchaseRequest.restore');
    }

    public function forceDelete(User $user, PurchaseRequest $purchaseRequest): bool
    {
        return $user->can('purchaseRequest.forceDelete');
    }

    public function review(User $user, PurchaseRequest $purchaseRequest): bool
    {
        return $user->can('purchaseRequest.review');
    }

    public function check(User $user, PurchaseRequest $purchaseRequest): bool
    {
        return $user->can('purchaseRequest.check');
    }

    public function approve(User $user, PurchaseRequest $purchaseRequest): bool
    {
        return $user->can('purchaseRequest.approve');
    }

    public function initial(User $user, PurchaseRequest $purchaseRequest): bool
    {
        return $user->can('purchaseRequest.initial');
    }

    public function verify(User $user, PurchaseRequest $purchaseRequest): bool
    {
        return $user->can('purchaseRequest.verify');
    }

    public function acknowledge(User $user, PurchaseRequest $purchaseRequest): bool
    {
        return $user->can('purchaseRequest.acknowledge');
    }

    public function reassign(User $user, PurchaseRequest $purchaseRequest): bool
    {
        return $user->can('purchaseRequest.reassign');
    }

}
