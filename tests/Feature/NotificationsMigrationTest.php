<?php

namespace Tests\Feature;

use Tests\TestCase;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;

class NotificationsMigrationTest extends TestCase
{
    use RefreshDatabase;
    /**
     * Test that notifications table has all required columns.
     */
    public function test_notifications_table_has_required_columns(): void
    {
        $this->assertTrue(Schema::hasTable('notifications'));

        $requiredColumns = [
            'id',
            'channel',
            'dispatch_mode',
            'recipient',
            'notifiable_type',
            'notifiable_id',
            'status',
            'message',
            'error_message',
            'sent_at',
            'created_at',
            'updated_at',
        ];

        foreach ($requiredColumns as $column) {
            $this->assertTrue(
                Schema::hasColumn('notifications', $column),
                "Column '{$column}' is missing from notifications table"
            );
        }
    }

    /**
     * Test that channel column accepts valid enum values.
     */
    public function test_channel_column_accepts_valid_values(): void
    {
        $validChannels = ['email', 'sms', 'push'];

        foreach ($validChannels as $channel) {
            $notification = \App\Models\Notification::create([
                'channel' => $channel,
                'dispatch_mode' => $channel === 'push' ? 'sync' : 'async',
                'recipient' => 'test@example.com',
                'notifiable_type' => 'App\Models\Transaction',
                'notifiable_id' => 1,
                'status' => 'pending',
                'message' => 'Test notification',
            ]);

            $this->assertEquals($channel, $notification->channel);
        }
    }

    /**
     * Test that status column accepts valid enum values.
     */
    public function test_status_column_accepts_valid_values(): void
    {
        $validStatuses = ['pending', 'sent', 'failed'];

        foreach ($validStatuses as $status) {
            $notification = \App\Models\Notification::create([
                'channel' => 'email',
                'dispatch_mode' => 'async',
                'recipient' => 'test@example.com',
                'notifiable_type' => 'App\Models\Transaction',
                'notifiable_id' => 1,
                'status' => $status,
                'message' => 'Test notification',
            ]);

            $this->assertEquals($status, $notification->status);
        }
    }

    /**
     * Test that dispatch_mode column accepts valid enum values.
     */
    public function test_dispatch_mode_column_accepts_valid_values(): void
    {
        $validModes = ['sync', 'async'];

        foreach ($validModes as $mode) {
            $notification = \App\Models\Notification::create([
                'channel' => $mode === 'sync' ? 'push' : 'email',
                'dispatch_mode' => $mode,
                'recipient' => 'test@example.com',
                'notifiable_type' => 'App\Models\Transaction',
                'notifiable_id' => 1,
                'status' => 'pending',
                'message' => 'Test notification',
            ]);

            $this->assertEquals($mode, $notification->dispatch_mode);
        }
    }

    /**
     * Test that error_message and sent_at are nullable.
     */
    public function test_nullable_columns(): void
    {
        $notification = \App\Models\Notification::create([
            'channel' => 'email',
            'dispatch_mode' => 'async',
            'recipient' => 'test@example.com',
            'notifiable_type' => 'App\Models\Transaction',
            'notifiable_id' => 1,
            'status' => 'pending',
            'message' => 'Test notification',
        ]);

        $this->assertNull($notification->error_message);
        $this->assertNull($notification->sent_at);
    }
}
