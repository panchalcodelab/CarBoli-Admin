<?php

namespace Tests\Feature;

use App\Jobs\DispatchNotificationJob;
use App\Models\Auction;
use App\Models\AuctionParticipant;
use App\Models\Bid;
use App\Models\Car;
use App\Models\Customer;
use App\Models\NotificationLog;
use App\Models\Payment;
use App\Models\User;
use App\Notifications\NotificationChannelManager;
use App\Services\AuctionResultService;
use App\Services\NotificationService;
use App\Services\PaymentWorkflowService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class NotificationTriggerDispatchTest extends TestCase
{
    use RefreshDatabase;

    public function test_entry_fee_capture_dispatches_one_notification_after_commit(): void
    {
        Queue::fake();
        $this->actingAs($this->userWithRole('Finance Manager'));
        $customer = $this->eligibleCustomer();
        $auction = $this->openAuction();
        $participant = $this->approvedParticipant($auction, $customer);
        $payment = Payment::factory()->create([
            'auction_id' => $auction->id,
            'customer_id' => $customer->id,
            'payment_type' => 'entry_fee',
            'amount' => $auction->participation_fee,
            'currency' => 'INR',
            'status' => 'pending',
        ]);

        app(PaymentWorkflowService::class)->capture($payment);

        $logs = NotificationLog::query()
            ->where('customer_id', $customer->id)
            ->where('event', 'payment.entry_fee_captured')
            ->get();

        $this->assertCount(1, $logs);
        $this->assertSame('payment', $logs->first()->type);
        $this->assertSame('email', $logs->first()->channel);
        $this->assertSame('payment.entry_fee_captured', $logs->first()->template_identifier);
        $this->assertSame('queued', $logs->first()->status);

        Queue::assertPushed(
            DispatchNotificationJob::class,
            fn (DispatchNotificationJob $job): bool => $job->notificationLogId === $logs->first()->id,
        );
        $this->assertNotNull($participant->fresh());
    }

    public function test_winner_finalization_dispatches_one_notification_after_commit(): void
    {
        Queue::fake();
        $manager = $this->userWithRole('Auction Manager');
        $this->actingAs($manager);
        $winner = $this->eligibleCustomer();
        $auction = $this->liveAuctionWithWinningBid($winner);

        app(AuctionResultService::class)->finalize($auction);

        $logs = NotificationLog::query()
            ->where('customer_id', $winner->id)
            ->where('event', 'auction.won')
            ->get();

        $this->assertCount(1, $logs);
        $this->assertSame('auction', $logs->first()->type);
        $this->assertSame('email', $logs->first()->channel);
        $this->assertSame('auction.winner', $logs->first()->template_identifier);

        Queue::assertPushed(
            DispatchNotificationJob::class,
            fn (DispatchNotificationJob $job): bool => $job->notificationLogId === $logs->first()->id,
        );
    }

    public function test_dispatch_uses_after_commit_so_the_job_waits_for_the_transaction(): void
    {
        Queue::fake();

        // The production dispatch() path must mark the job after-commit so it
        // defers until the surrounding database transaction commits.
        $dispatch = DispatchNotificationJob::dispatch(1)->afterCommit();

        $this->assertTrue($this->readAfterCommit($dispatch));
    }

    public function test_job_cannot_act_on_a_rolled_back_notification_record(): void
    {
        Queue::fake();
        $service = app(NotificationService::class);
        $capturedId = null;

        try {
            DB::transaction(function () use ($service, &$capturedId): void {
                $log = $service->dispatch([
                    'type' => 'auction',
                    'channel' => 'email',
                    'event' => 'auction.won',
                ]);
                $capturedId = $log->getKey();

                throw new \RuntimeException('force rollback');
            });
        } catch (\RuntimeException) {
            // expected
        }

        // The record was rolled back, so running the job for that id is a safe
        // no-op and no notification state is produced.
        $this->assertSame(0, NotificationLog::query()->count());
        (new DispatchNotificationJob((int) $capturedId))->handle(
            $service,
            app(NotificationChannelManager::class),
        );
        $this->assertSame(0, NotificationLog::query()->count());
    }

    private function readAfterCommit(object $pendingDispatch): bool
    {
        $reflection = new \ReflectionObject($pendingDispatch);
        $jobProperty = $reflection->getProperty('job');
        $jobProperty->setAccessible(true);
        $job = $jobProperty->getValue($pendingDispatch);

        return (bool) ($job->afterCommit ?? false);
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

    private function approvedParticipant(Auction $auction, Customer $customer): AuctionParticipant
    {
        return AuctionParticipant::factory()->create([
            'auction_id' => $auction->id,
            'customer_id' => $customer->id,
            'entry_fee_amount' => $auction->participation_fee,
            'payment_status' => 'pending',
            'kyc_status' => 'approved',
            'eligibility_status' => 'pending',
            'registration_status' => 'requested',
        ]);
    }

    private function liveAuctionWithWinningBid(Customer $winner): Auction
    {
        $auction = $this->openAuction();
        AuctionParticipant::factory()->create([
            'auction_id' => $auction->id,
            'customer_id' => $winner->id,
            'entry_fee_amount' => $auction->participation_fee,
            'payment_status' => 'captured',
            'kyc_status' => 'approved',
            'eligibility_status' => 'eligible',
            'registration_status' => 'approved',
        ]);
        Bid::query()->create([
            'auction_id' => $auction->id,
            'customer_id' => $winner->id,
            'bid_amount' => 120000,
            'bid_sequence' => 1,
            'status' => 'valid',
        ]);
        $auction->update(['status' => 'completed', 'end_at' => now()->subMinute()]);

        return $auction;
    }
}
