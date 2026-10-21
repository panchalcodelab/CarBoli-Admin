<?php

namespace App\Policies;

use App\Models\Auction;
use App\Models\User;

class AuctionPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('manage auctions')
            || $user->can('create auctions')
            || $user->can('edit auctions')
            || $user->can('publish auctions');
    }

    public function view(User $user, Auction $auction): bool
    {
        return $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return $user->can('manage auctions') || $user->can('create auctions');
    }

    public function update(User $user, Auction $auction): bool
    {
        return ($user->can('manage auctions') || $user->can('edit auctions'))
            && $auction->status === 'draft'
            && ! $auction->published;
    }

    public function publish(User $user, Auction $auction): bool
    {
        return $user->can('manage auctions') || $user->can('publish auctions');
    }

    public function cancel(User $user, Auction $auction): bool
    {
        return $user->can('manage auctions');
    }

    public function close(User $user, Auction $auction): bool
    {
        return $user->can('manage auctions');
    }

    public function finalize(User $user, Auction $auction): bool
    {
        return $user->can('manage auctions') || $user->can('manage auction results');
    }

    public function delete(User $user, Auction $auction): bool
    {
        return false;
    }

    public function deleteAny(User $user): bool
    {
        return false;
    }

    public function restore(User $user, Auction $auction): bool
    {
        return false;
    }

    public function forceDelete(User $user, Auction $auction): bool
    {
        return false;
    }
}
