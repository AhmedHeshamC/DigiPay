<?php

namespace App\Services\Notifications;

use App\Contracts\NotificationSender;
use App\Exceptions\UnsupportedNotificationChannel;

class NotificationFactory
{
    /**
     * Create a notification sender for the given channel.
     *
     * @param string $channel Channel name (email, sms, push)
     * @return NotificationSender
     * @throws UnsupportedNotificationChannel
     */
    public function make(string $channel): NotificationSender
    {
        return match (strtolower($channel)) {
            'email' => new EmailSender(),
            'sms' => new SmsSender(),
            'push' => new PushSender(),
            default => throw new UnsupportedNotificationChannel($channel),
        };
    }

    /**
     * Static factory method for convenience.
     *
     * @param string $channel Channel name (email, sms, push)
     * @return NotificationSender
     * @throws UnsupportedNotificationChannel
     */
    public static function create(string $channel): NotificationSender
    {
        return (new self())->make($channel);
    }
}
