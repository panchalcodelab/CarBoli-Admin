<?php

namespace Tests\Feature;

use App\Models\Auction;
use App\Models\AuctionParticipant;
use App\Models\Car;
use App\Models\Customer;
use App\Models\CustomerKyc;
use App\Models\Payment;
use App\Models\User;
use App\Services\AuctionLifecycleService;
use App\Services\AuctionParticipantService;
use App\Services\AuctionResultService;
use App\Services\BidService;
use App\Services\KycReviewService;
use App\Services\PaymentWorkflowService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class EndToEndRegressionTest extends TestCase
{
    use RefreshDatabase;

    // -------------------------------------------------------------------------
    // Full happy-path: KYC → auction → bid → result → settlement
    // -------------------------------------------------------------------------

    public function test_full_auction_workflow_from_kyc_to_settlement(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);

        // 1. KYC approval
        $reviewer = $this->userWithRole('Super Admin');
        $this->actingAs($reviewer);

        $winner = Customer::factory()->create(['account_status' => 'active', 'kyc_status' => 'pending']);
        $loser = Customer::factory()->create(['account_status' => 'active', 'kyc_status' => 'pending']);

        $winnerKyc = CustomerKyc::factory()->create(['customer_id' => $winner->id]);
        $loserKyc = CustomerKyc::factory()->create(['customer_id' => $loser->id]);

        $kycService = app(KycReviewService::class);
        $kycService->markUnderReview($winnerKyc);
        $kycService->approve($winnerKyc->fresh());
        $kycService->markUnderReview($loserKyc);
        $kycService->approve($loserKyc->fresh());

        $this->assertSame('approved', $winner->fresh()->kyc_status);
        $this->assertSame('approved', $loser->fresh()->kyc_status);

        // 2. Create and publish auction
        $manager = $this->userWithRole('Auction Manager');
        $this->actingAs($manager);

        $car = Car::factory()->create(['status' => 'available']);
        $lifecycle = app(AuctionLifecycleService::class);

        $auction = $lifecycle->create([
            'car_id' => $car->id,
            'title' => 'E2E Test Auction',
            'start_at' => now()->subMinute(),
            'end_at' => now()->addHour(),
            'starting_price' => 10000,
            'minimum_bid_increment' => 500,
            'participation_fee' => 0,
        ]);

        // publish transitions draft → scheduled/live based on start_at
        $published = $lifecycle->publish($auction);
        // force to live so bids are accepted
        $published->update(['status' => 'live']);
        $published = $published->fresh();

        $this->assertTrue($published->published);

        // 3. Register participants
        $participantService = app(AuctionParticipantService::class);
        $winnerParticipant = $participantService->register($published->id, $winner->id);
        $loserParticipant = $participantService->register($published->id, $loser->id);

        $this->assertSame('eligible', $winnerParticipant->eligibility_status);
        $this->assertSame('eligible', $loserParticipant->eligibility_status);

        // 4. Place bids
        $bidService = app(BidService::class);
        $loserBid = $bidService->placeBid($published, $loser, 10500);
        $winnerBid = $bidService->placeBid($published, $winner, 11000);

        $this->assertSame(1, $loserBid->bid_sequence);
        $this->assertSame(2, $winnerBid->bid_sequence);

        // 5. Close and finalize
        $published->update(['status' => 'completed', 'end_at' => now()->subMinute()]);
        $result = app(AuctionResultService::class)->finalize($published->fresh());

        $this->assertNotNull($result);
        $this->assertSame($winner->id, $result->winner_id);
        $this->assertSame($winnerBid->id, $result->winning_bid_id);
        $this->assertSame('pending', $result->settlement_status);

        // 6. Capture winning payment
        $financeManager = $this->userWithRole('Finance Manager');
        $this->actingAs($financeManager);

        $winningPayment = Payment::query()
            ->where('auction_id', $published->id)
            ->where('payment_type', 'winning_bid')
            ->firstOrFail();

        $captured = app(PaymentWorkflowService::class)->capture($winningPayment);

        $this->assertSame('captured', $captured->status);
        $this->assertSame('settled', $result->fresh()->settlement_status);
    }

    // -------------------------------------------------------------------------
    // Duplicate result finalization is idempotent
    // -------------------------------------------------------------------------

    public function test_duplicate_finalization_is_safe(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $this->actingAs($this->userWithRole('Auction Manager'));

        $auction = $this->completedAuction();
        $customer = $this->eligibleParticipant($auction);
        app(BidService::class)->placeBid($auction, $customer, 10500);
        $auction->update(['status' => 'completed', 'end_at' => now()->subMinute()]);

        $service = app(AuctionResultService::class);
        $first = $service->finalize($auction->fresh());
        $second = $service->finalize($auction->fresh());

        $this->assertNotNull($first);
        $this->assertNull($second);
        $this->assertDatabaseCount('auction_results', 1);
    }

    // -------------------------------------------------------------------------
    // Blocked customer cannot bid even with approved participation
    // -------------------------------------------------------------------------

    public function test_blocked_customer_cannot_bid_after_being_blocked(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $this->actingAs($this->userWithRole('Auction Manager'));

        $auction = $this->liveAuction();
        $customer = Customer::factory()->create([
            'account_status' => 'active',
            'is_blocked' => false,
            'kyc_status' => 'approved',
        ]);

        AuctionParticipant::factory()->create([
            'auction_id' => $auction->id,
            'customer_id' => $customer->id,
            'registration_status' => 'approved',
            'eligibility_status' => 'eligible',
            'payment_status' => 'not_required',
            'kyc_status' => 'approved',
        ]);

        $customer->update(['account_status' => 'blocked', 'is_blocked' => true]);

        $this->expectException(ValidationException::class);
        app(BidService::class)->placeBid($auction, $customer->fresh(), 10500);
    }

    // -------------------------------------------------------------------------
    // Reserve price not met — no winner, no payment, settlement not required
    // -------------------------------------------------------------------------

    public function test_reserve_price_not_met_produces_no_winner_and_no_payment(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $this->actingAs($this->userWithRole('Auction Manager'));

        $auction = $this->completedAuction(['reserve_price' => 50000]);
        $customer = $this->eligibleParticipant($auction);
        app(BidService::class)->placeBid($auction, $customer, 10500);
        $auction->update(['status' => 'completed', 'end_at' => now()->subMinute()]);

        $result = app(AuctionResultService::class)->finalize($auction->fresh());

        $this->assertNull($result->winner_id);
        $this->assertSame('not_required', $result->settlement_status);
        $this->assertDatabaseCount('payments', 0);
    }

    // -------------------------------------------------------------------------
    // Cross-role permission regression
    // -------------------------------------------------------------------------

    public function test_each_role_can_only_access_its_permitted_resources(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);

        $checks = [
            'Super Admin' => ['manage auctions', 'manage payments', 'manage staff', 'approve kyc', 'manage cars'],
            'Auction Manager' => ['manage auctions', 'manage auction participants'],
            'Car / Inventory Manager' => ['manage cars', 'manage inspections'],
            'Inspection Staff' => ['view inspections', 'update assigned inspections'],
            'Finance Manager' => ['manage payments', 'view financial reports'],
            'Customer Support' => ['view customers', 'view customer kyc'],
        ];

        $denied = [
            'Auction Manager' => ['manage payments', 'manage staff', 'approve kyc', 'manage cars'],
            'Car / Inventory Manager' => ['manage auctions', 'manage payments', 'manage staff'],
            'Inspection Staff' => ['manage auctions', 'manage payments', 'manage cars', 'manage staff'],
            'Finance Manager' => ['manage auctions', 'manage staff', 'manage cars', 'approve kyc'],
            'Customer Support' => ['manage auctions', 'manage payments', 'manage staff', 'manage cars'],
        ];

        foreach ($checks as $roleName => $permissions) {
            $user = User::factory()->create(['status' => 'active']);
            $user->assignRole(Role::findByName($roleName, 'web'));

            foreach ($permissions as $permission) {
                $this->assertTrue(
                    $user->can($permission),
                    "{$roleName} should have [{$permission}]"
                );
            }
        }

        foreach ($denied as $roleName => $permissions) {
            $user = User::factory()->create(['status' => 'active']);
            $user->assignRole(Role::findByName($roleName, 'web'));

            foreach ($permissions as $permission) {
                $this->assertFalse(
                    $user->can($permission),
                    "{$roleName} must NOT have [{$permission}]"
                );
            }
        }
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    private function userWithRole(string $roleName): User
    {
        $user = User::factory()->create(['status' => 'active']);
        $user->assignRole(Role::findByName($roleName, 'web'));

        return $user;
    }

    private function liveAuction(array $overrides = []): Auction
    {
        return Auction::factory()->create(array_merge([
            'status' => 'live',
            'published' => true,
            'start_at' => now()->subMinute(),
            'end_at' => now()->addHour(),
            'starting_price' => 10000,
            'minimum_bid_increment' => 500,
            'participation_fee' => 0,
        ], $overrides));
    }

    private function completedAuction(array $overrides = []): Auction
    {
        // Must be published=true so BidService accepts bids before we mark completed
        $auction = $this->liveAuction($overrides);

        // bids are placed while live; then mark completed for finalization
        return $auction;
    }

    private function eligibleParticipant(Auction $auction): Customer
    {
        $customer = Customer::factory()->create([
            'account_status' => 'active',
            'is_blocked' => false,
            'kyc_status' => 'approved',
        ]);

        AuctionParticipant::factory()->create([
            'auction_id' => $auction->id,
            'customer_id' => $customer->id,
            'registration_status' => 'approved',
            'eligibility_status' => 'eligible',
            'payment_status' => 'not_required',
            'kyc_status' => 'approved',
        ]);

        return $customer;
    }
}
