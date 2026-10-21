<?php

namespace App\Services;

use App\Models\Auction;
use App\Models\AuctionParticipant;
use App\Models\Customer;
use App\Models\Payment;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class PaymentWorkflowService
{
    public function __construct(
        private AuctionEligibilityService $eligibilityService,
        private AuditLogService $auditLogService,
    ) {}

    public function createEntryFeePayment(Auction $auction, Customer $customer): ?Payment
    {
        Gate::authorize('create', Payment::class);

        if ((float) $auction->participation_fee <= 0) {
            return null;
        }

        return DB::transaction(function () use ($auction, $customer): ?Payment {
            $lockedAuction = Auction::query()->lockForUpdate()->findOrFail($auction->getKey());
            $lockedCustomer = Customer::query()->lockForUpdate()->findOrFail($customer->getKey());

            $this->assertEntryFeePaymentCanBeCreated($lockedAuction);

            if ($lockedCustomer->account_status !== 'active'
                || $lockedCustomer->is_blocked
                || $lockedCustomer->kyc_status !== 'approved') {
                return null;
            }

            $existingPayment = Payment::query()
                ->where('auction_id', $lockedAuction->getKey())
                ->where('customer_id', $lockedCustomer->getKey())
                ->where('payment_type', 'entry_fee')
                ->whereIn('status', ['pending', 'captured'])
                ->lockForUpdate()
                ->first();

            if ($existingPayment) {
                if ($existingPayment->currency !== 'INR'
                    || (float) $existingPayment->amount < (float) $lockedAuction->participation_fee) {
                    throw ValidationException::withMessages([
                        'amount' => 'The existing entry-fee payment does not cover the configured auction fee.',
                    ]);
                }

                return $existingPayment;
            }

            return Payment::query()->create([
                'auction_id' => $lockedAuction->getKey(),
                'customer_id' => $lockedCustomer->getKey(),
                'amount' => $lockedAuction->participation_fee,
                'currency' => 'INR',
                'status' => 'pending',
                'payment_type' => 'entry_fee',
            ]);
        }, attempts: 3);
    }

    public function createWinningPayment(Auction $auction, Customer $customer, float $amount): Payment
    {
        Gate::authorize('create', Payment::class);

        $validated = Validator::make(['amount' => $amount], [
            'amount' => ['required', 'numeric', 'gt:0'],
        ])->validate();

        return DB::transaction(function () use ($auction, $customer, $validated): Payment {
            $lockedAuction = Auction::query()->lockForUpdate()->findOrFail($auction->getKey());
            $lockedCustomer = Customer::query()->lockForUpdate()->findOrFail($customer->getKey());
            $result = $this->finalizedResult($lockedAuction);

            if ($lockedAuction->status !== 'completed'
                || (int) $lockedAuction->winner_id !== (int) $lockedCustomer->getKey()
                || ! $result
                || (int) $result->winner_id !== (int) $lockedCustomer->getKey()) {
                throw ValidationException::withMessages([
                    'customer_id' => 'A winning payment can only be created for the finalized auction winner.',
                ]);
            }

            $existingPayment = Payment::query()
                ->where('auction_id', $lockedAuction->getKey())
                ->where('customer_id', $lockedCustomer->getKey())
                ->where('payment_type', 'winning_bid')
                ->whereIn('status', ['pending', 'captured'])
                ->lockForUpdate()
                ->first();

            if ($existingPayment) {
                if ($existingPayment->currency !== 'INR'
                    || (float) $existingPayment->amount !== (float) $validated['amount']) {
                    throw ValidationException::withMessages([
                        'amount' => 'A winning payment already exists with a different amount.',
                    ]);
                }

                return $existingPayment;
            }

            return Payment::query()->create([
                'auction_id' => $lockedAuction->getKey(),
                'customer_id' => $lockedCustomer->getKey(),
                'amount' => $validated['amount'],
                'currency' => 'INR',
                'status' => 'pending',
                'payment_type' => 'winning_bid',
            ]);
        }, attempts: 3);
    }

    public function capture(Payment $payment): Payment
    {
        Gate::authorize('capture', $payment);

        return DB::transaction(function () use ($payment): Payment {
            [$lockedPayment, $auction, $customer] = $this->lockPaymentContext($payment);

            if ($lockedPayment->status === 'captured') {
                if ($lockedPayment->payment_type === 'winning_bid') {
                    $this->syncSettlementStatus($auction);
                }

                return $lockedPayment;
            }

            $this->assertPending($lockedPayment);

            if ($lockedPayment->payment_type === 'entry_fee') {
                $this->assertEntryFeeCanBeCaptured($lockedPayment, $auction, $customer);
            } elseif ($lockedPayment->payment_type === 'winning_bid') {
                $this->assertWinningPaymentCanBeCaptured($lockedPayment, $auction, $customer);
            } else {
                throw ValidationException::withMessages([
                    'payment_type' => 'This payment type cannot be captured through this workflow.',
                ]);
            }

            $lockedPayment->update([
                'status' => 'captured',
                'successful_at' => now(),
                'captured_at' => now(),
                'failure_reason' => null,
            ]);

            $this->auditLogService->record('payments', 'payment.captured', $lockedPayment, [
                'status' => 'pending',
            ], [
                'status' => 'captured',
                'amount' => $lockedPayment->amount,
                'payment_type' => $lockedPayment->payment_type,
            ]);

            if ($lockedPayment->payment_type === 'entry_fee') {
                $this->refreshParticipant($auction, $customer);
                app(NotificationService::class)->dispatch([
                    'customer_id' => $customer->getKey(),
                    'type' => 'payment',
                    'channel' => 'email',
                    'event' => 'payment.entry_fee_captured',
                    'template_identifier' => 'payment.entry_fee_captured',
                ]);
            } else {
                $this->syncSettlementStatus($auction);
            }

            return $lockedPayment->refresh();
        }, attempts: 3);
    }

    public function fail(Payment $payment, string $reason): Payment
    {
        Gate::authorize('fail', $payment);

        $validated = Validator::make(['reason' => $reason], [
            'reason' => ['required', 'string', 'max:2000'],
        ])->validate();

        return DB::transaction(function () use ($payment, $validated): Payment {
            [$lockedPayment] = $this->lockPaymentContext($payment);

            if ($lockedPayment->status === 'failed') {
                return $lockedPayment;
            }

            $this->assertPending($lockedPayment);
            $lockedPayment->update([
                'status' => 'failed',
                'failure_reason' => $validated['reason'],
            ]);

            $this->auditLogService->record('payments', 'payment.failed', $lockedPayment, [
                'status' => 'pending',
            ], [
                'status' => 'failed',
                'failure_reason' => $validated['reason'],
            ]);

            if ($lockedPayment->payment_type === 'entry_fee') {
                $this->refreshParticipant($lockedPayment->auction, $lockedPayment->customer);
            }

            return $lockedPayment->refresh();
        }, attempts: 3);
    }

    public function release(Payment $payment): Payment
    {
        Gate::authorize('release', $payment);

        return DB::transaction(function () use ($payment): Payment {
            [$lockedPayment, $auction] = $this->lockPaymentContext($payment);

            if ($lockedPayment->status === 'released') {
                return $lockedPayment;
            }

            $this->assertCapturedLoserEntryFee($lockedPayment, $auction);
            $lockedPayment->update([
                'status' => 'released',
                'released_at' => now(),
            ]);
            $this->auditLogService->record('payments', 'payment.released', $lockedPayment, [
                'status' => 'captured',
            ], ['status' => 'released']);

            $this->refreshParticipant($auction, $lockedPayment->customer);
            $this->syncSettlementStatus($auction);

            return $lockedPayment->refresh();
        }, attempts: 3);
    }

    public function refund(Payment $payment): Payment
    {
        Gate::authorize('refund', $payment);

        return DB::transaction(function () use ($payment): Payment {
            [$lockedPayment, $auction] = $this->lockPaymentContext($payment);

            if ($lockedPayment->status === 'refunded') {
                return $lockedPayment;
            }

            $this->assertCapturedLoserEntryFee($lockedPayment, $auction);
            $lockedPayment->update([
                'status' => 'refunded',
                'refunded_at' => now(),
            ]);
            $this->auditLogService->record('payments', 'payment.refunded', $lockedPayment, [
                'status' => 'captured',
            ], ['status' => 'refunded']);

            $this->refreshParticipant($auction, $lockedPayment->customer);
            $this->syncSettlementStatus($auction);

            return $lockedPayment->refresh();
        }, attempts: 3);
    }

    private function assertEntryFeePaymentCanBeCreated(Auction $auction): void
    {
        if (! $auction->published || ! in_array($auction->status, ['scheduled', 'live'], true)
            || $auction->end_at?->isPast()) {
            throw ValidationException::withMessages([
                'auction_id' => 'Entry-fee payments can only be created for open auctions.',
            ]);
        }
    }

    private function assertEntryFeeCanBeCaptured(Payment $payment, Auction $auction, Customer $customer): void
    {
        if (! $auction->published || ! in_array($auction->status, ['scheduled', 'live'], true)
            || $auction->end_at?->isPast()) {
            throw ValidationException::withMessages([
                'auction_id' => 'Entry fees can only be captured for open auctions.',
            ]);
        }

        if ($customer->account_status !== 'active' || $customer->is_blocked || $customer->kyc_status !== 'approved') {
            throw ValidationException::withMessages([
                'customer_id' => 'Entry fees can only be captured for active customers with approved KYC.',
            ]);
        }

        if ($payment->currency !== 'INR' || (float) $payment->amount < (float) $auction->participation_fee) {
            throw ValidationException::withMessages([
                'amount' => 'The captured entry-fee amount must cover the auction fee in INR.',
            ]);
        }
    }

    private function assertWinningPaymentCanBeCaptured(Payment $payment, Auction $auction, Customer $customer): void
    {
        $result = $this->finalizedResult($auction);

        if ($auction->status !== 'completed' || (int) $auction->winner_id !== (int) $customer->getKey()
            || ! $result || (int) $result->winner_id !== (int) $customer->getKey()) {
            throw ValidationException::withMessages([
                'customer_id' => 'Only the finalized auction winner can have a winning payment captured.',
            ]);
        }

        if ($payment->amount <= 0 || $payment->currency !== 'INR') {
            throw ValidationException::withMessages([
                'amount' => 'A winning payment must have a positive amount in INR.',
            ]);
        }
    }

    private function assertCapturedLoserEntryFee(Payment $payment, Auction $auction): void
    {
        if ($payment->status !== 'captured' || $payment->payment_type !== 'entry_fee') {
            throw ValidationException::withMessages([
                'status' => 'Only captured entry-fee payments can be released or refunded.',
            ]);
        }

        if ($auction->status !== 'completed') {
            throw ValidationException::withMessages([
                'auction_id' => 'Entry fees can only be released or refunded after the auction is completed.',
            ]);
        }

        $result = $this->finalizedResult($auction);

        if ((int) $result->winner_id === (int) $payment->customer_id) {
            throw ValidationException::withMessages([
                'customer_id' => 'The auction winner’s entry fee cannot be released or refunded.',
            ]);
        }
    }

    private function assertPending(Payment $payment): void
    {
        if ($payment->status !== 'pending') {
            throw ValidationException::withMessages([
                'status' => 'Only pending payments can be captured or marked as failed.',
            ]);
        }
    }

    /**
     * @return array{0: Payment, 1: Auction, 2: Customer}
     */
    private function lockPaymentContext(Payment $payment): array
    {
        $paymentReference = Payment::query()->findOrFail($payment->getKey());

        if (! $paymentReference->auction_id) {
            throw ValidationException::withMessages([
                'auction_id' => 'Auction payments must belong to an auction.',
            ]);
        }

        $auction = Auction::query()->lockForUpdate()->findOrFail($paymentReference->auction_id);
        $customer = Customer::query()->lockForUpdate()->findOrFail($paymentReference->customer_id);
        $lockedPayment = Payment::query()->lockForUpdate()->findOrFail($paymentReference->getKey());

        return [$lockedPayment, $auction, $customer];
    }

    private function refreshParticipant(Auction $auction, Customer $customer): void
    {
        $participant = AuctionParticipant::query()
            ->where('auction_id', $auction->getKey())
            ->where('customer_id', $customer->getKey())
            ->lockForUpdate()
            ->first();

        if ($participant) {
            $this->eligibilityService->syncParticipant($participant, $customer, $auction);
        }
    }

    private function finalizedResult(Auction $auction): ?object
    {
        $result = DB::table('auction_results')
            ->where('auction_id', $auction->getKey())
            ->where('status', 'finalized')
            ->lockForUpdate()
            ->first();

        if ($result && (int) $result->winner_id !== (int) $auction->winner_id) {
            throw ValidationException::withMessages([
                'winner_id' => 'The auction winner must match its finalized result before settlement.',
            ]);
        }

        return $result;
    }

    private function syncSettlementStatus(Auction $auction): void
    {
        $result = $this->finalizedResult($auction);

        if (! $result) {
            return;
        }

        $hasCapturedLosingEntryFee = Payment::query()
            ->where('auction_id', $auction->getKey())
            ->where('payment_type', 'entry_fee')
            ->where('status', 'captured')
            ->when(
                $result->winner_id !== null,
                fn ($query) => $query->where('customer_id', '<>', $result->winner_id),
            )
            ->exists();

        $hasCapturedEntryFee = Payment::query()
            ->where('auction_id', $auction->getKey())
            ->where('payment_type', 'entry_fee')
            ->where('status', 'captured')
            ->exists();

        $winnerPaymentCaptured = $result->winner_id === null || Payment::query()
            ->where('auction_id', $auction->getKey())
            ->where('customer_id', $result->winner_id)
            ->where('payment_type', 'winning_bid')
            ->where('status', 'captured')
            ->exists();

        $settlementStatus = $result->winner_id === null && ! $hasCapturedEntryFee
            ? 'not_required'
            : ($winnerPaymentCaptured && ! $hasCapturedLosingEntryFee ? 'settled' : 'pending');

        DB::table('auction_results')
            ->where('id', $result->id)
            ->update([
                'settlement_status' => $settlementStatus,
                'updated_at' => now(),
            ]);
    }
}
