<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Exceptions\UnsupportedNotificationChannel;

class UnsupportedNotificationChannelTest extends TestCase
{
    public function test_exception_extends_invalid_argument(): void
    {
        $exception = new UnsupportedNotificationChannel('whatsapp');

        $this->assertInstanceOf(\InvalidArgumentException::class, $exception);
    }

    public function test_exception_contains_channel_in_message(): void
    {
        $exception = new UnsupportedNotificationChannel('whatsapp');

        $this->assertStringContainsString('whatsapp', $exception->getMessage());
        $this->assertStringContainsString('Unsupported notification channel', $exception->getMessage());
    }

    public function test_exception_message_lists_supported_channels(): void
    {
        $exception = new UnsupportedNotificationChannel('telegram');

        $message = $exception->getMessage();
        $this->assertStringContainsString('email', $message);
        $this->assertStringContainsString('sms', $message);
        $this->assertStringContainsString('push', $message);
    }
}
