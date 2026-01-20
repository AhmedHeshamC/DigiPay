<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use App\Models\WebhookCall;
use Tests\TestCase;

class WebhookCallsMigrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_webhook_calls_table_exists()
    {
        $this->assertTrue(Schema::hasTable('webhook_calls'));
    }

    public function test_webhook_calls_table_has_all_required_columns()
    {
        $this->assertTrue(Schema::hasColumn('webhook_calls', 'id'));
        $this->assertTrue(Schema::hasColumn('webhook_calls', 'bank_provider'));
        $this->assertTrue(Schema::hasColumn('webhook_calls', 'payload'));
        $this->assertTrue(Schema::hasColumn('webhook_calls', 'status'));
        $this->assertTrue(Schema::hasColumn('webhook_calls', 'error_message'));
        $this->assertTrue(Schema::hasColumn('webhook_calls', 'created_at'));
        $this->assertTrue(Schema::hasColumn('webhook_calls', 'updated_at'));
    }

    public function test_webhook_call_default_status_is_pending()
    {
        $webhookCall = WebhookCall::create([
            'bank_provider' => 'paytech',
            'payload' => 'raw webhook data',
        ]);

        $this->assertEquals('pending', $webhookCall->status);
    }

    public function test_webhook_call_payload_can_store_long_text()
    {
        $longPayload = str_repeat('x', 100000); // Test longText capacity

        $webhookCall = WebhookCall::create([
            'bank_provider' => 'acme',
            'payload' => $longPayload,
        ]);

        $this->assertEquals($longPayload, $webhookCall->payload);
    }
}
