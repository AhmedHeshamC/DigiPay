<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Jobs\ProcessWebhookJob;
use App\Models\Transaction;
use App\Models\Wallet;
use App\Services\Notifications\NotificationDispatcher;
use Tests\TestCase;

class BulkPerformanceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(); // Create default wallet
    }

    public function test_processes_1000_paytech_transactions_efficiently()
    {
        // Generate 1000 transaction lines
        $lines = [];
        for ($i = 1; $i <= 1000; $i++) {
            $lines[] = "20250615,{$i}.00#REF{$i}";
        }
        $payload = implode("\n", $lines);

        $webhookCall = \App\Models\WebhookCall::create([
            'bank_provider' => 'paytech',
            'payload' => $payload,
        ]);

        $startTime = microtime(true);

        $job = new ProcessWebhookJob($webhookCall->id);
        $job->handle(new NotificationDispatcher());

        $endTime = microtime(true);
        $duration = $endTime - $startTime;

        // Verify all 1000 transactions created
        $this->assertEquals(1000, Transaction::count());

        // Verify wallet balance updated correctly
        $wallet = Wallet::find(1);
        $expectedBalance = 1000 * 1001 / 2; // Sum of 1.00 to 1000.00
        $this->assertEquals($expectedBalance, $wallet->balance);

        // Performance check
        $this->assertLessThan(10, $duration, "Processing 1000 transactions should take less than 10 seconds");
    }

    public function test_processes_1000_acme_transactions_efficiently()
    {
        // Generate 1000 transaction lines for Acme format
        $lines = [];
        for ($i = 1; $i <= 1000; $i++) {
            $lines[] = "20250615//{$i}.00//ACME-REF-{$i}";
        }
        $payload = implode("\n", $lines);

        $webhookCall = \App\Models\WebhookCall::create([
            'bank_provider' => 'acme',
            'payload' => $payload,
        ]);

        $startTime = microtime(true);

        $job = new ProcessWebhookJob($webhookCall->id);
        $job->handle(new NotificationDispatcher());

        $endTime = microtime(true);
        $duration = $endTime - $startTime;

        // Verify all 1000 transactions created
        $this->assertEquals(1000, Transaction::count());

        // Performance check
        $this->assertLessThan(10, $duration, "Processing 1000 Acme transactions should take less than 10 seconds");
    }

    public function test_handles_idempotency_correctly_in_bulk_processing()
    {
        // First, process 100 transactions
        $lines1 = [];
        for ($i = 1; $i <= 100; $i++) {
            $lines1[] = "20250615,{$i}.00#REF{$i}";
        }
        $payload1 = implode("\n", $lines1);

        $webhookCall1 = \App\Models\WebhookCall::create([
            'bank_provider' => 'paytech',
            'payload' => $payload1,
        ]);

        $job1 = new ProcessWebhookJob($webhookCall1->id);
        $job1->handle(new NotificationDispatcher());

        $this->assertEquals(100, Transaction::count());

        // Now process again with 50 duplicates and 50 new
        $lines2 = [];
        for ($i = 1; $i <= 50; $i++) {
            $lines2[] = "20250615,{$i}.00#REF{$i}"; // Duplicate
        }
        for ($i = 101; $i <= 150; $i++) {
            $lines2[] = "20250615,{$i}.00#REF{$i}"; // New
        }
        $payload2 = implode("\n", $lines2);

        $webhookCall2 = \App\Models\WebhookCall::create([
            'bank_provider' => 'paytech',
            'payload' => $payload2,
        ]);

        $job2 = new ProcessWebhookJob($webhookCall2->id);
        $job2->handle(new NotificationDispatcher());

        // Should have 150 total (100 original + 50 new)
        $this->assertEquals(150, Transaction::count());
    }

    public function test_maintains_data_integrity_in_bulk_processing()
    {
        // Generate 500 transactions
        $lines = [];
        for ($i = 1; $i <= 500; $i++) {
            $lines[] = "20250615,{$i}.00#REF{$i}";
        }
        $payload = implode("\n", $lines);

        $webhookCall = \App\Models\WebhookCall::create([
            'bank_provider' => 'paytech',
            'payload' => $payload,
        ]);

        $job = new ProcessWebhookJob($webhookCall->id);
        $job->handle(new NotificationDispatcher());

        // Verify data integrity
        $this->assertEquals(500, Transaction::count());

        // Verify each transaction has correct amount
        for ($i = 1; $i <= 500; $i++) {
            $txn = Transaction::where('bank_reference', "REF{$i}")->first();
            $this->assertNotNull($txn, "Transaction REF{$i} should exist");
            $this->assertEquals((float) $i, (float) $txn->amount);
        }
    }

    public function test_handles_empty_lines_in_bulk_payload()
    {
        // Generate payload with empty lines
        $lines = [];
        for ($i = 1; $i <= 10; $i++) {
            $lines[] = "20250615,{$i}.00#REF{$i}";
            if ($i < 10) {
                $lines[] = ''; // Empty line
            }
        }
        $payload = implode("\n", $lines);

        $webhookCall = \App\Models\WebhookCall::create([
            'bank_provider' => 'paytech',
            'payload' => $payload,
        ]);

        $job = new ProcessWebhookJob($webhookCall->id);
        $job->handle(new NotificationDispatcher());

        // Should only process 10 non-empty lines
        $this->assertEquals(10, Transaction::count());
    }
}
