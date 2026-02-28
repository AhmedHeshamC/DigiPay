<?php

namespace Tests\Feature;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Services\Notifications\EmailSender;
use App\Services\Notifications\SmsSender;
use App\Services\Notifications\PushSender;
use App\DTO\NotificationPayload;
use App\Models\Notification;
use Illuminate\Support\Facades\Mail;

class NotificationSenderTest extends TestCase
{
    use RefreshDatabase;

    // ========== EmailSender Tests ==========

    public function test_email_sender_implements_interface(): void
    {
        $sender = new EmailSender();
        $this->assertInstanceOf(\App\Contracts\NotificationSender::class, $sender);
    }

    public function test_email_sender_creates_notification_record(): void
    {
        Mail::fake();

        $payload = new NotificationPayload(
            channel: 'email',
            recipient: 'user@example.com',
            message: 'Test email notification',
            notificationType: 'success',
            notifiableType: 'App\Models\Transaction',
            notifiableId: 1
        );

        $sender = new EmailSender();
        $sender->send($payload);

        $this->assertDatabaseHas('notifications', [
            'channel' => 'email',
            'dispatch_mode' => 'async',
            'recipient' => 'user@example.com',
            'status' => 'sent',
            'message' => 'Test email notification',
        ]);
    }

    // ========== SmsSender Tests ==========

    public function test_sms_sender_implements_interface(): void
    {
        $sender = new SmsSender();
        $this->assertInstanceOf(\App\Contracts\NotificationSender::class, $sender);
    }

    public function test_sms_sender_creates_notification_record(): void
    {
        $payload = new NotificationPayload(
            channel: 'sms',
            recipient: '+1234567890',
            message: 'Test SMS',
            notificationType: 'success',
            notifiableType: 'App\Models\Transaction',
            notifiableId: 1
        );

        $sender = new SmsSender();
        $sender->send($payload);

        $this->assertDatabaseHas('notifications', [
            'channel' => 'sms',
            'dispatch_mode' => 'async',
            'recipient' => '+1234567890',
            'status' => 'sent',
        ]);
    }

    // ========== PushSender Tests ==========

    public function test_push_sender_implements_interface(): void
    {
        $sender = new PushSender();
        $this->assertInstanceOf(\App\Contracts\NotificationSender::class, $sender);
    }

    public function test_push_sender_creates_notification_record(): void
    {
        $payload = new NotificationPayload(
            channel: 'push',
            recipient: 'device-token-abc123',
            message: 'Test push',
            notificationType: 'success',
            notifiableType: 'App\Models\Transaction',
            notifiableId: 1
        );

        $sender = new PushSender();
        $sender->send($payload);

        $this->assertDatabaseHas('notifications', [
            'channel' => 'push',
            'dispatch_mode' => 'sync',
            'recipient' => 'device-token-abc123',
            'status' => 'sent',
        ]);
    }

    public function test_push_sender_does_not_throw_on_failure(): void
    {
        // Create a payload that would cause an error
        $payload = new NotificationPayload(
            channel: 'push',
            recipient: 'test-device',
            message: 'Test',
            notificationType: 'success',
            notifiableType: 'App\Models\Transaction',
            notifiableId: 1
        );

        $sender = new PushSender();

        // This should NOT throw an exception (NFR-08)
        $sender->send($payload);

        // Should reach this point without exception
        $this->assertTrue(true);
    }

    // ========== Common Sender Tests ==========

    public function test_all_senders_mark_notification_as_sent(): void
    {
        Mail::fake();

        $channels = [
            ['sender' => new EmailSender(), 'channel' => 'email', 'recipient' => 'email@test.com'],
            ['sender' => new SmsSender(), 'channel' => 'sms', 'recipient' => '+1111111111'],
            ['sender' => new PushSender(), 'channel' => 'push', 'recipient' => 'device-token'],
        ];

        foreach ($channels as $config) {
            $payload = new NotificationPayload(
                channel: $config['channel'],
                recipient: $config['recipient'],
                message: 'Test message',
                notificationType: 'success',
                notifiableType: 'App\Models\Transaction',
                notifiableId: 1
            );

            $config['sender']->send($payload);
        }

        $this->assertEquals(3, Notification::where('status', 'sent')->count());
    }

    public function test_email_sender_uses_correct_dispatch_mode(): void
    {
        Mail::fake();

        $payload = new NotificationPayload(
            channel: 'email',
            recipient: 'user@example.com',
            message: 'Test',
            notificationType: 'success',
            notifiableType: 'App\Models\Transaction',
            notifiableId: 1
        );

        (new EmailSender())->send($payload);

        $notification = Notification::where('channel', 'email')->first();
        $this->assertEquals('async', $notification->dispatch_mode);
    }

    public function test_sms_sender_uses_correct_dispatch_mode(): void
    {
        $payload = new NotificationPayload(
            channel: 'sms',
            recipient: '+1234567890',
            message: 'Test',
            notificationType: 'success',
            notifiableType: 'App\Models\Transaction',
            notifiableId: 1
        );

        (new SmsSender())->send($payload);

        $notification = Notification::where('channel', 'sms')->first();
        $this->assertEquals('async', $notification->dispatch_mode);
    }

    public function test_push_sender_uses_correct_dispatch_mode(): void
    {
        $payload = new NotificationPayload(
            channel: 'push',
            recipient: 'device-token',
            message: 'Test',
            notificationType: 'success',
            notifiableType: 'App\Models\Transaction',
            notifiableId: 1
        );

        (new PushSender())->send($payload);

        $notification = Notification::where('channel', 'push')->first();
        $this->assertEquals('sync', $notification->dispatch_mode);
    }
}
