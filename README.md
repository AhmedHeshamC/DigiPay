# DigiPay - Digital Wallet Coding Challenge

A robust back-end module for a Digital Wallet Application built with Laravel 12. The system acts as middleware between banking infrastructure and an internal ledger, handling webhook ingestion (money in) and XML payouts (money out).

## Features

- **Multi-Provider Webhook Ingestion**: Accept webhooks from PayTech and Acme banks
- **Idempotency**: Prevents duplicate transactions using database constraints
- **Resilient Buffering**: Queue-based async processing ensures zero data loss
- **Bulk Processing**: Efficiently handles 1000+ transactions in a single webhook
- **XML Payout Generation**: Transform JSON to XML with conditional tag rendering
- **Test-Driven Development**: 100% TDD compliance with comprehensive test suite

## Tech Stack

- PHP 8.5+
- Laravel 12.47.0
- MySQL/SQLite
- Queue: Database driver

## Architecture

```
┌─────────────┐      ┌──────────────┐      ┌─────────────┐
│   Bank API  │─────>│  Webhook API  │─────>│   Queue     │
│  (PayTech)  │      │  POST /webhooks│      │  (Async)    │
└─────────────┘      └──────────────┘      └─────────────┘
                                              │
                                              v
┌─────────────┐      ┌──────────────┐      ┌─────────────┐
│   Bank API  │─────>│  Parser      │<─────│   Worker     │
│   (Acme)    │      │  (Strategy)   │      │   (Job)      │
└─────────────┘      └──────────────┘      └─────────────┘
                           │                      │
                           v                      v
                    ┌──────────────┐      ┌─────────────┐
                    │  Factory     │      │ Transactions │
                    │               │      │   (Ledger)   │
                    └──────────────┘      └─────────────┘
```

## Database Schema

### wallets
Master wallet table for storing account balances.

### webhook_calls
Buffer table for resilient webhook ingestion (FR-03).

### transactions
The ledger table storing all parsed transactions with idempotency constraint.

**Idempotency Constraint:** Unique key on `(bank_provider, bank_reference)`

## API Documentation

### Webhook Ingestion (Module A)

**Endpoint:** `POST /api/v1/webhooks/{bank_name}`

**Headers:** `Content-Type: text/plain`

**Response:** `202 Accepted`

**Supported Banks:** `paytech`, `acme`

### XML Payout (Module B)

**Endpoint:** `POST /api/v1/payouts/xml`

**Headers:**
- `Content-Type: application/json`
- `Accept: application/xml`

**Response:** `200 OK` with XML body

**Conditional Tag Logic:**
- `<Notes>` - Omitted if empty
- `<PaymentType>` - Omitted if value is `99`
- `<ChargeDetails>` - Omitted if value is `SHA`

## Installation

```bash
# Install dependencies
composer install

# Copy environment file
cp .env.example .env

# Generate application key
php artisan key:generate

# Run migrations
php artisan migrate

# Seed default wallet
php artisan db:seed
```

## Testing

### Run All Tests
```bash
php artisan test
```

### Current Test Status
- **Total Tests:** 78
- **Assertions:** 1187
- **Coverage:** 100% of core business logic

## End-to-End Testing Guide

This section provides step-by-step instructions to replicate all testing scenarios and verify the implementation.

### Prerequisites

```bash
# Ensure dependencies are installed
composer install

# Ensure environment is configured
cp .env.example .env
php artisan key:generate

# Run migrations and seeding
php artisan migrate
php artisan db:seed
```

### Test 1: Webhook Ingestion (PayTech)

**Objective:** Verify PayTech webhook creates transaction and updates wallet balance.

```bash
# Using Laravel Tinker
php artisan tinker

# Run the following commands in Tinker:
\$webhook = \App\Models\WebhookCall::create([
    'bank_provider' => 'paytech',
    'payload' => '20250615,100.50#REF001#note/Test Payment/internal_reference/ABC123',
]);

\$job = new \App\Jobs\ProcessWebhookJob(\$webhook->id);
\$job->handle();

# Verify results
echo \App\Models\Transaction::where('bank_reference', 'REF001')->first()->amount;
echo \App\Models\Wallet::find(1)->balance;
echo \App\Models\WebhookCall::find(\$webhook->id)->status;

# Expected output:
# 100.5000
# 100.5000
# processed
```

**Expected Results:**
- ✅ Transaction created with amount `100.50`
- ✅ Wallet balance updated to `100.50`
- ✅ Webhook call status changed to `processed`

### Test 2: Webhook Ingestion (Acme)

**Objective:** Verify Acme webhook creates transaction correctly.

```bash
php artisan tinker

\$webhook = \App\Models\WebhookCall::create([
    'bank_provider' => 'acme',
    'payload' => '20250615//250.75//ACME-REF-002',
]);

\$job = new \App\Jobs\ProcessWebhookJob(\$webhook->id);
\$job->handle();

# Verify results
echo \App\Models\Transaction::where('bank_reference', 'ACME-REF-002')->first()->amount;
echo \App\Models\Wallet::find(1)->balance;

# Expected output:
# 250.7500
# 351.2500 (100.50 + 250.75)
```

### Test 3: Idempotency (Duplicate Prevention)

**Objective:** Verify duplicate webhooks are ignored (FR-05).

```bash
php artisan tinker

# First webhook - creates transaction
\$webhook1 = \App\Models\WebhookCall::create([
    'bank_provider' => 'paytech',
    'payload' => '20250615,50#REF003',
]);
\$job1 = new \App\Jobs\ProcessWebhookJob(\$webhook1->id);
\$job1->handle();

# Second webhook with same reference - should be ignored
\$webhook2 = \App\Models\WebhookCall::create([
    'bank_provider' => 'paytech',
    'payload' => '20250615,50#REF003', // DUPLICATE
]);
\$job2 = new \App\Jobs\ProcessWebhookJob(\$webhook2->id);
\$job2->handle();

# Verify only 1 transaction exists
echo \App\Models\Transaction::where('bank_reference', 'REF003')->count();

# Expected output:
# 1 (not 2)
```

**Expected Results:**
- ✅ Only 1 transaction created (idempotency working)

### Test 4: XML Payout Generation

**Objective:** Verify XML generation with conditional tags.

```bash
php artisan tinker

\$generator = new \App\Services\XmlGeneratorService();

// Test 1: All fields
\$xml1 = \$generator->generate([
    'date' => '2025-02-25 06:33:00+03',
    'amount' => 177.39,
    'currency' => 'SAR',
    'notes' => 'Test Payment',
    'paymentType' => 1,
    'chargeDetails' => 'OUR',
]);

echo \$xml1;

// Test 2: Empty notes (should omit <Notes>)
\$xml2 = \$generator->generate([
    'date' => '2025-02-25 06:33:00+03',
    'amount' => 177.39,
    'currency' => 'SAR',
    'notes' => '',
]);
echo PHP_EOL . 'Contains Notes tag: ' . (str_contains(\$xml2, '<Notes>') ? 'YES' : 'NO');

// Test 3: PaymentType = 99 (should omit <PaymentType>)
\$xml3 = \$generator->generate([
    'date' => '2025-02-25 06:33:00+03',
    'amount' => 177.39,
    'currency' => 'SAR',
    'paymentType' => 99,
]);
echo 'Contains PaymentType tag: ' . (str_contains(\$xml3, '<PaymentType>') ? 'YES' : 'NO');

// Test 4: ChargeDetails = SHA (should omit <ChargeDetails>)
\$xml4 = \$generator->generate([
    'date' => '2025-02-25 06:33:00+03',
    'amount' => 177.39,
    'currency' => 'SAR',
    'chargeDetails' => 'SHA',
]);
echo 'Contains ChargeDetails tag: ' . (str_contains(\$xml4, '<ChargeDetails>') ? 'YES' : 'NO');
```

**Expected Results:**
- ✅ Test 2: `Contains Notes tag: NO`
- ✅ Test 3: `Contains PaymentType tag: NO`
- ✅ Test 4: `Contains ChargeDetails tag: NO`

### Test 5: API Endpoints (via HTTP)

**Objective:** Test the actual HTTP endpoints.

```bash
# Start the development server
php artisan serve --port=8000

# In another terminal, test the endpoints:

# Test 1: Webhook Ingestion (PayTech)
curl -X POST http://localhost:8000/api/v1/webhooks/paytech \
  -H "Content-Type: text/plain" \
  -d "20250615,100.50#HTTPTEST1"

# Expected: 202 Accepted
# Verify in database: Transaction created with amount 100.50

# Test 2: Webhook Ingestion (Acme)
curl -X POST http://localhost:8000/api/v1/webhooks/acme \
  -H "Content-Type: text/plain" \
  -d "20250615//75.50//ACME-REF1"

# Expected: 202 Accepted
# Verify in database: Transaction created with amount 75.50

# Test 3: XML Payout
curl -X POST http://localhost:8000/api/v1/payouts/xml \
  -H "Content-Type: application/json" \
  -H "Accept: application/xml" \
  -d '{
    "date": "2025-02-25 06:33:00+03",
    "amount": 99.99,
    "currency": "USD"
  }'

# Expected: 200 OK with XML body
```

### Test 6: Bulk Processing (1000 Transactions)

**Objective:** Verify NFR-02 performance requirement.

```bash
php artisan test --filter BulkPerformanceTest

# Expected output:
# ✓ processes 1000 paytech transactions efficiently
# ✓ processes 1000 acme transactions efficiently
# ✓ handles idempotency correctly in bulk processing
# ✓ maintains data integrity in bulk processing
# ✓ handles empty lines in bulk payload
# Tests: 5 passed (1009 assertions)
# Duration: ~0.7s
```

**Expected Results:**
- ✅ 1000 PayTech transactions processed in < 1s
- ✅ 1000 Acme transactions processed in < 1s
- ✅ Idempotency maintained in bulk processing
- ✅ Data integrity preserved

### Test 7: Full Test Suite

**Objective:** Run all tests and verify 100% pass rate.

```bash
# Run all tests
php artisan test

# Expected output:
# Tests:    78 passed (1187 assertions)
# Duration: ~1.1s

# Run tests with coverage report
php artisan test --coverage

# List all test files
php artisan test --list
```

### Manual Database Verification

```bash
php artisan tinker

# Check all tables
echo \App\Models\Wallet::count();           // Should be 1 (default wallet)
echo \App\Models\WebhookCall::count();     // Should be > 0
echo \App\Models\Transaction::count();      // Should be > 0

# Check wallet balance
echo \App\Models\Wallet::find(1)->balance;

# View recent transactions
\App\Models\Transaction::latest()->take(5)->get()->each(function(\$t) {
    echo \$t->bank_provider . ': ' . \$t->amount . ' (' . \$t->bank_reference . ')' . PHP_EOL;
});
```

### Cleanup (Optional)

To reset the database for testing:

```bash
# Fresh start
php artisan migrate:fresh
php artisan db:seed
```

## Replication Checklist

Use this checklist to verify the complete implementation:

- [ ] Installation steps completed (migrate, seed)
- [ ] PayTech webhook creates transaction ✅
- [ ] Acme webhook creates transaction ✅
- [ ] Duplicate webhooks are ignored (idempotency) ✅
- [ ] Wallet balance updates on credits ✅
- [ ] XML generated with all required fields ✅
- [ ] XML omits empty `<Notes>` tag ✅
- [ ] XML omits `<PaymentType>` when value is 99 ✅
- [ ] XML omits `<ChargeDetails>` when value is SHA ✅
- [ ] Webhook API returns 202 Accepted ✅
- [ ] Payout API returns 200 with XML ✅
- [ ] 1000 transactions process in < 10 seconds ✅
- [ ] All 78 tests passing ✅

## Development Methodology (TDD)

This project follows strict **Test-Driven Development (TDD)** methodology:

### Red-Green-Refactor Cycle

1. **Red**: Write a failing test that defines expected behavior
2. **Green**: Write minimum code to pass the test
3. **Refactor**: Clean up code while keeping tests green

### Critical Tests
- `PayTechParserTest` - Parser unit tests
- `AcmeParserTest` - Parser unit tests
- `XmlGeneratorTest` - XML generation with conditional logic
- `WebhookIngestionTest` - API endpoint tests
- `ProcessWebhookJobTest` - Idempotency and job processing
- `BulkPerformanceTest` - 1000 transaction performance

## Design Decisions

### 1. Strategy Pattern for Parsers (FR-01)
Each bank has a completely different format. Adding new banks requires only creating a new parser class and updating the factory.

### 2. Database-Level Idempotency (FR-05)
Use unique constraint on `(bank_provider, bank_reference)` to prevent duplicates at the database level.

### 3. Resilient Buffer Table (FR-03)
Store webhooks immediately to `webhook_calls` before processing. API returns 202 immediately.

### 4. Decimal Precision for Money
Use `decimal(19,4)` for amounts to accommodate any currency's smallest unit.

### 5. Readonly DTO for Parsed Transactions
Use PHP 8 `readonly` class for `ParsedTransaction` for language-level immutability.

## Performance Characteristics

| Metric | Result | Requirement |
|--------|--------|------------|
| 1000 PayTech transactions | 0.30s | < 10s ✅ |
| 1000 Acme transactions | 0.19s | < 10s ✅ |
| Idempotency check | O(1) via unique constraint | ✅ |

## Non-Functional Requirements Compliance

| ID | Requirement | Status |
|----|-------------|--------|
| NFR-01 | Strict TDD | ✅ All code written test-first |
| NFR-02 | Performance | ✅ 1000 transactions in < 1s |
| NFR-03 | Queued Architecture | ✅ Database queue with async jobs |
| NFR-04 | PSR-12 Standards | ✅ Code follows PSR-12 |
| NFR-05 | Documentation | ✅ README + inline comments |

## Future Enhancements

1. **Additional Banks**: Add new parser classes implementing `WebhookParserInterface`
2. **Debit Transactions**: Extend job to handle debit transactions
3. **Webhook Retry Logic**: Auto-retry failed webhook processing
4. **API Authentication**: Add API token authentication
5. **Rate Limiting**: Add rate limiting for webhook endpoints

## License

This is a coding challenge project.
