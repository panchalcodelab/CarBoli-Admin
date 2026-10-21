<?php

namespace Tests\Feature;

use App\Models\Auction;
use App\Models\AuctionParticipant;
use App\Models\Car;
use App\Models\Customer;
use App\Services\BidService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class BidManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_first_bid_must_meet_starting_price_plus_increment(): void
    {
        $auction = $this->liveAuction();
        $customer = $this->eligibleCustomer();
        $this->registerEligibleParticipant($auction, $customer);
        $service = app(BidService::class);

        try {
            $service->placeBid($auction, $customer, 1099);
            $this->fail('A first bid below the opening minimum must be rejected.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('bid_amount', $exception->errors());
            $this->assertDatabaseCount('bids', 0);
        }

        $bid = $service->placeBid($auction, $customer, 1100);

        $this->assertSame('1100.00', $bid->bid_amount);
        $this->assertSame(1, $bid->bid_sequence);
    }

    public function test_minimum_increment_applies_to_each_subsequent_bid(): void
    {
        $auction = $this->liveAuction();
        $firstCustomer = $this->eligibleCustomer();
        $secondCustomer = $this->eligibleCustomer();
        $this->registerEligibleParticipant($auction, $firstCustomer);
        $this->registerEligibleParticipant($auction, $secondCustomer);
        $service = app(BidService::class);
        $firstBid = $service->placeBid($auction, $firstCustomer, 1100);

        try {
            $service->placeBid($auction, $secondCustomer, 1199);
            $this->fail('A subsequent bid below the minimum increment must be rejected.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('bid_amount', $exception->errors());
            $this->assertDatabaseCount('bids', 1);
        }

        $secondBid = $service->placeBid($auction, $secondCustomer, 1200, ['source' => 'web']);

        $this->assertSame(1, $firstBid->bid_sequence);
        $this->assertSame(2, $secondBid->bid_sequence);
        $this->assertSame(['source' => 'web'], $secondBid->device_metadata);
    }

    public function test_ineligible_or_unregistered_customers_cannot_bid(): void
    {
        $auction = $this->liveAuction();
        $unregisteredCustomer = $this->eligibleCustomer();

        try {
            app(BidService::class)->placeBid($auction, $unregisteredCustomer, 1100);
            $this->fail('A customer without approved participation must not bid.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('participant', $exception->errors());
        }

        $blockedCustomer = Customer::factory()->create([
            'account_status' => 'blocked',
            'is_blocked' => true,
            'kyc_status' => 'approved',
        ]);
        $this->registerEligibleParticipant($auction, $blockedCustomer);

        $this->expectException(ValidationException::class);
        app(BidService::class)->placeBid($auction, $blockedCustomer, 1100);
    }

    public function test_bids_are_rejected_after_the_auction_window_closes(): void
    {
        $auction = $this->liveAuction(['end_at' => now()->subMinute()]);
        $customer = $this->eligibleCustomer();
        $this->registerEligibleParticipant($auction, $customer);

        $this->expectException(ValidationException::class);
        app(BidService::class)->placeBid($auction, $customer, 1100);
    }

    private function liveAuction(array $overrides = []): Auction
    {
        $car = Car::factory()->create();

        return Auction::factory()->create([
            'car_id' => $car->id,
            'status' => 'live',
            'published' => true,
            'start_at' => now()->subMinute(),
            'end_at' => now()->addHour(),
            'starting_price' => 1000,
            'minimum_bid_increment' => 100,
            'participation_fee' => 0,
            ...$overrides,
        ]);
    }

    private function eligibleCustomer(): Customer
    {
        return Customer::factory()->create([
            'account_status' => 'active',
            'is_blocked' => false,
            'kyc_status' => 'approved',
        ]);
    }

    private function registerEligibleParticipant(Auction $auction, Customer $customer): AuctionParticipant
    {
        return AuctionParticipant::factory()->create([
            'auction_id' => $auction->id,
            'customer_id' => $customer->id,
            'registration_status' => 'approved',
            'payment_status' => 'not_required',
            'kyc_status' => 'approved',
            'eligibility_status' => 'eligible',
        ]);
    }
}
