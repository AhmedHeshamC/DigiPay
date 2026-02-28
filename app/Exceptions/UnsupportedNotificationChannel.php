<?php

namespace App\Exceptions;

use InvalidArgumentException;

class UnsupportedNotificationChannel extends InvalidArgumentException
{
    /**
     * @var array<string> Supported notification channels
     */
    private const SUPPORTED_CHANNELS = ['email', 'sms', 'push'];

    /**
     * Create a new exception for an unsupported channel.
     *
     * @param string $channel The unsupported channel that was requested
     */
    public function __construct(string $channel)
    {
        $supportedList = implode(', ', self::SUPPORTED_CHANNELS);

        parent::__construct(
            "Unsupported notification channel: '{$channel}'. Supported channels are: {$supportedList}."
        );
    }
}
