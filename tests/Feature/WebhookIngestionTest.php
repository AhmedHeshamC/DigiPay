<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Models\WebhookCall;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class WebhookIngestionTest extends TestCase
{
    use RefreshDatabase;

    public function test_accepts_paytech_webhook_and_returns_202()
    {
        Queue::fake(); // Prevent job from running to check 'pending' status

        $payload = '20250615,50#REF123';

        $response = $this->call('POST', '/api/v1/webhooks/paytech', [], [], [], [
            'CONTENT_TYPE' => 'text/plain',
        ], $payload);

        $response->assertStatus(202);

        $this->assertDatabaseHas('webhook_calls', [
            'bank_provider' => 'paytech',
            'payload' => $payload,
            'status' => 'pending',
        ]);

        Queue::assertPushed(\App\Jobs\ProcessWebhookJob::class);
    }

    public function test_accepts_acme_webhook_and_returns_202()
    {
        Queue::fake();

        $payload = '20250615//75.50//ACME-REF-001';

        $response = $this->call('POST', '/api/v1/webhooks/acme', [], [], [], [
            'CONTENT_TYPE' => 'text/plain',
        ], $payload);

        $response->assertStatus(202);

        $this->assertDatabaseHas('webhook_calls', [
            'bank_provider' => 'acme',
            'payload' => $payload,
            'status' => 'pending',
        ]);
    }

    public function test_dispatches_job_for_processing()
    {
        Queue::fake();

        $payload = '20250615,50#REF123';

        $this->call('POST', '/api/v1/webhooks/paytech', [], [], [], [
            'CONTENT_TYPE' => 'text/plain',
        ], $payload);

        Queue::assertPushed(\App\Jobs\ProcessWebhookJob::class);
    }

    public function test_rejects_unknown_bank_provider()
    {
        $payload = 'some payload';

        $response = $this->call('POST', '/api/v1/webhooks/unknown', [], [], [], [
            'CONTENT_TYPE' => 'text/plain',
        ], $payload);

        $response->assertStatus(422);
    }

    public function test_validates_only_paytech_and_acme_banks()
    {
        Queue::fake();

        // Valid banks
        $this->call('POST', '/api/v1/webhooks/paytech', [], [], [], [
            'CONTENT_TYPE' => 'text/plain',
        ], 'payload')->assertStatus(202);
        $this->call('POST', '/api/v1/webhooks/acme', [], [], [], [
            'CONTENT_TYPE' => 'text/plain',
        ], 'payload')->assertStatus(202);

        // Invalid banks
        $this->call('POST', '/api/v1/webhooks/invalid', [], [], [], [
            'CONTENT_TYPE' => 'text/plain',
        ], 'payload')->assertStatus(422);
        $this->call('POST', '/api/v1/webhooks/badbank', [], [], [], [
            'CONTENT_TYPE' => 'text/plain',
        ], 'payload')->assertStatus(422);
    }

    public function test_returns_202_even_with_empty_payload()
    {
        Queue::fake();

        $response = $this->call('POST', '/api/v1/webhooks/paytech', [], [], [], [
            'CONTENT_TYPE' => 'text/plain',
        ], '');

        $response->assertStatus(202);
    }
}
