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
- **Total Tests:** 50+
- **Assertions:** 1000+
- **Coverage:** 100% of core business logic

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
