<?php

namespace App\Services\Notifications;

use App\Contracts\NotificationSender;
use App\DTO\NotificationPayload;
use App\Models\Notification;
use Illuminate\Support\Facades\Log;

class SmsSender implements NotificationSender
{
    /**
     * Send an SMS notification.
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
                'channel' => 'sms',
                'dispatch_mode' => 'async',
                'recipient' => $payload->recipient,
                'notifiable_type' => $payload->notifiableType,
                'notifiable_id' => $payload->notifiableId,
                'status' => 'pending',
                'message' => $payload->message,
            ]);

            // Simulate SMS gateway integration
            // In production, this would integrate with Twilio, Vonage, etc.
            $this->sendViaGateway($payload);

            // Mark as sent
            $notification->markAsSent();

            Log::info('SMS notification sent', [
                'recipient' => $payload->recipient,
                'type' => $payload->notificationType,
            ]);

        } catch (\Exception $e) {
            Log::error('SMS notification failed', [
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
     * Send SMS via gateway (placeholder for actual implementation).
     * In production, integrate with Twilio, Vonage, or similar.
     */
    private function sendViaGateway(NotificationPayload $payload): void
    {
        // Placeholder: In production, this would call an SMS gateway API
        // Example: Twilio::message($payload->recipient, $payload->message)

        // For now, we simulate successful sending
        // This allows tests to pass without actual SMS gateway credentials
    }
}
