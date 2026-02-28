<?php

namespace App\Services\Notifications;

use App\DTO\NotificationPayload;
use App\Jobs\SendNotificationJob;
use App\Models\Transaction;
use Illuminate\Support\Facades\Log;

class NotificationDispatcher
{
    public function __construct(
        private readonly NotificationFactory $factory = new NotificationFactory()
    ) {}

    /**
     * Dispatch a notification based on its channel type.
     *
     * Email and SMS are dispatched asynchronously via queue.
     * Push notifications are sent synchronously.
     *
     * @param NotificationPayload $payload
     * @return void
     */
    public function dispatch(NotificationPayload $payload): void
    {
        try {
            if ($payload->isSync()) {
                // Push notifications: send synchronously
                $sender = $this->factory->make($payload->channel);
                $sender->send($payload);
            } else {
                // Email/SMS: dispatch to queue for async processing
                SendNotificationJob::dispatch($payload);
            }
        } catch (\Exception $e) {
            // Per NFR-08: notification failures must not affect transaction outcome
            Log::warning('Notification dispatch failed (non-blocking)', [
                'channel' => $payload->channel,
                'recipient' => $payload->recipient,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Send notification for successful transaction credit.
     *
     * @param Transaction $transaction
     * @param string $channel Notification channel (email, sms, push)
     * @param string $recipient Recipient identifier
     * @return void
     */
    public function notifyTransactionSuccess(
        Transaction $transaction,
        string $channel,
        string $recipient
    ): void {
        $payload = new NotificationPayload(
            channel: $channel,
            recipient: $recipient,
            message: sprintf(
                'Transaction of %s %s credited successfully. Reference: %s',
                number_format($transaction->amount, 2),
                'USD', // Default currency
                $transaction->bank_reference
            ),
            notificationType: 'success',
            notifiableType: get_class($transaction),
            notifiableId: $transaction->id
        );

        $this->dispatch($payload);
    }

    /**
     * Send notification for failed transaction.
     *
     * @param string $reference Transaction reference
     * @param string $provider Bank provider
     * @param string $channel Notification channel
     * @param string $recipient Recipient identifier
     * @param string $errorMessage Error description
     * @return void
     */
    public function notifyTransactionFailure(
        string $reference,
        string $provider,
        string $channel,
        string $recipient,
        string $errorMessage
    ): void {
        $payload = new NotificationPayload(
            channel: $channel,
            recipient: $recipient,
            message: sprintf(
                'Transaction failed for reference %s from %s. Error: %s',
                $reference,
                $provider,
                $errorMessage
            ),
            notificationType: 'failure',
            notifiableType: 'App\Models\Transaction',
            notifiableId: 0 // No transaction was created
        );

        $this->dispatch($payload);
    }

    /**
     * Send notification for successful payout XML generation.
     *
     * @param int $payoutId Payout identifier
     * @param float $amount Payout amount
     * @param string $channel Notification channel
     * @param string $recipient Recipient identifier
     * @return void
     */
    public function notifyPayoutGenerated(
        int $payoutId,
        float $amount,
        string $channel,
        string $recipient
    ): void {
        $payload = new NotificationPayload(
            channel: $channel,
            recipient: $recipient,
            message: sprintf(
                'Payout of %s has been generated successfully. Payout ID: %d',
                number_format($amount, 2),
                $payoutId
            ),
            notificationType: 'success',
            notifiableType: 'App\Models\Payout',
            notifiableId: $payoutId
        );

        $this->dispatch($payload);
    }
}
