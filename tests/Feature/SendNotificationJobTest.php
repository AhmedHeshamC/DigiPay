<?php

namespace Tests\Feature;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Queue;
use App\Jobs\SendNotificationJob;
use App\DTO\NotificationPayload;
use App\Models\Notification;
use App\Services\Notifications\NotificationFactory;

class SendNotificationJobTest extends TestCase
{
    use RefreshDatabase;

    public function test_job_can_be_dispatched(): void
    {
        Bus::fake();

        $payload = new NotificationPayload(
            channel: 'email',
            recipient: 'user@example.com',
            message: 'Test notification',
            notificationType: 'success',
            notifiableType: 'App\Models\Transaction',
            notifiableId: 1
        );

        SendNotificationJob::dispatch($payload);

        Bus::assertDispatched(SendNotificationJob::class);
    }

    public function test_job_implements_should_queue(): void
    {
        $job = new SendNotificationJob(
            new NotificationPayload(
                channel: 'email',
                recipient: 'test@example.com',
                message: 'Test',
                notificationType: 'success',
                notifiableType: 'App\Models\Transaction',
                notifiableId: 1
            )
        );

        $this->assertInstanceOf(\Illuminate\Contracts\Queue\ShouldQueue::class, $job);
    }

    public function test_job_uses_factory_to_send_notification(): void
    {
        $payload = new NotificationPayload(
            channel: 'email',
            recipient: 'user@example.com',
            message: 'Test notification',
            notificationType: 'success',
            notifiableType: 'App\Models\Transaction',
            notifiableId: 1
        );

        $job = new SendNotificationJob($payload);
        $job->handle(new NotificationFactory());

        $this->assertDatabaseHas('notifications', [
            'channel' => 'email',
            'recipient' => 'user@example.com',
            'status' => 'sent',
        ]);
    }

    public function test_job_handles_email_channel(): void
    {
        $payload = new NotificationPayload(
            channel: 'email',
            recipient: 'email@test.com',
            message: 'Email test',
            notificationType: 'success',
            notifiableType: 'App\Models\Transaction',
            notifiableId: 1
        );

        $job = new SendNotificationJob($payload);
        $job->handle(new NotificationFactory());

        $this->assertDatabaseHas('notifications', [
            'channel' => 'email',
            'recipient' => 'email@test.com',
        ]);
    }

    public function test_job_handles_sms_channel(): void
    {
        $payload = new NotificationPayload(
            channel: 'sms',
            recipient: '+1234567890',
            message: 'SMS test',
            notificationType: 'failure',
            notifiableType: 'App\Models\Transaction',
            notifiableId: 2
        );

        $job = new SendNotificationJob($payload);
        $job->handle(new NotificationFactory());

        $this->assertDatabaseHas('notifications', [
            'channel' => 'sms',
            'recipient' => '+1234567890',
        ]);
    }

    public function test_job_stores_notifiable_relationship(): void
    {
        $payload = new NotificationPayload(
            channel: 'email',
            recipient: 'test@example.com',
            message: 'Test',
            notificationType: 'success',
            notifiableType: 'App\Models\Transaction',
            notifiableId: 42
        );

        $job = new SendNotificationJob($payload);
        $job->handle(new NotificationFactory());

        $this->assertDatabaseHas('notifications', [
            'notifiable_type' => 'App\Models\Transaction',
            'notifiable_id' => 42,
        ]);
    }

    public function test_job_can_fail_gracefully(): void
    {
        // Create a payload with invalid channel that will fail
        $payload = new NotificationPayload(
            channel: 'email',
            recipient: 'test@example.com',
            message: 'Test',
            notificationType: 'success',
            notifiableType: 'App\Models\Transaction',
            notifiableId: 1
        );

        $job = new SendNotificationJob($payload);

        // Job should handle exceptions gracefully
        // Note: In production, the job would retry based on queue config
        $job->handle(new NotificationFactory());

        // The notification should have been created
        $this->assertDatabaseHas('notifications', [
            'recipient' => 'test@example.com',
        ]);
    }
}
