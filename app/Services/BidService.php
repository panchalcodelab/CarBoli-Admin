<?php

namespace App\Services;

use App\Models\Auction;
use App\Models\Bid;
use App\Models\Customer;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class BidService
{
    public function __construct(
        private BidValidationService $validationService,
        private AuditLogService $auditLogService,
    ) {}

    /**
     * @param  array<string, mixed>|null  $deviceMetadata
     */
    public function placeBid(
        Auction $auction,
        Customer $customer,
        float $amount,
        ?array $deviceMetadata = null,
    ): Bid {
        $validatedMetadata = Validator::make(['device_metadata' => $deviceMetadata], [
            'device_metadata' => ['nullable', 'array', 'max:20'],
        ])->validate()['device_metadata'] ?? null;

        return DB::transaction(function () use ($auction, $customer, $amount, $validatedMetadata): Bid {
            $lockedAuction = Auction::query()->lockForUpdate()->findOrFail($auction->getKey());
            $lockedCustomer = Customer::query()->lockForUpdate()->findOrFail($customer->getKey());
            $validation = $this->validationService->validate($lockedAuction, $lockedCustomer, $amount);

            if (! $validation['valid']) {
                throw ValidationException::withMessages($validation['errors']);
            }

            $sequence = (int) (Bid::query()
                ->where('auction_id', $lockedAuction->getKey())
                ->max('bid_sequence') ?? 0) + 1;

            $bid = Bid::query()->create([
                'auction_id' => $lockedAuction->getKey(),
                'customer_id' => $lockedCustomer->getKey(),
                'bid_amount' => $amount,
                'bid_sequence' => $sequence,
                'status' => 'valid',
                'device_metadata' => $validatedMetadata,
            ]);

            $this->auditLogService->record('bids', 'bid.placed', $bid, null, [
                'auction_id' => $lockedAuction->getKey(),
                'customer_id' => $lockedCustomer->getKey(),
                'bid_amount' => $amount,
                'bid_sequence' => $sequence,
            ]);

            return $bid;
        }, attempts: 3);
    }
}
