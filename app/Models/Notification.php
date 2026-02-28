<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Notification extends Model
{
    protected $fillable = [
        'channel',
        'dispatch_mode',
        'recipient',
        'notifiable_type',
        'notifiable_id',
        'status',
        'message',
        'error_message',
        'sent_at',
    ];

    protected $casts = [
        'sent_at' => 'datetime',
    ];

    /**
     * Get the parent notifiable model (Transaction, Payout, etc.).
     */
    public function notifiable()
    {
        return $this->morphTo();
    }

    /**
     * Mark notification as sent.
     */
    public function markAsSent(): void
    {
        $this->update([
            'status' => 'sent',
            'sent_at' => now(),
            'error_message' => null,
        ]);
    }

    /**
     * Mark notification as failed.
     */
    public function markAsFailed(string $errorMessage): void
    {
        $this->update([
            'status' => 'failed',
            'error_message' => $errorMessage,
        ]);
    }

    /**
     * Scope for pending notifications.
     */
    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    /**
     * Scope for failed notifications.
     */
    public function scopeFailed($query)
    {
        return $query->where('status', 'failed');
    }

    /**
     * Scope for sent notifications.
     */
    public function scopeSent($query)
    {
        return $query->where('status', 'sent');
    }

    /**
     * Check if notification is synchronous (push).
     */
    public function isSync(): bool
    {
        return $this->dispatch_mode === 'sync';
    }

    /**
     * Check if notification is asynchronous (email, sms).
     */
    public function isAsync(): bool
    {
        return $this->dispatch_mode === 'async';
    }
}
