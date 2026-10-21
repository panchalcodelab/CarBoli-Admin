<?php

namespace Tests\Feature;

use App\Models\Auction;
use App\Models\Customer;
use App\Models\Payment;
use App\Models\User;
use App\Services\AuctionEligibilityService;
use App\Services\AuctionParticipantService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AuctionParticipantManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_eligible_customer_can_register_with_a_captured_entry_fee(): void
    {
        $this->auctionManager();
        $auction = $this->openAuction();
        $customer = Customer::factory()->create([
            'account_status' => 'active',
            'is_blocked' => false,
            'kyc_status' => 'approved',
        ]);
        Payment::factory()->create([
            'auction_id' => $auction->id,
            'customer_id' => $customer->id,
            'amount' => $auction->participation_fee,
            'status' => 'captured',
            'payment_type' => 'entry_fee',
        ]);

        $participant = app(AuctionParticipantService::class)->register($auction->id, $customer->id);

        $this->assertSame('eligible', $participant->eligibility_status);
        $this->assertSame('approved', $participant->registration_status);
        $this->assertSame('captured', $participant->payment_status);
        $this->assertSame('approved', $participant->kyc_status);
        $this->assertDatabaseHas('auction_participants', [
            'auction_id' => $auction->id,
            'customer_id' => $customer->id,
            'eligibility_status' => 'eligible',
        ]);
    }

    public function test_pending_payment_does_not_make_a_customer_eligible(): void
    {
        $this->auctionManager();
        $auction = $this->openAuction();
        $customer = Customer::factory()->create([
            'account_status' => 'active',
            'kyc_status' => 'approved',
        ]);

        $participant = app(AuctionParticipantService::class)->register($auction->id, $customer->id);

        $this->assertSame('pending', $participant->eligibility_status);
        $this->assertSame('pending', $participant->payment_status);
        $this->assertFalse(app(AuctionEligibilityService::class)->isEligible($customer, $auction));
    }

    public function test_nonapproved_kyc_and_blocked_customers_are_ineligible(): void
    {
        $this->auctionManager();
        $auction = $this->openAuction();
        $service = app(AuctionParticipantService::class);
        $unverifiedCustomer = Customer::factory()->create([
            'account_status' => 'active',
            'kyc_status' => 'pending',
        ]);
        $blockedCustomer = Customer::factory()->create([
            'account_status' => 'blocked',
            'is_blocked' => true,
            'kyc_status' => 'approved',
        ]);

        $unverifiedParticipant = $service->register($auction->id, $unverifiedCustomer->id);
        $blockedParticipant = $service->register($auction->id, $blockedCustomer->id);

        $this->assertSame('ineligible', $unverifiedParticipant->eligibility_status);
        $this->assertSame('ineligible', $blockedParticipant->eligibility_status);
    }

    public function test_rechecking_after_payment_capture_updates_eligibility(): void
    {
        $this->auctionManager();
        $auction = $this->openAuction();
        $customer = Customer::factory()->create([
            'account_status' => 'active',
            'kyc_status' => 'approved',
        ]);
        $service = app(AuctionParticipantService::class);
        $participant = $service->register($auction->id, $customer->id);

        Payment::factory()->create([
            'auction_id' => $auction->id,
            'customer_id' => $customer->id,
            'amount' => $auction->participation_fee,
            'status' => 'captured',
            'payment_type' => 'entry_fee',
        ]);

        $updatedParticipant = $service->recheckEligibility($participant);

        $this->assertSame('eligible', $updatedParticipant->eligibility_status);
        $this->assertSame('captured', $updatedParticipant->payment_status);
    }

    public function test_underpaid_or_wrong_currency_entry_fee_does_not_qualify(): void
    {
        $this->auctionManager();
        $auction = $this->openAuction();
        $customer = Customer::factory()->create([
            'account_status' => 'active',
            'kyc_status' => 'approved',
        ]);
        $service = app(AuctionParticipantService::class);
        $participant = $service->register($auction->id, $customer->id);
        $payment = Payment::factory()->create([
            'auction_id' => $auction->id,
            'customer_id' => $customer->id,
            'amount' => $auction->participation_fee - 1,
            'status' => 'captured',
            'payment_type' => 'entry_fee',
            'currency' => 'INR',
        ]);

        $this->assertSame('pending', $service->recheckEligibility($participant)->eligibility_status);

        $payment->update([
            'amount' => $auction->participation_fee,
            'currency' => 'USD',
        ]);

        $this->assertSame('pending', $service->recheckEligibility($participant)->eligibility_status);

        $payment->update(['currency' => 'INR']);

        $this->assertSame('eligible', $service->recheckEligibility($participant)->eligibility_status);
    }

    public function test_duplicate_registration_reuses_the_existing_participant(): void
    {
        $this->auctionManager();
        $auction = $this->openAuction(fee: 0);
        $customer = Customer::factory()->create([
            'account_status' => 'active',
            'kyc_status' => 'approved',
        ]);
        $service = app(AuctionParticipantService::class);

        $firstParticipant = $service->register($auction->id, $customer->id);
        $secondParticipant = $service->register($auction->id, $customer->id);

        $this->assertSame($firstParticipant->id, $secondParticipant->id);
        $this->assertDatabaseCount('auction_participants', 1);
        $this->assertSame('not_required', $secondParticipant->payment_status);
    }

    public function test_registration_is_rejected_for_unpublished_auctions(): void
    {
        $this->auctionManager();
        $auction = Auction::factory()->create([
            'status' => 'draft',
            'published' => false,
        ]);
        $customer = Customer::factory()->create();

        $this->expectException(ValidationException::class);
        app(AuctionParticipantService::class)->register($auction->id, $customer->id);
    }

    public function test_users_without_auction_permissions_cannot_register_participants(): void
    {
        $this->actingAs(User::factory()->create());
        $auction = $this->openAuction();
        $customer = Customer::factory()->create();

        $this->expectException(AuthorizationException::class);
        app(AuctionParticipantService::class)->register($auction->id, $customer->id);
    }

    private function auctionManager(): User
    {
        $this->seed(RolesAndPermissionsSeeder::class);

        $manager = User::factory()->create();
        $manager->assignRole(Role::findByName('Auction Manager', 'web'));
        $this->actingAs($manager);

        return $manager;
    }

    private function openAuction(float $fee = 5000): Auction
    {
        return Auction::factory()->create([
            'status' => 'live',
            'published' => true,
            'participation_fee' => $fee,
            'end_at' => now()->addHour(),
        ]);
    }
}
