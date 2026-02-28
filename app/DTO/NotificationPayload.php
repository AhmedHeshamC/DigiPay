<?php

namespace App\DTO;

use InvalidArgumentException;

readonly class NotificationPayload
{
    private const VALID_CHANNELS = ['email', 'sms', 'push'];
    private const VALID_TYPES = ['success', 'failure'];
    private const SYNC_CHANNELS = ['push'];

    public readonly string $dispatchMode;

    /**
     * @param string $channel Notification channel (email, sms, push)
     * @param string $recipient Recipient identifier (email, phone, device token)
     * @param string $message Notification message body
     * @param string $notificationType Type of notification (success, failure)
     * @param string $notifiableType Model class of the related entity
     * @param int $notifiableId ID of the related entity
     */
    public function __construct(
        public readonly string $channel,
        public readonly string $recipient,
        public readonly string $message,
        public readonly string $notificationType,
        public readonly string $notifiableType,
        public readonly int $notifiableId
    ) {
        $this->validateChannel($channel);
        $this->validateNotificationType($notificationType);
        $this->dispatchMode = $this->determineDispatchMode($channel);
    }

    /**
     * Create NotificationPayload from array.
     *
     * @param array<string, mixed> $data
     * @return self
     */
    public static function fromArray(array $data): self
    {
        return new self(
            channel: $data['channel'],
            recipient: $data['recipient'],
            message: $data['message'],
            notificationType: $data['notification_type'],
            notifiableType: $data['notifiable_type'],
            notifiableId: (int) $data['notifiable_id']
        );
    }

    /**
     * Convert NotificationPayload to array.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'channel' => $this->channel,
            'recipient' => $this->recipient,
            'message' => $this->message,
            'notification_type' => $this->notificationType,
            'notifiable_type' => $this->notifiableType,
            'notifiable_id' => $this->notifiableId,
            'dispatch_mode' => $this->dispatchMode,
        ];
    }

    /**
     * Check if notification should be sent synchronously.
     */
    public function isSync(): bool
    {
        return $this->dispatchMode === 'sync';
    }

    /**
     * Check if notification should be sent asynchronously.
     */
    public function isAsync(): bool
    {
        return $this->dispatchMode === 'async';
    }

    /**
     * Validate the channel value.
     *
     * @throws InvalidArgumentException
     */
    private function validateChannel(string $channel): void
    {
        if (!in_array($channel, self::VALID_CHANNELS, true)) {
            throw new InvalidArgumentException("Invalid channel: {$channel}");
        }
    }

    /**
     * Validate the notification type value.
     *
     * @throws InvalidArgumentException
     */
    private function validateNotificationType(string $type): void
    {
        if (!in_array($type, self::VALID_TYPES, true)) {
            throw new InvalidArgumentException("Invalid notification type: {$type}");
        }
    }

    /**
     * Determine dispatch mode based on channel.
     */
    private function determineDispatchMode(string $channel): string
    {
        return in_array($channel, self::SYNC_CHANNELS, true) ? 'sync' : 'async';
    }

    /**
     * Create a new instance with a different message.
     */
    public function withMessage(string $message): self
    {
        return new self(
            channel: $this->channel,
            recipient: $this->recipient,
            message: $message,
            notificationType: $this->notificationType,
            notifiableType: $this->notifiableType,
            notifiableId: $this->notifiableId
        );
    }
}
