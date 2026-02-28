<?php

namespace App\Jobs;

use App\DTO\NotificationPayload;
use App\Services\Notifications\NotificationFactory;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Exception;

class SendNotificationJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * The number of times the job may be attempted.
     *
     * @var int
     */
    public int $tries = 3;

    /**
     * The maximum number of seconds the job can run.
     *
     * @var int
     */
    public int $timeout = 60;

    /**
     * Create a new job instance.
     */
    public function __construct(
        private readonly NotificationPayload $payload
    ) {}

    /**
     * Execute the job.
     */
    public function handle(NotificationFactory $factory): void
    {
        try {
            // Use factory to get the appropriate sender
            $sender = $factory->make($this->payload->channel);

            // Send the notification
            $sender->send($this->payload);

        } catch (Exception $e) {
            // Log the error but allow retry
            logger()->error('SendNotificationJob failed', [
                'channel' => $this->payload->channel,
                'recipient' => $this->payload->recipient,
                'attempt' => $this->attempts(),
                'error' => $e->getMessage(),
            ]);

            // Re-throw to trigger retry mechanism (up to 3 times per NFR-08)
            if ($this->attempts() < $this->tries) {
                throw $e;
            }

            // After max retries, the job will fail but won't affect transaction
            // The sender already created a notification record with 'failed' status
        }
    }

    /**
     * Get the tags that should be assigned to the job.
     *
     * @return array<int, string>
     */
    public function tags(): array
    {
        return [
            'notification',
            "channel:{$this->payload->channel}",
            "notifiable:{$this->payload->notifiableType}:{$this->payload->notifiableId}",
        ];
    }

    /**
     * Handle a job failure.
     */
    public function failed(Exception $exception): void
    {
        logger()->error('SendNotificationJob failed permanently', [
            'channel' => $this->payload->channel,
            'recipient' => $this->payload->recipient,
            'error' => $exception->getMessage(),
        ]);
    }
}
