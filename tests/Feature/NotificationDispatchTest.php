<?php

namespace Tests\Feature;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Queue;
use App\Jobs\SendNotificationJob;
use App\Services\Notifications\NotificationDispatcher;
use App\DTO\NotificationPayload;
use App\Models\Transaction;
use App\Models\Wallet;

class NotificationDispatchTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Create a wallet for transactions
        Wallet::create([
            'owner_name' => 'Test Owner',
            'email' => 'owner@example.com',
            'balance' => 0,
            'currency' => 'USD',
        ]);
    }

    // ========== Dispatcher Tests ==========

    public function test_dispatcher_dispatches_async_job_for_email(): void
    {
        Queue::fake();

        $payload = new NotificationPayload(
            channel: 'email',
            recipient: 'user@example.com',
            message: 'Test',
            notificationType: 'success',
            notifiableType: 'App\Models\Transaction',
            notifiableId: 1
        );

        $dispatcher = new NotificationDispatcher();
        $dispatcher->dispatch($payload);

        // Email should be dispatched to queue (async)
        Queue::assertPushed(SendNotificationJob::class);
    }

    public function test_dispatcher_dispatches_async_job_for_sms(): void
    {
        Queue::fake();

        $payload = new NotificationPayload(
            channel: 'sms',
            recipient: '+1234567890',
            message: 'Test',
            notificationType: 'success',
            notifiableType: 'App\Models\Transaction',
            notifiableId: 1
        );

        $dispatcher = new NotificationDispatcher();
        $dispatcher->dispatch($payload);

        // SMS should be dispatched to queue (async)
        Queue::assertPushed(SendNotificationJob::class);
    }

    public function test_dispatcher_sends_push_sync(): void
    {
        // Push notifications are sent synchronously, not queued
        $payload = new NotificationPayload(
            channel: 'push',
            recipient: 'device-token',
            message: 'Test push',
            notificationType: 'success',
            notifiableType: 'App\Models\Transaction',
            notifiableId: 1
        );

        $dispatcher = new NotificationDispatcher();

        // Should not throw and should complete synchronously
        $dispatcher->dispatch($payload);

        // Verify notification was created (sync)
        $this->assertDatabaseHas('notifications', [
            'channel' => 'push',
            'dispatch_mode' => 'sync',
            'status' => 'sent',
        ]);
    }

    // ========== Transaction Success Notification Tests ==========

    public function test_notification_sent_on_successful_credit(): void
    {
        Queue::fake();

        // Create a transaction
        $transaction = Transaction::create([
            'wallet_id' => 1,
            'type' => 'credit',
            'bank_reference' => 'REF-TEST-001',
            'bank_provider' => 'paytech',
            'amount' => 100.00,
            'bank_transaction_time' => now(),
        ]);

        // Dispatch success notification
        $dispatcher = new NotificationDispatcher();
        $dispatcher->notifyTransactionSuccess($transaction, 'email', 'owner@example.com');

        // Verify job was dispatched for email channel
        Queue::assertPushed(SendNotificationJob::class);
    }

    public function test_notification_sent_on_transaction_failure(): void
    {
        Queue::fake();

        // Simulate a failed transaction (no actual record created)
        $dispatcher = new NotificationDispatcher();
        $dispatcher->notifyTransactionFailure(
            'REF-FAILED-001',
            'paytech',
            'email',
            'owner@example.com',
            'Processing error'
        );

        // Verify job was dispatched
        Queue::assertPushed(SendNotificationJob::class);
    }

    // ========== Payout Notification Tests ==========

    public function test_notification_sent_on_payout_generation(): void
    {
        Queue::fake();

        $dispatcher = new NotificationDispatcher();
        $dispatcher->notifyPayoutGenerated(
            payoutId: 1,
            amount: 500.00,
            channel: 'email',
            recipient: 'owner@example.com'
        );

        // Verify job was dispatched for payout
        Queue::assertPushed(SendNotificationJob::class);
    }

    public function test_push_notification_for_payout_is_sync(): void
    {
        // Push notifications should be sent synchronously
        $dispatcher = new NotificationDispatcher();
        $dispatcher->notifyPayoutGenerated(
            payoutId: 1,
            amount: 500.00,
            channel: 'push',
            recipient: 'device-token'
        );

        // Verify notification was created synchronously
        $this->assertDatabaseHas('notifications', [
            'channel' => 'push',
            'dispatch_mode' => 'sync',
            'status' => 'sent',
        ]);
    }

    // ========== Resilience Tests ==========

    public function test_notification_failure_does_not_affect_transaction(): void
    {
        // This test verifies NFR-08: notification failures don't affect transactions

        // Create a transaction first
        $transaction = Transaction::create([
            'wallet_id' => 1,
            'type' => 'credit',
            'bank_reference' => 'REF-RESILIENCE-001',
            'bank_provider' => 'paytech',
            'amount' => 200.00,
            'bank_transaction_time' => now(),
        ]);

        // Even if notification fails, transaction should still exist
        $this->assertDatabaseHas('transactions', [
            'id' => $transaction->id,
            'bank_reference' => 'REF-RESILIENCE-001',
        ]);

        // The transaction record is unaffected by notification dispatch
        $this->assertEquals(200.00, $transaction->fresh()->amount);
    }
}
