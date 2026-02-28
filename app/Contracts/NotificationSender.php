<?php

namespace App\Contracts;

use App\DTO\NotificationPayload;

interface NotificationSender
{
    /**
     * Send a notification through this channel.
     *
     * @param NotificationPayload $payload The notification data to send
     * @return void
     * @throws \Exception If sending fails
     */
    public function send(NotificationPayload $payload): void;
}
