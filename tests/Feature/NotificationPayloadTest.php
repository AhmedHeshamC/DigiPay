<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\DTO\NotificationPayload;
use InvalidArgumentException;

class NotificationPayloadTest extends TestCase
{
    public function test_creates_payload_with_required_fields(): void
    {
        $payload = new NotificationPayload(
            channel: 'email',
            recipient: 'user@example.com',
            message: 'Transaction completed successfully',
            notificationType: 'success',
            notifiableType: 'App\Models\Transaction',
            notifiableId: 1
        );

        $this->assertEquals('email', $payload->channel);
        $this->assertEquals('user@example.com', $payload->recipient);
        $this->assertEquals('Transaction completed successfully', $payload->message);
        $this->assertEquals('success', $payload->notificationType);
        $this->assertEquals('App\Models\Transaction', $payload->notifiableType);
        $this->assertEquals(1, $payload->notifiableId);
    }

    public function test_from_array_creates_payload(): void
    {
        $data = [
            'channel' => 'sms',
            'recipient' => '+1234567890',
            'message' => 'Your payment was received',
            'notification_type' => 'success',
            'notifiable_type' => 'App\Models\Transaction',
            'notifiable_id' => 42,
        ];

        $payload = NotificationPayload::fromArray($data);

        $this->assertEquals('sms', $payload->channel);
        $this->assertEquals('+1234567890', $payload->recipient);
        $this->assertEquals('Your payment was received', $payload->message);
        $this->assertEquals('success', $payload->notificationType);
        $this->assertEquals('App\Models\Transaction', $payload->notifiableType);
        $this->assertEquals(42, $payload->notifiableId);
    }

    public function test_to_array_converts_payload(): void
    {
        $payload = new NotificationPayload(
            channel: 'push',
            recipient: 'device-token-abc123',
            message: 'Payout generated',
            notificationType: 'success',
            notifiableType: 'App\Models\Payout',
            notifiableId: 5
        );

        $array = $payload->toArray();

        $this->assertEquals('push', $array['channel']);
        $this->assertEquals('device-token-abc123', $array['recipient']);
        $this->assertEquals('Payout generated', $array['message']);
        $this->assertEquals('success', $array['notification_type']);
        $this->assertEquals('App\Models\Payout', $array['notifiable_type']);
        $this->assertEquals(5, $array['notifiable_id']);
    }

    public function test_validates_channel_on_creation(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid channel: invalid');

        new NotificationPayload(
            channel: 'invalid',
            recipient: 'user@example.com',
            message: 'Test',
            notificationType: 'success',
            notifiableType: 'App\Models\Transaction',
            notifiableId: 1
        );
    }

    public function test_validates_notification_type_on_creation(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid notification type: unknown');

        new NotificationPayload(
            channel: 'email',
            recipient: 'user@example.com',
            message: 'Test',
            notificationType: 'unknown',
            notifiableType: 'App\Models\Transaction',
            notifiableId: 1
        );
    }

    public function test_determines_dispatch_mode_based_on_channel(): void
    {
        // Push is synchronous
        $pushPayload = new NotificationPayload(
            channel: 'push',
            recipient: 'device-token',
            message: 'Test',
            notificationType: 'success',
            notifiableType: 'App\Models\Transaction',
            notifiableId: 1
        );
        $this->assertEquals('sync', $pushPayload->dispatchMode);

        // Email is asynchronous
        $emailPayload = new NotificationPayload(
            channel: 'email',
            recipient: 'user@example.com',
            message: 'Test',
            notificationType: 'success',
            notifiableType: 'App\Models\Transaction',
            notifiableId: 1
        );
        $this->assertEquals('async', $emailPayload->dispatchMode);

        // SMS is asynchronous
        $smsPayload = new NotificationPayload(
            channel: 'sms',
            recipient: '+1234567890',
            message: 'Test',
            notificationType: 'success',
            notifiableType: 'App\Models\Transaction',
            notifiableId: 1
        );
        $this->assertEquals('async', $smsPayload->dispatchMode);
    }

    public function test_is_sync_returns_true_for_push(): void
    {
        $payload = new NotificationPayload(
            channel: 'push',
            recipient: 'device-token',
            message: 'Test',
            notificationType: 'success',
            notifiableType: 'App\Models\Transaction',
            notifiableId: 1
        );

        $this->assertTrue($payload->isSync());
        $this->assertFalse($payload->isAsync());
    }

    public function test_is_async_returns_true_for_email_and_sms(): void
    {
        $emailPayload = new NotificationPayload(
            channel: 'email',
            recipient: 'user@example.com',
            message: 'Test',
            notificationType: 'success',
            notifiableType: 'App\Models\Transaction',
            notifiableId: 1
        );

        $this->assertTrue($emailPayload->isAsync());
        $this->assertFalse($emailPayload->isSync());

        $smsPayload = new NotificationPayload(
            channel: 'sms',
            recipient: '+1234567890',
            message: 'Test',
            notificationType: 'success',
            notifiableType: 'App\Models\Transaction',
            notifiableId: 1
        );

        $this->assertTrue($smsPayload->isAsync());
        $this->assertFalse($smsPayload->isSync());
    }
}
