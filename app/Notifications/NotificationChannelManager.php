<?php

namespace App\Notifications;

use App\Notifications\Channels\LogNotificationChannel;
use App\Notifications\Contracts\NotificationChannel;

/**
 * Resolves a notification channel by its name. Unknown or unconfigured channels
 * fall back to the safe log channel so that no external provider is contacted
 * unless a real, credentialed channel has been explicitly registered.
 */
class NotificationChannelManager
{
    /**
     * @var array<string, NotificationChannel>
     */
    private array $channels = [];

    public function __construct(private NotificationChannel $defaultChannel)
    {
        $this->register($defaultChannel);
    }

    public function register(NotificationChannel $channel): void
    {
        $this->channels[$channel->name()] = $channel;
    }

    public function resolve(string $channelName): NotificationChannel
    {
        return $this->channels[$channelName] ?? $this->defaultChannel;
    }

    public function default(): NotificationChannel
    {
        return $this->defaultChannel;
    }

    public static function make(): self
    {
        return new self(new LogNotificationChannel);
    }
}
