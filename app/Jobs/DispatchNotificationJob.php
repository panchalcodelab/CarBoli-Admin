<?php

namespace App\Jobs;

use App\Models\NotificationLog;
use App\Notifications\NotificationChannelManager;
use App\Services\NotificationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;

class DispatchNotificationJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Limit retries so a persistently failing provider cannot loop forever.
     * Each attempt is idempotent because delivery only proceeds for a log that
     * is still in the "queued" state.
     */
    public int $tries = 3;

    public function __construct(public int $notificationLogId) {}

    public function handle(
        NotificationService $notificationService,
        NotificationChannelManager $channelManager,
    ): void {
        $notificationLog = NotificationLog::query()->find($this->notificationLogId);

        if (! $notificationLog || $notificationLog->status !== 'queued') {
            // Already delivered, already failed-terminal, or removed: do nothing
            // so retries and duplicate dispatches cannot re-send or duplicate state.
            return;
        }

        $channel = $channelManager->resolve((string) $notificationLog->channel);

        try {
            $reference = $channel->send($notificationLog);
        } catch (Throwable $exception) {
            $notificationService->markFailed(
                $notificationLog,
                $exception->getMessage() !== '' ? $exception->getMessage() : 'Notification delivery failed.',
            );

            return;
        }

        $notificationService->markSent($notificationLog, $reference);
    }
}
