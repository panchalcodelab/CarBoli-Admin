<?php

namespace App\Services;

use App\Models\Auction;
use App\Models\AuctionParticipant;
use App\Models\Customer;
use App\Models\Payment;

class AuctionEligibilityService
{
    public function isEligible(Customer $customer, Auction $auction): bool
    {
        return $this->status($customer, $auction) === 'eligible';
    }

    public function status(Customer $customer, Auction $auction): string
    {
        if (! $auction->published || ! in_array($auction->status, ['scheduled', 'live'], true)) {
            return 'ineligible';
        }

        if ($customer->account_status !== 'active' || $customer->is_blocked || $customer->kyc_status !== 'approved') {
            return 'ineligible';
        }

        if ((float) $auction->participation_fee <= 0) {
            return 'eligible';
        }

        return $this->hasCapturedEntryFee($customer, $auction) ? 'eligible' : 'pending';
    }

    public function paymentStatus(Customer $customer, Auction $auction): string
    {
        if ((float) $auction->participation_fee <= 0) {
            return 'not_required';
        }

        if ($this->hasCapturedEntryFee($customer, $auction)) {
            return 'captured';
        }

        $latestPaymentStatus = Payment::query()
            ->where('auction_id', $auction->getKey())
            ->where('customer_id', $customer->getKey())
            ->where('payment_type', 'entry_fee')
            ->orderByDesc('id')
            ->value('status');

        return in_array($latestPaymentStatus, ['pending', 'failed', 'released', 'refunded'], true)
            ? $latestPaymentStatus
            : 'pending';
    }

    public function syncParticipant(
        AuctionParticipant $participant,
        Customer $customer,
        Auction $auction,
    ): AuctionParticipant {
        $eligibilityStatus = $this->status($customer, $auction);

        $participant->update([
            'registration_status' => $eligibilityStatus === 'eligible' ? 'approved' : 'requested',
            'entry_fee_amount' => $auction->participation_fee,
            'payment_status' => $this->paymentStatus($customer, $auction),
            'kyc_status' => $customer->kyc_status,
            'eligibility_status' => $eligibilityStatus,
        ]);

        return $participant->refresh()->load(['auction', 'customer']);
    }

    private function hasCapturedEntryFee(Customer $customer, Auction $auction): bool
    {
        return Payment::query()
            ->where('auction_id', $auction->getKey())
            ->where('customer_id', $customer->getKey())
            ->where('currency', 'INR')
            ->where('payment_type', 'entry_fee')
            ->where('status', 'captured')
            ->where('amount', '>=', $auction->participation_fee)
            ->exists();
    }
}
