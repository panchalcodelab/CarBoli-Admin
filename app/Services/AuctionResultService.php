<?php

namespace App\Services;

use App\Models\Auction;
use App\Models\AuctionResult;
use App\Models\Bid;
use App\Models\Customer;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class AuctionResultService
{
    public function __construct(
        private PaymentWorkflowService $paymentWorkflowService,
        private AuditLogService $auditLogService,
    ) {}

    public function finalize(Auction $auction): ?AuctionResult
    {
        Gate::authorize('finalize', $auction);

        return DB::transaction(function () use ($auction): ?AuctionResult {
            $lockedAuction = Auction::query()->lockForUpdate()->findOrFail($auction->getKey());

            $existingResult = AuctionResult::query()
                ->where('auction_id', $lockedAuction->getKey())
                ->lockForUpdate()
                ->first();

            if ($existingResult?->status === 'finalized') {
                return null;
            }

            if ($lockedAuction->status !== 'completed'
                || ! $lockedAuction->end_at
                || $lockedAuction->end_at->isFuture()) {
                throw ValidationException::withMessages([
                    'status' => 'Only auctions completed at or after their end time can be finalized.',
                ]);
            }

            $highestBid = Bid::query()
                ->where('auction_id', $lockedAuction->getKey())
                ->where('status', 'valid')
                ->orderByDesc('bid_amount')
                ->orderBy('bid_sequence')
                ->orderBy('id')
                ->lockForUpdate()
                ->first();

            $winningBid = $highestBid;
            if ($highestBid && $lockedAuction->reserve_price !== null
                && (float) $highestBid->bid_amount < (float) $lockedAuction->reserve_price) {
                $winningBid = null;
            }

            $winner = $winningBid
                ? Customer::query()->lockForUpdate()->findOrFail($winningBid->customer_id)
                : null;
            $finalizedAt = now();
            $resultData = [
                'winner_id' => $winner?->getKey(),
                'winning_bid_id' => $winningBid?->getKey(),
                'status' => 'finalized',
                'finalized_by' => Auth::id(),
                'finalized_at' => $finalizedAt,
                'settlement_status' => $winner ? 'pending' : 'not_required',
            ];

            $result = $existingResult
                ? tap($existingResult)->update($resultData)
                : AuctionResult::query()->create([
                    'auction_id' => $lockedAuction->getKey(),
                    ...$resultData,
                ]);

            $lockedAuction->update(['winner_id' => $winner?->getKey()]);

            if ($winner && $winningBid) {
                $this->paymentWorkflowService->createWinningPayment(
                    $lockedAuction,
                    $winner,
                    (float) $winningBid->bid_amount,
                );
            }

            $this->auditLogService->record('auction_results', 'auction.result_finalized', $result, null, [
                'auction_id' => $lockedAuction->getKey(),
                'winner_id' => $winner?->getKey(),
                'winning_bid_id' => $winningBid?->getKey(),
                'settlement_status' => $result->settlement_status,
            ]);

            if ($winner) {
                app(NotificationService::class)->dispatch([
                    'customer_id' => $winner->getKey(),
                    'type' => 'auction',
                    'channel' => 'email',
                    'event' => 'auction.won',
                    'template_identifier' => 'auction.winner',
                ]);
            }

            return $result->refresh()->load(['auction', 'winner', 'winningBid', 'finalizedBy']);
        }, attempts: 3);
    }
}
