<?php

namespace App\Notifications\Channels;

use App\Models\NotificationLog;
use App\Notifications\Contracts\NotificationChannel;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Safe default channel. It records the intent to the application log and never
 * contacts an external provider, so no real SMS/WhatsApp/email/push is sent
 * without a configured, credentialed channel being registered explicitly.
 */
class LogNotificationChannel implements NotificationChannel
{
    public function send(NotificationLog $notificationLog): string
    {
        $reference = 'log-'.Str::uuid()->toString();

        Log::info('Notification dispatched via log channel.', [
            'notification_id' => $notificationLog->getKey(),
            'customer_id' => $notificationLog->customer_id,
            'channel' => $notificationLog->channel,
            'event' => $notificationLog->event,
            'template_identifier' => $notificationLog->template_identifier,
            'provider_reference' => $reference,
        ]);

        return $reference;
    }

    public function name(): string
    {
        return 'log';
    }
}
