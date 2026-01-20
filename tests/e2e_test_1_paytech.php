<?php

/**
 * E2E Test 1: PayTech Webhook Ingestion
 *
 * Verify PayTech webhook creates transaction and updates wallet balance.
 */

require __DIR__.'/../vendor/autoload.php';

$app = require_once __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\WebhookCall;
use App\Models\Transaction;
use App\Models\Wallet;
use App\Jobs\ProcessWebhookJob;

echo "=== E2E Test 1: PayTech Webhook Ingestion ===" . PHP_EOL;
echo PHP_EOL;

// Create webhook
echo "1. Creating PayTech webhook call..." . PHP_EOL;
$webhook = WebhookCall::create([
    'bank_provider' => 'paytech',
    'payload' => '20250615,100.50#REF001#note/Test Payment/internal_reference/ABC123',
]);
echo "   ✓ Webhook created with ID: {$webhook->id}" . PHP_EOL;
echo PHP_EOL;

// Process webhook
echo "2. Processing webhook via ProcessWebhookJob..." . PHP_EOL;
$job = new ProcessWebhookJob($webhook->id);
$job->handle();
echo "   ✓ Job processed" . PHP_EOL;
echo PHP_EOL;

// Verify transaction
echo "3. Verifying transaction..." . PHP_EOL;
$txn = Transaction::where('bank_reference', 'REF001')->first();
if (!$txn) {
    echo "   ✗ FAIL: Transaction not found!" . PHP_EOL;
    exit(1);
}
echo "   ✓ Transaction found" . PHP_EOL;
echo "   - Amount: " . number_format($txn->amount, 4) . PHP_EOL;
echo "   - Bank Reference: {$txn->bank_reference}" . PHP_EOL;
echo "   - Bank Provider: {$txn->bank_provider}" . PHP_EOL;
echo "   - Type: {$txn->type}" . PHP_EOL;
echo PHP_EOL;

// Verify wallet balance
echo "4. Verifying wallet balance..." . PHP_EOL;
$wallet = Wallet::find(1);
echo "   ✓ Wallet balance: " . number_format($wallet->balance, 4) . PHP_EOL;
echo PHP_EOL;

// Verify webhook status
echo "5. Verifying webhook status..." . PHP_EOL;
$webhook->refresh();
echo "   ✓ Webhook status: {$webhook->status}" . PHP_EOL;
echo PHP_EOL;

// Assertions
echo "=== Test Results ===" . PHP_EOL;
$pass = true;

// Expected: 100.5000
if ((float) $txn->amount === 100.50) {
    echo "✓ PASS: Transaction amount is 100.50" . PHP_EOL;
} else {
    echo "✗ FAIL: Expected 100.50, got " . number_format($txn->amount, 4) . PHP_EOL;
    $pass = false;
}

// Expected: 100.5000
if ((float) $wallet->balance === 100.50) {
    echo "✓ PASS: Wallet balance is 100.50" . PHP_EOL;
} else {
    echo "✗ FAIL: Expected 100.50, got " . number_format($wallet->balance, 4) . PHP_EOL;
    $pass = false;
}

// Expected: processed
if ($webhook->status === 'processed') {
    echo "✓ PASS: Webhook status is 'processed'" . PHP_EOL;
} else {
    echo "✗ FAIL: Expected 'processed', got '{$webhook->status}'" . PHP_EOL;
    $pass = false;
}

echo PHP_EOL;
if ($pass) {
    echo "=== ✅ TEST 1 PASSED ===" . PHP_EOL;
    exit(0);
} else {
    echo "=== ❌ TEST 1 FAILED ===" . PHP_EOL;
    exit(1);
}
