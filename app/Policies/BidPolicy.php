<?php

namespace App\Policies;

use App\Models\Bid;
use App\Models\User;

class BidPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('manage auctions') || $user->can('manage auction results');
    }

    public function view(User $user, Bid $bid): bool
    {
        return $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return false;
    }

    public function update(User $user, Bid $bid): bool
    {
        return false;
    }

    public function delete(User $user, Bid $bid): bool
    {
        return false;
    }

    public function restore(User $user, Bid $bid): bool
    {
        return false;
    }

    public function forceDelete(User $user, Bid $bid): bool
    {
        return false;
    }

    public function deleteAny(User $user): bool
    {
        return false;
    }
}
