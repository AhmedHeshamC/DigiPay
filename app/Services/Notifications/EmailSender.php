<?php

namespace App\Services\Notifications;

use App\Contracts\NotificationSender;
use App\DTO\NotificationPayload;
use App\Models\Notification;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class EmailSender implements NotificationSender
{
    /**
     * Send an email notification.
     *
     * @param NotificationPayload $payload
     * @return void
     * @throws \Exception If sending fails
     */
    public function send(NotificationPayload $payload): void
    {
        try {
            // Create notification record
            $notification = Notification::create([
                'channel' => 'email',
                'dispatch_mode' => 'async',
                'recipient' => $payload->recipient,
                'notifiable_type' => $payload->notifiableType,
                'notifiable_id' => $payload->notifiableId,
                'status' => 'pending',
                'message' => $payload->message,
            ]);

            // Send email using Laravel's mail system
            Mail::raw($payload->message, function ($message) use ($payload) {
                $message->to($payload->recipient)
                    ->subject($this->getSubject($payload->notificationType));
            });

            // Mark as sent
            $notification->markAsSent();

            Log::info('Email notification sent', [
                'recipient' => $payload->recipient,
                'type' => $payload->notificationType,
            ]);

        } catch (\Exception $e) {
            Log::error('Email notification failed', [
                'recipient' => $payload->recipient,
                'error' => $e->getMessage(),
            ]);

            if (isset($notification)) {
                $notification->markAsFailed($e->getMessage());
            }

            throw $e;
        }
    }

    /**
     * Get email subject based on notification type.
     */
    private function getSubject(string $type): string
    {
        return match ($type) {
            'success' => 'Transaction Successful',
            'failure' => 'Transaction Failed',
            default => 'Notification',
        };
    }
}
