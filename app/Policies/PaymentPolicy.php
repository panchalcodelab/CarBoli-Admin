<?php

namespace App\Policies;

use App\Models\Payment;
use App\Models\User;

class PaymentPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('manage payments') || $user->can('view financial reports');
    }

    public function view(User $user, Payment $payment): bool
    {
        return $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return $user->can('manage payments')
            || $user->can('manage auction participants')
            || $user->can('manage auctions')
            || $user->can('manage auction results');
    }

    public function capture(User $user, Payment $payment): bool
    {
        return $user->can('manage payments');
    }

    public function fail(User $user, Payment $payment): bool
    {
        return $user->can('manage payments');
    }

    public function release(User $user, Payment $payment): bool
    {
        return $user->can('manage payments');
    }

    public function refund(User $user, Payment $payment): bool
    {
        return $user->can('manage payments');
    }

    public function update(User $user, Payment $payment): bool
    {
        return false;
    }

    public function delete(User $user, Payment $payment): bool
    {
        return false;
    }

    public function deleteAny(User $user): bool
    {
        return false;
    }

    public function restore(User $user, Payment $payment): bool
    {
        return false;
    }

    public function forceDelete(User $user, Payment $payment): bool
    {
        return false;
    }
}
