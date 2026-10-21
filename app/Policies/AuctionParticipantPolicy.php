<?php

namespace App\Policies;

use App\Models\AuctionParticipant;
use App\Models\User;

class AuctionParticipantPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('manage auction participants') || $user->can('manage auctions');
    }

    public function view(User $user, AuctionParticipant $auctionParticipant): bool
    {
        return $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return $this->viewAny($user);
    }

    public function update(User $user, AuctionParticipant $auctionParticipant): bool
    {
        return false;
    }

    public function delete(User $user, AuctionParticipant $auctionParticipant): bool
    {
        return false;
    }

    public function recheckEligibility(User $user, AuctionParticipant $auctionParticipant): bool
    {
        return $this->viewAny($user);
    }

    public function deleteAny(User $user): bool
    {
        return false;
    }

    public function restore(User $user, AuctionParticipant $auctionParticipant): bool
    {
        return false;
    }

    public function forceDelete(User $user, AuctionParticipant $auctionParticipant): bool
    {
        return false;
    }
}
