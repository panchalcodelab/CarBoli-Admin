<?php

namespace Tests\Feature;

use App\Models\Auction;
use App\Models\AuctionResult;
use App\Models\Car;
use App\Models\Customer;
use App\Models\Location;
use App\Models\Payment;
use App\Models\User;
use App\Services\AuctionEligibilityService;
use App\Services\AuctionResultService;
use App\Services\BidValidationService;
use App\Services\PaymentWorkflowService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class CarBoliBusinessRulesTest extends TestCase
{
    use RefreshDatabase;

    public function test_eligible_customer_can_be_detected(): void
    {
        $location = Location::factory()->create();
        $car = Car::factory()->create(['location_id' => $location->id]);
        $customer = Customer::factory()->create([
            'account_status' => 'active',
            'kyc_status' => 'approved',
        ]);

        $auction = Auction::factory()->create([
            'car_id' => $car->id,
            'status' => 'live',
            'published' => true,
        ]);

        Payment::factory()->create([
            'customer_id' => $customer->id,
            'auction_id' => $auction->id,
            'amount' => 5000,
            'status' => 'captured',
        ]);

        $this->assertTrue(app(AuctionEligibilityService::class)->isEligible($customer, $auction));
    }

    public function test_bid_below_minimum_increment_is_rejected(): void
    {
        $location = Location::factory()->create();
        $car = Car::factory()->create(['location_id' => $location->id]);
        $customer = Customer::factory()->create([
            'account_status' => 'active',
            'kyc_status' => 'approved',
        ]);

        $auction = Auction::factory()->create([
            'car_id' => $car->id,
            'status' => 'live',
            'published' => true,
            'start_at' => now()->subMinute(),
            'end_at' => now()->addHour(),
            'starting_price' => 1000,
            'minimum_bid_increment' => 100,
        ]);

        Payment::factory()->create([
            'customer_id' => $customer->id,
            'auction_id' => $auction->id,
            'amount' => 5000,
            'status' => 'captured',
        ]);

        $result = app(BidValidationService::class)->validate($auction, $customer, 1050);

        $this->assertFalse($result['valid']);
        $this->assertNotEmpty($result['errors']);
    }

    public function test_auction_result_is_only_finalized_once(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $manager = User::factory()->create();
        $manager->assignRole(Role::findByName('Auction Manager', 'web'));
        $this->actingAs($manager);

        $location = Location::factory()->create();
        $car = Car::factory()->create(['location_id' => $location->id]);
        $customer = Customer::factory()->create();
        $auction = Auction::factory()->create([
            'car_id' => $car->id,
            'status' => 'completed',
            'published' => true,
            'winner_id' => $customer->id,
        ]);

        $result = AuctionResult::factory()->create([
            'auction_id' => $auction->id,
            'status' => 'finalized',
        ]);

        $service = app(AuctionResultService::class);

        $this->assertNull($service->finalize($auction));
        $this->assertSame('finalized', $result->fresh()->status);
    }

    public function test_payment_workflow_uses_controlled_status_updates(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $financeManager = User::factory()->create();
        $financeManager->assignRole(Role::findByName('Finance Manager', 'web'));
        $this->actingAs($financeManager);

        $location = Location::factory()->create();
        $car = Car::factory()->create(['location_id' => $location->id]);
        $customer = Customer::factory()->create([
            'account_status' => 'active',
            'is_blocked' => false,
            'kyc_status' => 'approved',
        ]);
        $auction = Auction::factory()->create([
            'car_id' => $car->id,
            'status' => 'live',
            'published' => true,
            'participation_fee' => 5000,
        ]);

        $payment = Payment::factory()->create([
            'customer_id' => $customer->id,
            'auction_id' => $auction->id,
            'amount' => 5000,
            'status' => 'pending',
        ]);

        $service = app(PaymentWorkflowService::class);
        $service->capture($payment);

        $this->assertSame('captured', $payment->fresh()->status);
    }
}
