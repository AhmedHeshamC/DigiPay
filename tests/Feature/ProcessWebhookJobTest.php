<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Jobs\ProcessWebhookJob;
use App\Models\WebhookCall;
use App\Models\Transaction;
use App\Models\Wallet;
use Tests\TestCase;

class ProcessWebhookJobTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(); // Create default wallet
    }

    public function test_processes_paytech_webhook_and_creates_transaction()
    {
        $webhookCall = WebhookCall::create([
            'bank_provider' => 'paytech',
            'payload' => '20250615,50#REF123',
        ]);

        $job = new ProcessWebhookJob($webhookCall->id);
        $job->handle();

        $this->assertDatabaseHas('transactions', [
            'bank_reference' => 'REF123',
            'bank_provider' => 'paytech',
            'amount' => 50.00,
        ]);

        $webhookCall->refresh();
        $this->assertEquals('processed', $webhookCall->status);
    }

    public function test_processes_acme_webhook_and_creates_transaction()
    {
        $webhookCall = WebhookCall::create([
            'bank_provider' => 'acme',
            'payload' => '20250615//75.50//ACME-REF-001',
        ]);

        $job = new ProcessWebhookJob($webhookCall->id);
        $job->handle();

        $this->assertDatabaseHas('transactions', [
            'bank_reference' => 'ACME-REF-001',
            'bank_provider' => 'acme',
            'amount' => 75.50,
        ]);

        $webhookCall->refresh();
        $this->assertEquals('processed', $webhookCall->status);
    }

    public function test_handles_duplicate_transactions_idempotently()
    {
        // Create first transaction
        Transaction::create([
            'wallet_id' => 1,
            'type' => 'credit',
            'bank_reference' => 'REF123',
            'bank_provider' => 'paytech',
            'amount' => 50.00,
            'bank_transaction_time' => now(),
        ]);

        // Try to process webhook with same reference
        $webhookCall = WebhookCall::create([
            'bank_provider' => 'paytech',
            'payload' => '20250615,50#REF123',
        ]);

        $job = new ProcessWebhookJob($webhookCall->id);
        $job->handle();

        // Should still only have 1 transaction
        $this->assertEquals(1, Transaction::where('bank_reference', 'REF123')->count());

        $webhookCall->refresh();
        $this->assertEquals('processed', $webhookCall->status);
    }

    public function test_handles_multi_line_bulk_payload()
    {
        $webhookCall = WebhookCall::create([
            'bank_provider' => 'paytech',
            'payload' => "20250615,50#REF1\n20250615,75#REF2\n20250615,100#REF3",
        ]);

        $job = new ProcessWebhookJob($webhookCall->id);
        $job->handle();

        $this->assertEquals(3, Transaction::count());
        $this->assertEquals(50.00, Transaction::where('bank_reference', 'REF1')->first()->amount);
        $this->assertEquals(75.00, Transaction::where('bank_reference', 'REF2')->first()->amount);
        $this->assertEquals(100.00, Transaction::where('bank_reference', 'REF3')->first()->amount);
    }

    public function test_marks_webhook_as_failed_on_exception()
    {
        $webhookCall = WebhookCall::create([
            'bank_provider' => 'unknown',
            'payload' => 'invalid payload',
        ]);

        $job = new ProcessWebhookJob($webhookCall->id);
        $job->handle();

        $webhookCall->refresh();
        $this->assertEquals('failed', $webhookCall->status);
        $this->assertNotNull($webhookCall->error_message);
    }

    public function test_updates_wallet_balance_on_credit()
    {
        $webhookCall = WebhookCall::create([
            'bank_provider' => 'paytech',
            'payload' => '20250615,100#REF123',
        ]);

        $job = new ProcessWebhookJob($webhookCall->id);
        $job->handle();

        $wallet = Wallet::find(1);
        $this->assertEquals(100.00, $wallet->balance);
    }
}
