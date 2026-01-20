<?php

/**
 * E2E Test 2: Acme Webhook Ingestion
 *
 * Verify Acme webhook creates transaction correctly.
 */

require __DIR__.'/../vendor/autoload.php';

$app = require_once __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\WebhookCall;
use App\Models\Transaction;
use App\Models\Wallet;
use App\Jobs\ProcessWebhookJob;

echo "=== E2E Test 2: Acme Webhook Ingestion ===" . PHP_EOL;
echo PHP_EOL;

// Create webhook
echo "1. Creating Acme webhook call..." . PHP_EOL;
$webhook = WebhookCall::create([
    'bank_provider' => 'acme',
    'payload' => '20250615//250.75//ACME-REF-002',
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
$txn = Transaction::where('bank_reference', 'ACME-REF-002')->first();
if (!$txn) {
    echo "   ✗ FAIL: Transaction not found!" . PHP_EOL;
    exit(1);
}
echo "   ✓ Transaction found" . PHP_EOL;
echo "   - Amount: " . number_format($txn->amount, 4) . PHP_EOL;
echo "   - Bank Reference: {$txn->bank_reference}" . PHP_EOL;
echo "   - Bank Provider: {$txn->bank_provider}" . PHP_EOL;
echo PHP_EOL;

// Verify wallet balance (should be 100.50 + 250.75 = 351.25)
echo "4. Verifying wallet balance..." . PHP_EOL;
$wallet = Wallet::find(1);
$expectedBalance = 100.50 + 250.75; // 351.25
echo "   ✓ Current wallet balance: " . number_format($wallet->balance, 4) . PHP_EOL;
echo "   ✓ Expected balance: " . number_format($expectedBalance, 4) . " (100.50 + 250.75)" . PHP_EOL;
echo PHP_EOL;

// Assertions
echo "=== Test Results ===" . PHP_EOL;
$pass = true;

// Expected: 250.75
if ((float) $txn->amount === 250.75) {
    echo "✓ PASS: Transaction amount is 250.75" . PHP_EOL;
} else {
    echo "✗ FAIL: Expected 250.75, got " . number_format($txn->amount, 4) . PHP_EOL;
    $pass = false;
}

// Expected: 351.25
if ((float) $wallet->balance === 351.25) {
    echo "✓ PASS: Wallet balance is 351.25 (100.50 + 250.75)" . PHP_EOL;
} else {
    echo "✗ FAIL: Expected 351.25, got " . number_format($wallet->balance, 4) . PHP_EOL;
    $pass = false;
}

echo PHP_EOL;
if ($pass) {
    echo "=== ✅ TEST 2 PASSED ===" . PHP_EOL;
    exit(0);
} else {
    echo "=== ❌ TEST 2 FAILED ===" . PHP_EOL;
    exit(1);
}
