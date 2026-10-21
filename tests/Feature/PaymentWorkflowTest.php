<?php

namespace Tests\Feature;

use App\Models\Auction;
use App\Models\AuctionParticipant;
use App\Models\Car;
use App\Models\Customer;
use App\Models\Payment;
use App\Models\User;
use App\Services\AuctionParticipantService;
use App\Services\PaymentWorkflowService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class PaymentWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_participant_registration_creates_one_pending_entry_fee_payment(): void
    {
        $this->actingAs($this->userWithRole('Auction Manager'));
        $auction = $this->openAuction();
        $customer = $this->eligibleCustomer();
        $service = app(AuctionParticipantService::class);

        $service->register($auction->id, $customer->id);
        $service->register($auction->id, $customer->id);

        $payment = Payment::query()
            ->where('auction_id', $auction->id)
            ->where('customer_id', $customer->id)
            ->firstOrFail();

        $this->assertSame('entry_fee', $payment->payment_type);
        $this->assertSame('pending', $payment->status);
        $this->assertSame('5000.00', $payment->amount);
        $this->assertDatabaseCount('payments', 1);
    }

    public function test_finance_manager_can_capture_entry_fee_and_refresh_participant_eligibility(): void
    {
        $this->actingAs($this->userWithRole('Auction Manager'));
        $auction = $this->openAuction();
        $customer = $this->eligibleCustomer();
        $participant = app(AuctionParticipantService::class)->register($auction->id, $customer->id);
        $payment = Payment::query()
            ->where('auction_id', $auction->id)
            ->where('customer_id', $customer->id)
            ->firstOrFail();
        $this->actingAs($this->userWithRole('Finance Manager'));

        $capturedPayment = app(PaymentWorkflowService::class)->capture($payment);

        $this->assertSame('captured', $capturedPayment->status);
        $this->assertNotNull($capturedPayment->captured_at);
        $this->assertNotNull($capturedPayment->successful_at);
        $this->assertSame('eligible', $participant->fresh()->eligibility_status);
        $this->assertSame('captured', $participant->fresh()->payment_status);
    }

    public function test_pending_payment_can_fail_with_a_reason_and_cannot_be_captured_afterward(): void
    {
        $this->actingAs($this->userWithRole('Finance Manager'));
        $auction = $this->openAuction();
        $payment = Payment::factory()->create([
            'auction_id' => $auction->id,
            'status' => 'pending',
        ]);
        $service = app(PaymentWorkflowService::class);

        $failedPayment = $service->fail($payment, 'Gateway declined the transaction.');

        $this->assertSame('failed', $failedPayment->status);
        $this->assertSame('Gateway declined the transaction.', $failedPayment->failure_reason);

        try {
            $service->capture($failedPayment);
            $this->fail('A failed payment must not be captured.');
        } catch (ValidationException) {
            $this->assertSame('failed', $failedPayment->fresh()->status);
        }
    }

    public function test_failed_entry_fee_updates_participant_payment_status(): void
    {
        $this->actingAs($this->userWithRole('Auction Manager'));
        $auction = $this->openAuction();
        $customer = $this->eligibleCustomer();
        $participant = app(AuctionParticipantService::class)->register($auction->id, $customer->id);
        $payment = Payment::query()
            ->where('auction_id', $auction->id)
            ->where('customer_id', $customer->id)
            ->firstOrFail();
        $this->actingAs($this->userWithRole('Finance Manager'));

        app(PaymentWorkflowService::class)->fail($payment, 'Gateway declined the transaction.');

        $this->assertSame('failed', $participant->fresh()->payment_status);
        $this->assertSame('pending', $participant->fresh()->eligibility_status);
    }

    public function test_only_finance_managers_can_change_payment_status(): void
    {
        $this->actingAs(User::factory()->create());
        $auction = $this->openAuction();
        $payment = Payment::factory()->create(['auction_id' => $auction->id]);

        $this->expectException(AuthorizationException::class);
        app(PaymentWorkflowService::class)->capture($payment);
    }

    public function test_finance_manager_can_release_nonwinner_entry_fee_after_finalized_result(): void
    {
        $this->actingAs($this->userWithRole('Finance Manager'));
        $winner = $this->eligibleCustomer();
        $losingCustomer = $this->eligibleCustomer();
        $auction = $this->openAuction();
        $this->finalizeAuction($auction, $winner);
        $participant = AuctionParticipant::factory()->create([
            'auction_id' => $auction->id,
            'customer_id' => $losingCustomer->id,
            'entry_fee_amount' => $auction->participation_fee,
            'payment_status' => 'captured',
            'kyc_status' => 'approved',
            'eligibility_status' => 'eligible',
            'registration_status' => 'approved',
        ]);
        $payment = Payment::factory()->create([
            'auction_id' => $auction->id,
            'customer_id' => $losingCustomer->id,
            'status' => 'captured',
            'payment_type' => 'entry_fee',
        ]);

        $releasedPayment = app(PaymentWorkflowService::class)->release($payment);

        $this->assertSame('released', $releasedPayment->status);
        $this->assertNotNull($releasedPayment->released_at);
        $this->assertSame('released', $participant->fresh()->payment_status);
        $this->assertSame('ineligible', $participant->fresh()->eligibility_status);
    }

    public function test_finance_manager_can_refund_a_nonwinner_entry_fee(): void
    {
        $this->actingAs($this->userWithRole('Finance Manager'));
        $winner = $this->eligibleCustomer();
        $losingCustomer = $this->eligibleCustomer();
        $auction = $this->openAuction();
        $this->finalizeAuction($auction, $winner);
        $participant = AuctionParticipant::factory()->create([
            'auction_id' => $auction->id,
            'customer_id' => $losingCustomer->id,
            'payment_status' => 'captured',
            'eligibility_status' => 'eligible',
            'registration_status' => 'approved',
        ]);
        $payment = Payment::factory()->create([
            'auction_id' => $auction->id,
            'customer_id' => $losingCustomer->id,
            'status' => 'captured',
            'payment_type' => 'entry_fee',
        ]);

        $refundedPayment = app(PaymentWorkflowService::class)->refund($payment);

        $this->assertSame('refunded', $refundedPayment->status);
        $this->assertNotNull($refundedPayment->refunded_at);
        $this->assertSame('refunded', $participant->fresh()->payment_status);
    }

    public function test_winning_payment_is_created_and_captured_only_for_the_finalized_winner(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $winner = $this->eligibleCustomer();
        $auction = $this->openAuction();
        $this->finalizeAuction($auction, $winner);
        $service = app(PaymentWorkflowService::class);
        $this->actingAs($this->userWithRole('Auction Manager'));

        $payment = $service->createWinningPayment($auction, $winner, 125000);
        $duplicatePayment = $service->createWinningPayment($auction, $winner, 125000);
        $this->assertSame($payment->id, $duplicatePayment->id);

        $this->actingAs($this->userWithRole('Finance Manager'));
        $capturedPayment = $service->capture($payment);

        $this->assertSame('winning_bid', $capturedPayment->payment_type);
        $this->assertSame('captured', $capturedPayment->status);
        $this->assertDatabaseCount('payments', 1);
    }

    public function test_winner_entry_fee_cannot_be_released(): void
    {
        $this->actingAs($this->userWithRole('Finance Manager'));
        $winner = $this->eligibleCustomer();
        $auction = $this->openAuction();
        $this->finalizeAuction($auction, $winner);
        $payment = Payment::factory()->create([
            'auction_id' => $auction->id,
            'customer_id' => $winner->id,
            'status' => 'captured',
            'payment_type' => 'entry_fee',
        ]);

        $this->expectException(ValidationException::class);
        app(PaymentWorkflowService::class)->release($payment);
    }

    public function test_financial_panel_is_read_only_for_finance_managers(): void
    {
        $financeManager = $this->userWithRole('Finance Manager');
        $this->actingAs($financeManager);
        $payment = Payment::factory()->create();

        $this->assertTrue(Gate::allows('viewAny', Payment::class));
        $this->assertTrue(Gate::allows('view', $payment));
        $this->assertFalse(Gate::allows('update', $payment));
        $this->assertFalse(Gate::allows('delete', $payment));
    }

    private function userWithRole(string $roleName): User
    {
        $this->seed(RolesAndPermissionsSeeder::class);

        $user = User::factory()->create();
        $user->assignRole(Role::findByName($roleName, 'web'));

        return $user;
    }

    private function openAuction(): Auction
    {
        $car = Car::factory()->create();

        return Auction::factory()->create([
            'car_id' => $car->id,
            'status' => 'live',
            'published' => true,
            'start_at' => now()->subMinute(),
            'end_at' => now()->addHour(),
            'participation_fee' => 5000,
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

    private function finalizeAuction(Auction $auction, Customer $winner): void
    {
        $auction->update([
            'status' => 'completed',
            'winner_id' => $winner->getKey(),
        ]);

        DB::table('auction_results')->insert([
            'auction_id' => $auction->getKey(),
            'winner_id' => $winner->getKey(),
            'winning_bid_id' => null,
            'status' => 'finalized',
            'finalized_by' => null,
            'finalized_at' => now(),
            'settlement_status' => 'pending',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

    }
}
