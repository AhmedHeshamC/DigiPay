<?php

namespace App\Services\Notifications;

use App\Contracts\NotificationSender;
use App\DTO\NotificationPayload;
use App\Models\Notification;
use Illuminate\Support\Facades\Log;

class PushSender implements NotificationSender
{
    /**
     * Send a push notification (synchronous).
     *
     * @param NotificationPayload $payload
     * @return void
     */
    public function send(NotificationPayload $payload): void
    {
        try {
            // Create notification record
            $notification = Notification::create([
                'channel' => 'push',
                'dispatch_mode' => 'sync',
                'recipient' => $payload->recipient,
                'notifiable_type' => $payload->notifiableType,
                'notifiable_id' => $payload->notifiableId,
                'status' => 'pending',
                'message' => $payload->message,
            ]);

            // Send push notification via FCM/APNs
            // In production, this would integrate with Firebase Cloud Messaging
            $this->sendViaPushService($payload);

            // Mark as sent
            $notification->markAsSent();

            Log::info('Push notification sent', [
                'recipient' => $payload->recipient,
                'type' => $payload->notificationType,
            ]);

        } catch (\Exception $e) {
            // Per NFR-08: Push failures are caught, logged, and surfaced as warnings
            // They must NOT throw unhandled exceptions
            Log::warning('Push notification failed (non-blocking)', [
                'recipient' => $payload->recipient,
                'error' => $e->getMessage(),
            ]);

            if (isset($notification)) {
                $notification->markAsFailed($e->getMessage());
            }

            // Do NOT re-throw - push failures should not affect transaction outcome
        }
    }

    /**
     * Send push notification via FCM/APNs (placeholder for actual implementation).
     * In production, integrate with Firebase Cloud Messaging or Apple Push Notification service.
     */
    private function sendViaPushService(NotificationPayload $payload): void
    {
        // Placeholder: In production, this would call FCM/APNs API
        // Example: FirebaseMessaging::send($payload->recipient, $payload->message)

        // For now, we simulate successful sending
        // This allows tests to pass without actual FCM credentials
    }
}
