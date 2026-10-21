<?php

namespace App\Policies;

use App\Models\AuctionResult;
use App\Models\User;

class AuctionResultPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('manage auctions') || $user->can('manage auction results');
    }

    public function view(User $user, AuctionResult $auctionResult): bool
    {
        return $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return false;
    }

    public function update(User $user, AuctionResult $auctionResult): bool
    {
        return false;
    }

    public function delete(User $user, AuctionResult $auctionResult): bool
    {
        return false;
    }

    public function restore(User $user, AuctionResult $auctionResult): bool
    {
        return false;
    }

    public function forceDelete(User $user, AuctionResult $auctionResult): bool
    {
        return false;
    }

    public function deleteAny(User $user): bool
    {
        return false;
    }
}
