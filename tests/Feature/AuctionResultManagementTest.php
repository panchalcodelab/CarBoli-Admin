<?php

namespace Tests\Feature;

use App\Models\Auction;
use App\Models\AuctionParticipant;
use App\Models\Car;
use App\Models\Customer;
use App\Models\Payment;
use App\Models\User;
use App\Services\AuctionResultService;
use App\Services\BidService;
use App\Services\PaymentWorkflowService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AuctionResultManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_finalization_uses_highest_valid_bid_and_creates_winner_payment_afterward(): void
    {
        $manager = $this->auctionManager();
        $auction = $this->auction();
        $firstCustomer = $this->eligibleCustomer();
        $winner = $this->eligibleCustomer();
        $this->registerParticipant($auction, $firstCustomer);
        $this->registerParticipant($auction, $winner);
        $bidService = app(BidService::class);
        $firstBid = $bidService->placeBid($auction, $firstCustomer, 1100);
        $winningBid = $bidService->placeBid($auction, $winner, 1200);
        $auction->update(['status' => 'completed', 'end_at' => now()->subMinute()]);

        $result = app(AuctionResultService::class)->finalize($auction);

        $this->assertNotNull($result);
        $this->assertSame($winner->id, $result->winner_id);
        $this->assertSame($winningBid->id, $result->winning_bid_id);
        $this->assertNotSame($firstBid->id, $result->winning_bid_id);
        $this->assertSame('pending', $result->settlement_status);
        $this->assertSame($winner->id, $auction->fresh()->winner_id);
        $this->assertDatabaseHas('payments', [
            'auction_id' => $auction->id,
            'customer_id' => $winner->id,
            'payment_type' => 'winning_bid',
            'amount' => 1200,
            'status' => 'pending',
        ]);
        $this->assertSame($manager->id, $result->finalized_by);
    }

    public function test_reserve_price_not_met_finalizes_without_a_winner_or_payment(): void
    {
        $this->auctionManager();
        $auction = $this->auction(['reserve_price' => 2000]);
        $customer = $this->eligibleCustomer();
        $this->registerParticipant($auction, $customer);
        app(BidService::class)->placeBid($auction, $customer, 1100);
        $auction->update(['status' => 'completed', 'end_at' => now()->subMinute()]);

        $result = app(AuctionResultService::class)->finalize($auction);

        $this->assertNotNull($result);
        $this->assertNull($result->winner_id);
        $this->assertNull($result->winning_bid_id);
        $this->assertSame('not_required', $result->settlement_status);
        $this->assertDatabaseCount('payments', 0);
    }

    public function test_result_cannot_be_finalized_before_auction_completion(): void
    {
        $this->auctionManager();
        $auction = $this->auction(['status' => 'live']);

        $this->expectException(ValidationException::class);
        app(AuctionResultService::class)->finalize($auction);
    }

    public function test_result_cannot_be_finalized_before_auction_end_time(): void
    {
        $this->auctionManager();
        $auction = $this->auction(['status' => 'completed']);

        $this->expectException(ValidationException::class);
        app(AuctionResultService::class)->finalize($auction);
    }

    public function test_finalized_result_cannot_be_created_twice(): void
    {
        $this->auctionManager();
        $auction = $this->auction();
        $customer = $this->eligibleCustomer();
        $this->registerParticipant($auction, $customer);
        app(BidService::class)->placeBid($auction, $customer, 1100);
        $auction->update(['status' => 'completed', 'end_at' => now()->subMinute()]);
        $service = app(AuctionResultService::class);

        $firstResult = $service->finalize($auction);
        $secondResult = $service->finalize($auction);

        $this->assertNotNull($firstResult);
        $this->assertNull($secondResult);
        $this->assertDatabaseCount('auction_results', 1);
        $this->assertDatabaseCount('payments', 1);
    }

    public function test_result_settlement_changes_to_settled_after_winner_payment_capture(): void
    {
        $this->auctionManager();
        $auction = $this->auction();
        $winner = $this->eligibleCustomer();
        $this->registerParticipant($auction, $winner);
        app(BidService::class)->placeBid($auction, $winner, 1100);
        $auction->update(['status' => 'completed', 'end_at' => now()->subMinute()]);
        $result = app(AuctionResultService::class)->finalize($auction);
        $payment = Payment::query()
            ->where('auction_id', $auction->id)
            ->where('payment_type', 'winning_bid')
            ->firstOrFail();
        $this->assertSame('pending', $result->settlement_status);

        $this->actingAs($this->userWithRole('Finance Manager'));
        app(PaymentWorkflowService::class)->capture($payment);

        $this->assertSame('settled', $result->fresh()->settlement_status);
    }

    private function auctionManager(): User
    {
        $this->seed(RolesAndPermissionsSeeder::class);

        $manager = User::factory()->create();
        $manager->assignRole(Role::findByName('Auction Manager', 'web'));
        $this->actingAs($manager);

        return $manager;
    }

    private function userWithRole(string $roleName): User
    {
        $this->seed(RolesAndPermissionsSeeder::class);

        $user = User::factory()->create();
        $user->assignRole(Role::findByName($roleName, 'web'));

        return $user;
    }

    private function auction(array $overrides = []): Auction
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

    private function registerParticipant(Auction $auction, Customer $customer): AuctionParticipant
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
