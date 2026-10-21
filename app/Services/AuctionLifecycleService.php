<?php

namespace App\Services;

use App\Models\Auction;
use App\Models\Car;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class AuctionLifecycleService
{
    /**
     * Auction statuses that represent a live claim on a car. A published auction
     * in one of these states blocks publishing another auction for the same car.
     *
     * @var list<string>
     */
    private const array ACTIVE_AUCTION_STATUSES = ['scheduled', 'live'];

    /**
     * Terminal/withdrawn car statuses for which a new auction must not be published.
     *
     * @var list<string>
     */
    private const array INELIGIBLE_CAR_STATUSES_FOR_PUBLISH = ['sold', 'removed', 'archived'];

    public function __construct(private AuditLogService $auditLogService) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): Auction
    {
        Gate::authorize('create', Auction::class);

        $validated = $this->validate($data);

        return DB::transaction(function () use ($validated): Auction {
            $auction = Auction::query()->create([
                ...$validated,
                'participation_fee' => $validated['participation_fee'] ?? 0,
                'status' => 'draft',
                'published' => false,
                'created_by' => Auth::id(),
            ]);

            $this->auditLogService->record('auctions', 'auction.created', $auction, null, [
                'status' => $auction->status,
                'car_id' => $auction->car_id,
            ]);

            return $auction;
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(Auction $auction, array $data): Auction
    {
        Gate::authorize('update', $auction);

        $validated = $this->validate($data);

        return DB::transaction(function () use ($auction, $validated): Auction {
            $lockedAuction = Auction::query()->lockForUpdate()->findOrFail($auction->getKey());

            if ($lockedAuction->published || $lockedAuction->status !== 'draft') {
                throw ValidationException::withMessages([
                    'status' => 'Only unpublished draft auctions can be edited.',
                ]);
            }

            $lockedAuction->update($validated);

            return $lockedAuction->refresh();
        });
    }

    public function publish(Auction $auction): Auction
    {
        Gate::authorize('publish', $auction);

        return DB::transaction(function () use ($auction): Auction {
            $lockedAuction = Auction::query()->lockForUpdate()->findOrFail($auction->getKey());

            if ($lockedAuction->published || $lockedAuction->status !== 'draft') {
                throw ValidationException::withMessages([
                    'status' => 'Only draft auctions can be published.',
                ]);
            }

            if (! $lockedAuction->start_at || ! $lockedAuction->end_at) {
                throw ValidationException::withMessages([
                    'start_at' => 'A start and end date are required before publishing.',
                ]);
            }

            if ($lockedAuction->start_at->greaterThanOrEqualTo($lockedAuction->end_at)) {
                throw ValidationException::withMessages([
                    'end_at' => 'The auction end date must be after its start date.',
                ]);
            }

            if ($lockedAuction->end_at->isPast()) {
                throw ValidationException::withMessages([
                    'end_at' => 'An auction cannot be published after its end date.',
                ]);
            }

            if ((float) $lockedAuction->starting_price < 0 || (float) $lockedAuction->minimum_bid_increment <= 0) {
                throw ValidationException::withMessages([
                    'minimum_bid_increment' => 'A positive minimum bid increment is required before publishing.',
                ]);
            }

            // Lock the car row before the conflict check so two concurrent publishes
            // for the same car are serialized and cannot both succeed.
            $car = Car::query()->lockForUpdate()->findOrFail($lockedAuction->car_id);

            if (in_array($car->status, self::INELIGIBLE_CAR_STATUSES_FOR_PUBLISH, true)) {
                throw ValidationException::withMessages([
                    'car_id' => 'This car is not eligible for a published auction in its current status.',
                ]);
            }

            // Approval gate (Q6): a car must be approved before its auction may be
            // published. isApproved() treats an "available" car as approved, which
            // grandfathers legacy cars created before the approval workflow existed.
            // A car still in draft/under_review cannot have a published auction.
            if (! $car->isApproved()) {
                throw ValidationException::withMessages([
                    'car_id' => 'This car must be approved (documents and inspection) before its auction can be published.',
                ]);
            }

            $hasConflictingActiveAuction = Auction::query()
                ->where('car_id', $lockedAuction->car_id)
                ->whereKeyNot($lockedAuction->getKey())
                ->where('published', true)
                ->whereIn('status', self::ACTIVE_AUCTION_STATUSES)
                ->lockForUpdate()
                ->exists();

            if ($hasConflictingActiveAuction) {
                throw ValidationException::withMessages([
                    'car_id' => 'This car already has an active auction and cannot be published again.',
                ]);
            }

            $lockedAuction->update([
                'published' => true,
                'published_by' => Auth::id(),
                'published_at' => now(),
                'status' => $lockedAuction->start_at->isFuture() ? 'scheduled' : 'live',
            ]);

            $this->auditLogService->record('auctions', 'auction.published', $lockedAuction, [
                'status' => 'draft',
                'published' => false,
            ], [
                'status' => $lockedAuction->status,
                'published' => true,
            ]);

            return $lockedAuction->refresh();
        });
    }

    public function cancel(Auction $auction): Auction
    {
        Gate::authorize('cancel', $auction);

        return DB::transaction(function () use ($auction): Auction {
            $lockedAuction = Auction::query()->lockForUpdate()->findOrFail($auction->getKey());

            if (! in_array($lockedAuction->status, ['draft', 'scheduled', 'live'], true)) {
                throw ValidationException::withMessages([
                    'status' => 'Only draft, scheduled, or live auctions can be cancelled.',
                ]);
            }

            $lockedAuction->update(['status' => 'cancelled']);
            $this->auditLogService->record('auctions', 'auction.cancelled', $lockedAuction, [
                'status' => $auction->status,
            ], ['status' => 'cancelled']);

            return $lockedAuction->refresh();
        });
    }

    public function close(Auction $auction): Auction
    {
        Gate::authorize('close', $auction);

        return DB::transaction(function () use ($auction): Auction {
            $lockedAuction = Auction::query()->lockForUpdate()->findOrFail($auction->getKey());

            if (! in_array($lockedAuction->status, ['scheduled', 'live'], true)) {
                throw ValidationException::withMessages([
                    'status' => 'Only scheduled or live auctions can be closed.',
                ]);
            }

            if (! $lockedAuction->end_at || $lockedAuction->end_at->isFuture()) {
                throw ValidationException::withMessages([
                    'end_at' => 'An auction can only be closed at or after its end date.',
                ]);
            }

            $lockedAuction->update(['status' => 'completed']);
            $this->auditLogService->record('auctions', 'auction.closed', $lockedAuction, [
                'status' => $auction->status,
            ], ['status' => 'completed']);

            return $lockedAuction->refresh();
        });
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function validate(array $data): array
    {
        $validated = Validator::make($data, [
            'car_id' => ['required', 'integer', Rule::exists('cars', 'id')->whereNull('deleted_at')],
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'start_at' => ['nullable', 'date'],
            'end_at' => ['nullable', 'date'],
            'starting_price' => ['required', 'numeric', 'min:0'],
            'minimum_bid_increment' => ['required', 'numeric', 'gt:0'],
            'reserve_price' => ['nullable', 'numeric', 'min:0'],
            'participation_fee' => ['sometimes', 'numeric', 'min:0'],
        ])->validate();

        if (filled($validated['start_at'] ?? null) && filled($validated['end_at'] ?? null)
            && ! Carbon::parse($validated['end_at'])->greaterThan(Carbon::parse($validated['start_at']))) {
            throw ValidationException::withMessages([
                'end_at' => 'The auction end date must be after its start date.',
            ]);
        }

        return $validated;
    }
}
