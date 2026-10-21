<?php

namespace App\Services;

use App\Models\Auction;
use App\Models\AuctionParticipant;
use App\Models\Bid;
use App\Models\Customer;

class BidValidationService
{
    public function __construct(private AuctionEligibilityService $eligibilityService) {}

    /**
     * @return array{valid: bool, errors: array<string, string>, minimum_bid: float}
     */
    public function validate(Auction $auction, Customer $customer, float $amount): array
    {
        $errors = [];

        if (! $auction->published
            || $auction->status !== 'live'
            || ! $auction->start_at
            || ! $auction->end_at
            || $auction->start_at->isFuture()
            || $auction->end_at->isPast()) {
            $errors['auction'] = 'Bids are only accepted while a published auction is live.';
        }

        if (! $this->eligibilityService->isEligible($customer, $auction)) {
            $errors['customer'] = 'The customer is not eligible to bid in this auction.';
        }

        $hasApprovedParticipation = AuctionParticipant::query()
            ->where('auction_id', $auction->getKey())
            ->where('customer_id', $customer->getKey())
            ->where('registration_status', 'approved')
            ->where('eligibility_status', 'eligible')
            ->exists();

        if (! $hasApprovedParticipation) {
            $errors['participant'] = 'The customer must have an approved auction registration to bid.';
        }

        if ($amount <= 0) {
            $errors['bid_amount'] = 'The bid amount must be greater than zero.';
        }

        $increment = (float) $auction->minimum_bid_increment;
        if ($increment <= 0) {
            $errors['minimum_bid_increment'] = 'The auction minimum bid increment must be greater than zero.';
        }

        $highestBid = Bid::query()
            ->where('auction_id', $auction->getKey())
            ->where('status', 'valid')
            ->max('bid_amount');
        $baseAmount = $highestBid === null ? (float) $auction->starting_price : (float) $highestBid;
        $minimumBid = $baseAmount + $increment;

        if ($amount < $minimumBid) {
            $errors['bid_amount'] = "The next bid must be at least {$minimumBid}.";
        }

        return [
            'valid' => $errors === [],
            'errors' => $errors,
            'minimum_bid' => $minimumBid,
        ];
    }
}
