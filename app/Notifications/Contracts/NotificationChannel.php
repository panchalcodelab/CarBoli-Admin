<?php

namespace App\Notifications\Contracts;

use App\Models\NotificationLog;

interface NotificationChannel
{
    /**
     * Deliver the notification through the provider.
     *
     * Implementations MUST return a provider reference string on success and
     * MUST throw an exception on failure. Implementations MUST NOT mutate the
     * notification log; status transitions are owned by the caller.
     */
    public function send(NotificationLog $notificationLog): string;

    /**
     * The channel identifier (e.g. "email", "sms", "whatsapp", "push").
     */
    public function name(): string;
}
