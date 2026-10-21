<?php

namespace App\Services;

use App\Jobs\DispatchNotificationJob;
use App\Models\NotificationLog;
use App\Notifications\NotificationChannelManager;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Throwable;

class NotificationService
{
    /**
     * Record the intent to notify. The returned log is the source of truth and
     * starts in the "queued" state. This does NOT dispatch anything.
     *
     * @param  array<string, mixed>  $data
     */
    public function queue(array $data): NotificationLog
    {
        $validated = Validator::make($data, [
            'customer_id' => ['nullable', 'integer', 'exists:customers,id'],
            'type' => ['required', 'string', 'max:255'],
            'channel' => ['required', 'string', 'max:255'],
            'event' => ['required', 'string', 'max:255'],
            'template_identifier' => ['nullable', 'string', 'max:255'],
        ])->validate();

        return NotificationLog::query()->create([
            ...$validated,
            'status' => 'queued',
        ]);
    }

    /**
     * Record the intent to notify and queue it for provider delivery.
     *
     * The notification is NOT delivered synchronously here; the returned log is
     * still "queued". Delivery happens when the queued job runs and routes the
     * outcome back through markSent()/markFailed(). A queued notification is not
     * a delivered notification.
     *
     * @param  array<string, mixed>  $data
     */
    public function dispatch(array $data): NotificationLog
    {
        $notificationLog = $this->queue($data);

        // Dispatch after the surrounding database transaction commits so the
        // job never runs against an uncommitted notification record. When there
        // is no open transaction, Laravel dispatches immediately.
        DispatchNotificationJob::dispatch($notificationLog->getKey())->afterCommit();

        return $notificationLog;
    }

    /**
     * Deliver a queued notification through its channel synchronously, routing
     * the outcome through the existing status transitions. Safe to re-run: it
     * only acts on logs that are still "queued".
     */
    public function deliver(NotificationLog $notificationLog, NotificationChannelManager $channelManager): NotificationLog
    {
        $fresh = NotificationLog::query()->find($notificationLog->getKey());

        if (! $fresh || $fresh->status !== 'queued') {
            return $fresh ?? $notificationLog;
        }

        $channel = $channelManager->resolve((string) $fresh->channel);

        try {
            $reference = $channel->send($fresh);
        } catch (Throwable $exception) {
            return $this->markFailed(
                $fresh,
                $exception->getMessage() !== '' ? $exception->getMessage() : 'Notification delivery failed.',
            );
        }

        return $this->markSent($fresh, $reference);
    }

    public function markSent(NotificationLog $notificationLog, ?string $providerReference = null): NotificationLog
    {
        return DB::transaction(function () use ($notificationLog, $providerReference): NotificationLog {
            $lockedLog = NotificationLog::query()->lockForUpdate()->findOrFail($notificationLog->getKey());

            if ($lockedLog->status === 'sent') {
                return $lockedLog;
            }

            if ($lockedLog->status !== 'queued') {
                throw ValidationException::withMessages([
                    'status' => 'Only queued notifications can be marked as sent.',
                ]);
            }

            $lockedLog->update([
                'status' => 'sent',
                'provider_reference' => $providerReference,
                'sent_at' => now(),
                'failure_reason' => null,
            ]);

            return $lockedLog->refresh();
        });
    }

    public function markFailed(NotificationLog $notificationLog, string $reason): NotificationLog
    {
        $validated = Validator::make(['reason' => $reason], [
            'reason' => ['required', 'string', 'max:2000'],
        ])->validate();

        return DB::transaction(function () use ($notificationLog, $validated): NotificationLog {
            $lockedLog = NotificationLog::query()->lockForUpdate()->findOrFail($notificationLog->getKey());

            if (! in_array($lockedLog->status, ['queued', 'failed'], true)) {
                throw ValidationException::withMessages([
                    'status' => 'Only queued or failed notifications can be marked as failed.',
                ]);
            }

            $lockedLog->update([
                'status' => 'failed',
                'failure_reason' => $validated['reason'],
                'sent_at' => null,
            ]);

            return $lockedLog->refresh();
        });
    }
}
