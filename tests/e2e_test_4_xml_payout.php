<?php

/**
 * E2E Test 4: XML Payout Generation
 *
 * Verify XML generation with conditional tags.
 */

require __DIR__.'/../vendor/autoload.php';

$app = require_once __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Services\XmlGeneratorService;

echo "=== E2E Test 4: XML Payout Generation ===" . PHP_EOL;
echo PHP_EOL;

$generator = new XmlGeneratorService();

$allPassed = true;

// Test 1: All fields
echo "Test 1: All fields present" . PHP_EOL;
echo "---------------------------" . PHP_EOL;
$xml1 = $generator->generate([
    'date' => '2025-02-25 06:33:00+03',
    'amount' => 177.39,
    'currency' => 'SAR',
    'notes' => 'Test Payment',
    'paymentType' => 1,
    'chargeDetails' => 'OUR',
]);
echo "XML Output:" . PHP_EOL;
echo $xml1 . PHP_EOL;
echo PHP_EOL;

// Verify all tags present
$checks1 = [
    'has_date' => str_contains($xml1, '<Date>2025-02-25 06:33:00+03</Date>'),
    'has_amount' => str_contains($xml1, '<Amount>177.39</Amount>'),
    'has_currency' => str_contains($xml1, '<Currency>SAR</Currency>'),
    'has_notes' => str_contains($xml1, '<Notes>Test Payment</Notes>'),
    'has_payment_type' => str_contains($xml1, '<PaymentType>1</PaymentType>'),
    'has_charge_details' => str_contains($xml1, '<ChargeDetails>OUR</ChargeDetails>'),
];
foreach ($checks1 as $check => $result) {
    if ($result) {
        echo "✓ PASS: {$check}" . PHP_EOL;
    } else {
        echo "✗ FAIL: {$check}" . PHP_EOL;
        $allPassed = false;
    }
}
echo PHP_EOL;

// Test 2: Empty notes (should omit <Notes>)
echo "Test 2: Empty notes (should omit <Notes>)" . PHP_EOL;
echo "------------------------------------------" . PHP_EOL;
$xml2 = $generator->generate([
    'date' => '2025-02-25 06:33:00+03',
    'amount' => 177.39,
    'currency' => 'SAR',
    'notes' => '',
]);
$hasNotes = str_contains($xml2, '<Notes>');
echo "Contains Notes tag: " . ($hasNotes ? 'YES (FAIL)' : 'NO (PASS)') . PHP_EOL;
if ($hasNotes) {
    echo "✗ FAIL: <Notes> tag should be omitted when empty" . PHP_EOL;
    $allPassed = false;
} else {
    echo "✓ PASS: <Notes> tag omitted when empty" . PHP_EOL;
}
echo PHP_EOL;

// Test 3: PaymentType = 99 (should omit <PaymentType>)
echo "Test 3: PaymentType = 99 (should omit <PaymentType>)" . PHP_EOL;
echo "------------------------------------------------------" . PHP_EOL;
$xml3 = $generator->generate([
    'date' => '2025-02-25 06:33:00+03',
    'amount' => 177.39,
    'currency' => 'SAR',
    'paymentType' => 99,
]);
$hasPaymentType = str_contains($xml3, '<PaymentType>');
echo "Contains PaymentType tag: " . ($hasPaymentType ? 'YES (FAIL)' : 'NO (PASS)') . PHP_EOL;
if ($hasPaymentType) {
    echo "✗ FAIL: <PaymentType> tag should be omitted when value is 99" . PHP_EOL;
    $allPassed = false;
} else {
    echo "✓ PASS: <PaymentType> tag omitted when value is 99" . PHP_EOL;
}
echo PHP_EOL;

// Test 4: ChargeDetails = SHA (should omit <ChargeDetails>)
echo "Test 4: ChargeDetails = SHA (should omit <ChargeDetails>)" . PHP_EOL;
echo "----------------------------------------------------------" . PHP_EOL;
$xml4 = $generator->generate([
    'date' => '2025-02-25 06:33:00+03',
    'amount' => 177.39,
    'currency' => 'SAR',
    'chargeDetails' => 'SHA',
]);
$hasChargeDetails = str_contains($xml4, '<ChargeDetails>');
echo "Contains ChargeDetails tag: " . ($hasChargeDetails ? 'YES (FAIL)' : 'NO (PASS)') . PHP_EOL;
if ($hasChargeDetails) {
    echo "✗ FAIL: <ChargeDetails> tag should be omitted when value is SHA" . PHP_EOL;
    $allPassed = false;
} else {
    echo "✓ PASS: <ChargeDetails> tag omitted when value is SHA" . PHP_EOL;
}
echo PHP_EOL;

// Test 5: ChargeDetails = SHA (lowercase, should also omit)
echo "Test 5: ChargeDetails = sha (lowercase, should omit <ChargeDetails>)" . PHP_EOL;
echo "------------------------------------------------------------------------" . PHP_EOL;
$xml5 = $generator->generate([
    'date' => '2025-02-25 06:33:00+03',
    'amount' => 177.39,
    'currency' => 'SAR',
    'chargeDetails' => 'sha', // lowercase
]);
$hasChargeDetails5 = str_contains($xml5, '<ChargeDetails>');
echo "Contains ChargeDetails tag: " . ($hasChargeDetails5 ? 'YES (FAIL)' : 'NO (PASS)') . PHP_EOL;
if ($hasChargeDetails5) {
    echo "✗ FAIL: <ChargeDetails> tag should be omitted when value is sha (case insensitive)" . PHP_EOL;
    $allPassed = false;
} else {
    echo "✓ PASS: <ChargeDetails> tag omitted when value is sha (case insensitive)" . PHP_EOL;
}
echo PHP_EOL;

// Summary
echo "=== Test Results ===" . PHP_EOL;
if ($allPassed) {
    echo "=== ✅ TEST 4 PASSED ===" . PHP_EOL;
    echo "All XML conditional tag logic working correctly!" . PHP_EOL;
    exit(0);
} else {
    echo "=== ❌ TEST 4 FAILED ===" . PHP_EOL;
    exit(1);
}
