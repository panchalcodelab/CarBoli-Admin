<?php

namespace App\Services;

use App\Models\Auction;
use App\Models\AuctionParticipant;
use App\Models\Customer;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class AuctionParticipantService
{
    public function __construct(
        private AuctionEligibilityService $eligibilityService,
        private PaymentWorkflowService $paymentWorkflowService,
    ) {}

    public function register(int $auctionId, int $customerId): AuctionParticipant
    {
        Gate::authorize('create', AuctionParticipant::class);

        return DB::transaction(function () use ($auctionId, $customerId): AuctionParticipant {
            $auction = Auction::query()->lockForUpdate()->findOrFail($auctionId);
            $customer = Customer::query()->lockForUpdate()->findOrFail($customerId);

            $this->assertRegistrationIsOpen($auction);

            $participant = $auction->participants()->firstOrCreate(
                ['customer_id' => $customer->getKey()],
                [
                    'registration_status' => 'requested',
                    'entry_fee_amount' => $auction->participation_fee,
                    'participation_date' => now(),
                ],
            );

            $this->paymentWorkflowService->createEntryFeePayment($auction, $customer);

            return $this->eligibilityService->syncParticipant($participant, $customer, $auction);
        });
    }

    public function recheckEligibility(AuctionParticipant $participant): AuctionParticipant
    {
        Gate::authorize('recheckEligibility', $participant);

        return DB::transaction(function () use ($participant): AuctionParticipant {
            $participantReference = AuctionParticipant::query()->findOrFail($participant->getKey());
            $auction = Auction::query()->lockForUpdate()->findOrFail($participantReference->auction_id);
            $customer = Customer::query()->lockForUpdate()->findOrFail($participantReference->customer_id);
            $lockedParticipant = AuctionParticipant::query()
                ->lockForUpdate()
                ->findOrFail($participantReference->getKey());

            return $this->eligibilityService->syncParticipant($lockedParticipant, $customer, $auction);
        });
    }

    private function assertRegistrationIsOpen(Auction $auction): void
    {
        if (! $auction->published || ! in_array($auction->status, ['scheduled', 'live'], true)) {
            throw ValidationException::withMessages([
                'auction_id' => 'Registrations are only available for published scheduled or live auctions.',
            ]);
        }

        if ($auction->end_at?->isPast()) {
            throw ValidationException::withMessages([
                'auction_id' => 'Registrations are closed for this auction.',
            ]);
        }
    }
}
