<?php

namespace Tests\Feature;

use App\Jobs\DispatchNotificationJob;
use App\Models\Customer;
use App\Models\NotificationLog;
use App\Notifications\Contracts\NotificationChannel;
use App\Notifications\NotificationChannelManager;
use App\Services\NotificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use RuntimeException;
use Tests\TestCase;

class NotificationDispatchTest extends TestCase
{
    use RefreshDatabase;

    public function test_dispatch_queues_a_log_and_a_job_without_marking_it_sent(): void
    {
        Queue::fake();
        $customer = Customer::factory()->create();

        $log = app(NotificationService::class)->dispatch([
            'customer_id' => $customer->id,
            'type' => 'auction',
            'channel' => 'email',
            'event' => 'auction.won',
            'template_identifier' => 'auction.winner',
        ]);

        $this->assertSame('queued', $log->status);
        $this->assertDatabaseHas('notifications', ['id' => $log->id, 'status' => 'queued']);
        Queue::assertPushed(DispatchNotificationJob::class, fn (DispatchNotificationJob $job): bool => $job->notificationLogId === $log->id);
    }

    public function test_fake_channel_receives_the_correct_notification_and_recipient(): void
    {
        $customer = Customer::factory()->create();
        $spy = new RecordingNotificationChannel('email');
        $manager = new NotificationChannelManager($spy);

        $service = app(NotificationService::class);
        $log = $service->queue([
            'customer_id' => $customer->id,
            'type' => 'auction',
            'channel' => 'email',
            'event' => 'auction.won',
            'template_identifier' => 'auction.winner',
        ]);

        $service->deliver($log, $manager);

        $this->assertCount(1, $spy->received);
        $received = $spy->received[0];
        $this->assertSame($log->id, $received->id);
        $this->assertSame($customer->id, $received->customer_id);
        $this->assertSame('email', $received->channel);
        $this->assertSame('auction.won', $received->event);
    }

    public function test_successful_delivery_marks_the_log_sent_with_provider_reference(): void
    {
        $spy = new RecordingNotificationChannel('email', 'provider-ref-xyz');
        $manager = new NotificationChannelManager($spy);
        $service = app(NotificationService::class);
        $log = $service->queue([
            'type' => 'payment',
            'channel' => 'email',
            'event' => 'payment.entry_fee_captured',
        ]);

        $result = $service->deliver($log, $manager);

        $this->assertSame('sent', $result->status);
        $this->assertSame('provider-ref-xyz', $result->provider_reference);
        $this->assertNotNull($result->sent_at);
        $this->assertNull($result->failure_reason);
    }

    public function test_provider_failure_marks_the_log_failed_without_throwing(): void
    {
        $manager = new NotificationChannelManager(new FailingNotificationChannel('sms'));
        $service = app(NotificationService::class);
        $log = $service->queue([
            'type' => 'payment',
            'channel' => 'sms',
            'event' => 'payment.failed',
        ]);

        $result = $service->deliver($log, $manager);

        $this->assertSame('failed', $result->status);
        $this->assertSame('Provider rejected the request.', $result->failure_reason);
        $this->assertNull($result->sent_at);
    }

    public function test_redelivery_does_not_resend_or_duplicate_records(): void
    {
        $spy = new RecordingNotificationChannel('email');
        $manager = new NotificationChannelManager($spy);
        $service = app(NotificationService::class);
        $log = $service->queue([
            'type' => 'auction',
            'channel' => 'email',
            'event' => 'auction.won',
        ]);

        $service->deliver($log, $manager);
        // Second delivery attempt (as a retry would do) must be a no-op.
        $service->deliver($log->fresh(), $manager);

        $this->assertCount(1, $spy->received);
        $this->assertSame(1, NotificationLog::query()->count());
        $this->assertSame('sent', $log->fresh()->status);
    }

    public function test_job_handler_delivers_a_queued_notification(): void
    {
        $spy = new RecordingNotificationChannel('email');
        $this->app->instance(NotificationChannelManager::class, new NotificationChannelManager($spy));

        $log = app(NotificationService::class)->queue([
            'type' => 'auction',
            'channel' => 'email',
            'event' => 'auction.won',
        ]);

        (new DispatchNotificationJob($log->id))->handle(
            app(NotificationService::class),
            app(NotificationChannelManager::class),
        );

        $this->assertCount(1, $spy->received);
        $this->assertSame('sent', $log->fresh()->status);
    }

    public function test_unknown_channel_falls_back_to_default_channel(): void
    {
        $default = new RecordingNotificationChannel('log');
        $manager = new NotificationChannelManager($default);
        $service = app(NotificationService::class);
        $log = $service->queue([
            'type' => 'auction',
            'channel' => 'whatsapp',
            'event' => 'auction.reminder',
        ]);

        $service->deliver($log, $manager);

        $this->assertCount(1, $default->received);
        $this->assertSame('sent', $log->fresh()->status);
    }
}

class RecordingNotificationChannel implements NotificationChannel
{
    /** @var list<NotificationLog> */
    public array $received = [];

    public function __construct(private string $channelName, private string $reference = 'recorded-ref') {}

    public function send(NotificationLog $notificationLog): string
    {
        $this->received[] = $notificationLog;

        return $this->reference;
    }

    public function name(): string
    {
        return $this->channelName;
    }
}

class FailingNotificationChannel implements NotificationChannel
{
    public function __construct(private string $channelName) {}

    public function send(NotificationLog $notificationLog): string
    {
        throw new RuntimeException('Provider rejected the request.');
    }

    public function name(): string
    {
        return $this->channelName;
    }
}
