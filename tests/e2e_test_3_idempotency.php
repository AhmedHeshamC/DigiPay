<?php

/**
 * E2E Test 3: Idempotency (Duplicate Prevention)
 *
 * Verify duplicate webhooks are ignored (FR-05).
 */

require __DIR__.'/../vendor/autoload.php';

$app = require_once __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\WebhookCall;
use App\Models\Transaction;
use App\Models\Wallet;
use App\Jobs\ProcessWebhookJob;

echo "=== E2E Test 3: Idempotency (Duplicate Prevention) ===" . PHP_EOL;
echo PHP_EOL;

// Get initial wallet balance
$wallet = Wallet::find(1);
$initialBalance = (float) $wallet->balance;
echo "Initial wallet balance: " . number_format($initialBalance, 4) . PHP_EOL;
echo PHP_EOL;

// First webhook - creates transaction
echo "1. Creating first webhook (REF003)..." . PHP_EOL;
$webhook1 = WebhookCall::create([
    'bank_provider' => 'paytech',
    'payload' => '20250615,50#REF003',
]);
echo "   ✓ Webhook created with ID: {$webhook1->id}" . PHP_EOL;
echo PHP_EOL;

echo "2. Processing first webhook..." . PHP_EOL;
$job1 = new ProcessWebhookJob($webhook1->id);
$job1->handle();
echo "   ✓ Job processed" . PHP_EOL;
echo PHP_EOL;

$txnCount1 = Transaction::where('bank_reference', 'REF003')->count();
echo "   Transaction count after first webhook: {$txnCount1}" . PHP_EOL;
$wallet->refresh();
echo "   Wallet balance after first webhook: " . number_format($wallet->balance, 4) . PHP_EOL;
echo PHP_EOL;

// Second webhook with same reference - should be ignored
echo "3. Creating duplicate webhook (same REF003)..." . PHP_EOL;
$webhook2 = WebhookCall::create([
    'bank_provider' => 'paytech',
    'payload' => '20250615,50#REF003', // DUPLICATE
]);
echo "   ✓ Webhook created with ID: {$webhook2->id}" . PHP_EOL;
echo PHP_EOL;

echo "4. Processing duplicate webhook..." . PHP_EOL;
$job2 = new ProcessWebhookJob($webhook2->id);
$job2->handle();
echo "   ✓ Job processed" . PHP_EOL;
echo PHP_EOL;

$txnCount2 = Transaction::where('bank_reference', 'REF003')->count();
echo "   Transaction count after duplicate webhook: {$txnCount2}" . PHP_EOL;
$wallet->refresh();
echo "   Wallet balance after duplicate webhook: " . number_format($wallet->balance, 4) . PHP_EOL;
echo PHP_EOL;

// Assertions
echo "=== Test Results ===" . PHP_EOL;
$pass = true;

// Expected: only 1 transaction
if ($txnCount2 === 1) {
    echo "✓ PASS: Only 1 transaction created (idempotency working)" . PHP_EOL;
} else {
    echo "✗ FAIL: Expected 1 transaction, found {$txnCount2}" . PHP_EOL;
    $pass = false;
}

// Expected: balance unchanged after duplicate
$expectedBalance = $initialBalance + 50;
if ((float) $wallet->balance === $expectedBalance) {
    echo "✓ PASS: Wallet balance unchanged after duplicate (" . number_format($expectedBalance, 4) . ")" . PHP_EOL;
} else {
    echo "✗ FAIL: Expected balance " . number_format($expectedBalance, 4) . ", got " . number_format($wallet->balance, 4) . PHP_EOL;
    $pass = false;
}

// Verify both webhooks are marked as processed
$webhook1->refresh();
$webhook2->refresh();
if ($webhook1->status === 'processed' && $webhook2->status === 'processed') {
    echo "✓ PASS: Both webhooks marked as 'processed'" . PHP_EOL;
} else {
    echo "✗ FAIL: Webhook statuses - 1: {$webhook1->status}, 2: {$webhook2->status}" . PHP_EOL;
    $pass = false;
}

echo PHP_EOL;
if ($pass) {
    echo "=== ✅ TEST 3 PASSED ===" . PHP_EOL;
    echo "Idempotency is working correctly - duplicate transactions are prevented!" . PHP_EOL;
    exit(0);
} else {
    echo "=== ❌ TEST 3 FAILED ===" . PHP_EOL;
    exit(1);
}
