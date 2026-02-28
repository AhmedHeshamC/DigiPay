# Business Requirement Document (BRD)

**Project:** Digital Wallet Coding Challenge
**Version:** 4.0 (Final with Notification System)
**Date:** January 20, 2026

---

## 1. Executive Summary

The objective is to develop a robust back-end module for a Digital Wallet Application. The system acts as a middleware between banking infrastructure and an internal ledger, responsible for:

1. **Ingesting money** - Handling raw webhook streams from multiple bank providers
2. **Facilitating payouts** - Generating standardized XML for payment processing
3. **Sending notifications** - Multi-channel notifications via Email, SMS, and Push

Key drivers for this project are **Idempotency** (preventing duplicate entries), **Resilience** (zero data loss), and **Code Reliability**. To ensure the highest level of stability and maintainability, this project strictly adheres to the **Test-Driven Development (TDD)** methodology.

---

## 2. Development Methodology (TDD)

### Mandatory Requirement:
All functional logic must be implemented using the **Test-Driven Development (Red-Green-Refactor) cycle**.

| Phase | Description |
|-------|-------------|
| **Red (Write the Test)** | Create a failing test case that defines the expected behavior (e.g., "assert that the PayTech parser extracts 50.00 from string X"). |
| **Green (Make it Pass)** | Write the minimum amount of code required to pass that test. |
| **Refactor** | Clean up the code, optimize logic, and improve readability while keeping the test green. |

### Evidence of TDD:
- **Commits:** The Git commit history reflects the progression of adding tests first, then adding features.
- **Coverage:** 100% of the core business logic (Parsers, XML Generation, Idempotency checks, Notification System) is covered by automated tests.

---

## 3. Functional Requirements

### 3.1 Module A: Ingestion (Receiving Money)

| ID | Requirement | Description |
|----|-------------|-------------|
| FR-01 | Multi-Provider Support | Accept webhooks from "PayTech" and "Acme". The architecture must allow adding new banks easily (Strategy Pattern). |
| FR-02 | Raw Data Handling | The API must accept `text/plain` payloads containing CSV-like or custom delimited strings. |
| FR-03 | Resilient Buffering | **Crucial:** The system must store raw webhooks in `webhook_calls` before processing. The API must return `202 Accepted` immediately, and a queued job processes the payload. This ensures zero data loss if parsing fails. |
| FR-04 | Bulk Processing | A single webhook payload may contain multiple lines (bulk). The system must process each line efficiently. |
| FR-05 | Idempotency | Duplicate webhooks (same `bank_provider` + `bank_reference`) must be ignored safely. |
| FR-06 | Default Wallet | All credits go to wallet ID 1 (default wallet). |

### 3.2 Module B: Payouts (Sending Money)

| ID | Requirement | Description |
|----|-------------|-------------|
| FR-07 | XML Generation | Generate an XML file compliant with the provided schema based on JSON input. |
| FR-08 | Conditional Tags | - Omit `<Notes>` if empty.<br>- Omit `<PaymentType>` if 99.<br>- Omit `<ChargeDetails>` if SHA. |
| FR-09 | Output Format | The response must be `application/xml`, encoded in UTF-8. |

### 3.3 Module C: Notification System

| ID | Requirement | Description |
|----|-------------|-------------|
| FR-10 | Notification Triggers | The system must send a notification upon: (1) successful transaction credit, (2) failed transaction or processing error, and (3) successful payout XML generation. |
| FR-11 | Multi-Channel Support | The system must support three notification channels: **Email**, **SMS**, and **Push**. Each channel is a concrete implementation of a shared `NotificationSender` interface. |
| FR-12 | Factory Method Pattern | A `NotificationFactory` must be responsible for instantiating the correct sender. Client code must never directly instantiate a concrete sender (e.g., `new EmailSender()`). Decoupling from concrete classes is mandatory. |
| FR-13 | Notification Payload | Each notification must include: recipient identifier (email / phone / device token), notification type (success / failure), channel (email / sms / push), message body, and a reference to the source transaction or payout ID. |
| FR-14 | Dispatch Mode by Channel | Dispatch mode is determined by channel type: **Push** notifications are dispatched **synchronously** (lightweight, instant feedback expected by the user). **Email** and **SMS** notifications are dispatched **asynchronously** via the existing queue worker infrastructure to avoid blocking the main processing flow. |
| FR-15 | Extensibility | The architecture must allow new channels (e.g., WhatsApp, Slack) to be added by creating a new class implementing the `NotificationSender` interface and registering it in the factory — with zero changes to client code. |

---

## 4. Database Schema Design

### Table 1: `wallets` (Master Data)

```php
Schema::create('wallets', function (Blueprint $table) {
    $table->id();
    $table->string('owner_name');
    $table->string('email')->unique();
    // 19 digits, 4 decimal places for precision
    $table->decimal('balance', 19, 4)->default(0);
    $table->string('currency', 3)->default('USD');
    $table->timestamps();
});
```

### Table 2: `webhook_calls` (The Buffer)

**Satisfies FR-03 (Resilience).** API writes here; Workers read from here.

```php
Schema::create('webhook_calls', function (Blueprint $table) {
    $table->id();
    $table->string('bank_provider'); // 'paytech', 'acme'
    $table->longText('payload');     // Raw body
    $table->string('status')->default('pending');
    $table->text('error_message')->nullable();
    $table->timestamps();
});
```

### Table 3: `transactions` (The Ledger)

Stores parsed data. Includes unique constraint for Idempotency.

```php
Schema::create('transactions', function (Blueprint $table) {
    $table->id();
    $table->foreignId('wallet_id')->constrained('wallets')->cascadeOnDelete();
    $table->enum('type', ['credit', 'debit']);
    $table->string('bank_reference');
    $table->string('bank_provider');
    $table->decimal('amount', 19, 4);
    $table->dateTime('bank_transaction_time');
    $table->json('metadata')->nullable(); // Flexible fields
    $table->timestamps();

    // === IDEMPOTENCY KEY ===
    $table->unique(['bank_provider', 'bank_reference'], 'unique_txn_provider');
});
```

### Table 4: `notifications` (Notification Records)

**Satisfies FR-10 to FR-15.** Tracks all notification attempts with polymorphic relationships.

```php
Schema::create('notifications', function (Blueprint $table) {
    $table->id();
    $table->enum('channel', ['email', 'sms', 'push']);
    $table->enum('dispatch_mode', ['sync', 'async']); // Derived from channel
    $table->string('recipient');                       // Email, phone, or device token
    $table->morphs('notifiable');                      // notifiable_type + notifiable_id
    $table->enum('status', ['pending', 'sent', 'failed'])->default('pending');
    $table->text('message');
    $table->text('error_message')->nullable();
    $table->timestamp('sent_at')->nullable();
    $table->timestamps();

    $table->index(['status', 'channel']);
    $table->index('created_at');
});
```

---

## 5. API Contract

### 5.1 Ingestion Endpoint

- **URL:** `POST /api/v1/webhooks/{bank_name}`
- **Headers:** `Content-Type: text/plain`
- **Response:** `202 Accepted` (Queued).

**Sample PayTech Payload:**
```text
20250615156,50#202506159000001#note/debt payment march/internal_reference/A462JE81
```

**Behavior:**
1. API accepts raw text and stores in `webhook_calls` table.
2. `ProcessWebhookJob` is queued.
3. Job parses, creates `transactions`, updates `wallets`.
4. On success: `NotificationDispatcher` sends notification (FR-10).

### 5.2 XML Payout Endpoint

- **URL:** `POST /api/v1/payouts/xml`
- **Headers:** `Content-Type: application/json`, `Accept: application/xml`
- **Response:** `200 OK` (XML Body).

**Sample XML Output:**
```xml
<PaymentRequestMessage>
    <TransferInfo>
        <Date>2025-02-25 06:33:00+03</Date>
        <Amount>177.39</Amount>
        <Currency>SAR</Currency>
        <Notes>Test Payment</Notes>
        <PaymentType>1</PaymentType>
        <ChargeDetails>OUR</ChargeDetails>
    </TransferInfo>
</PaymentRequestMessage>
```

**Behavior:**
1. API accepts JSON, validates required fields.
2. `XmlGeneratorService` generates XML with conditional tags.
3. On success: `NotificationDispatcher` sends payout notification (FR-10).

---

## 6. Architecture Patterns

### 6.1 Strategy Pattern (Parsers) - FR-01

```
┌──────────────────┐
│ WebhookParserInterface │
│  + parse(string)  │
└─────────┬────────┘
          │
    ┌─────┴─────┐
    │           │
    v           v
┌────────┐  ┌────────┐
│PayTech │  │ Acme   │
│Parser  │  │ Parser │
└────────┘  └────────┘
```

**Why Strategy?** Each bank has completely different formats. Adding new banks = 1 new class + 1 factory entry.

### 6.2 Factory Method Pattern (Notifications) - FR-12

```
┌──────────────────────────────┐
│       NotificationSender      │
│      (Interface/Contract)     │
│  + send(NotificationPayload) │
└──────────────┬───────────────┘
               │
     ┌─────────┼─────────┐
     │         │         │
     v         v         v
┌────────┐ ┌────────┐ ┌────────┐
│ Email  │ │  SMS   │ │  Push  │
│ Sender │ │ Sender │ │ Sender │
│(async) │ │(async) │ │(sync)  │
└────────┘ └────────┘ └────────┘
     ▲         ▲         ▲
     │         │         │
     └─────────┼─────────┘
               │
     ┌─────────┴─────────┐
     │ NotificationFactory│
     │  + make(channel)   │
     └────────────────────┘
```

**Why Factory Method?**
- Client code depends only on `NotificationSender` interface
- Adding new channel = 1 new class + 1 factory entry
- All senders interchangeable at runtime

### 6.3 Dispatch Flow (Sync vs Async) - FR-14

```
┌─────────────────────────────────────────────────────────────┐
│                    Notification Flow                         │
├─────────────────────────────────────────────────────────────┤
│                                                             │
│  Transaction Success ──┐                                    │
│  Transaction Failure ──┼──> NotificationDispatcher          │
│  Payout Generated    ──┘         │                         │
│                                  │                         │
│                    ┌─────────────┴─────────────┐           │
│                    │                           │           │
│                    v                           v           │
│            ┌──────────────┐           ┌──────────────┐     │
│            │ Push (Sync)  │           │ Email/SMS    │     │
│            │ PushSender   │           │ Queue Job    │     │
│            │  (inline)    │           │ (async)      │     │
│            └──────────────┘           └──────────────┘     │
│                    │                           │           │
│                    v                           v           │
│            ┌──────────────┐           ┌──────────────┐     │
│            │ Notification │           │ Notification │     │
│            │   Factory    │           │   Factory    │     │
│            └──────────────┘           └──────────────┘     │
│                    │                           │           │
│                    v                           v           │
│            ┌──────────────┐           ┌──────────────┐     │
│            │ PushSender   │           │ EmailSender  │     │
│            │  (inline)    │           │ SmsSender    │     │
│            └──────────────┘           └──────────────┘     │
│                                                             │
└─────────────────────────────────────────────────────────────┘
```

---

## 7. Non-Functional Requirements (NFR)

| ID | Category | Requirement |
|----|----------|-------------|
| NFR-01 | Methodology | **Strict TDD.** Tests must be written before implementation. |
| NFR-02 | Performance | System must handle a test case with 1,000 transactions in a single webhook efficiently (Bulk Inserts). |
| NFR-03 | Architecture | Event-Driven/Queued architecture is mandatory to support non-blocking ingestion. |
| NFR-04 | Code Quality | PSR-12 standards. No debug code (`dd`, `dump`) in final submission. |
| NFR-05 | Documentation | README.md must explain the TDD approach and design decisions. |
| NFR-06 | Architecture | **Factory Method pattern is mandatory** for notifications. Direct instantiation of concrete senders in client code is a violation. |
| NFR-07 | Methodology | All notification logic must follow the TDD Red-Green-Refactor cycle, consistent with NFR-01. |
| NFR-08 | Resilience | A notification failure must **never** affect the outcome of a transaction, regardless of dispatch mode. Async jobs (Email/SMS) may retry up to 3 times before marking status as `failed`. Synchronous push failures are caught, logged, and surfaced as a warning — they must not throw unhandled exceptions. |
| NFR-09 | Extensibility | Adding a new channel must require only: (1) a new class implementing `NotificationSender`, (2) one line in the factory. No changes to existing code. |

---

## 8. Testing Strategy (TDD Implementation)

To fulfill **NFR-01** and **NFR-07**, the following tests are required:

### 8.1 Module A Tests: Ingestion

| Test | Type | Description |
|------|------|-------------|
| `PayTechParserTest` | Unit | Feed raw string, assert correct Amount/Reference/Metadata extracted. |
| `AcmeParserTest` | Unit | Feed raw string, assert correct Amount/Reference/Date extracted. |
| `WebhookParserFactoryTest` | Unit | Assert factory returns correct parser for each bank. |
| `WebhookParserInterfaceTest` | Unit | Assert interface contract is defined correctly. |
| `WebhookIngestionTest` | Feature | Post to API → Assert 202 → Assert DB `webhook_calls` has record. |
| `ProcessWebhookJobTest` | Feature | Process webhook → Assert transactions created → Assert wallet updated. |
| `BulkPerformanceTest` | Feature | Process payload with 1,000 lines → Assert 1,000 records created → Assert execution time < 2 seconds. |

### 8.2 Module B Tests: Payouts

| Test | Type | Description |
|------|------|-------------|
| `XmlGeneratorTest` | Unit | Feed JSON with/without optional fields → Assert XML tags appear/disappear. |
| `PayoutEndpointTest` | Feature | Post JSON → Assert 200 → Assert valid XML returned. |

### 8.3 Module C Tests: Notifications

| Test | Type | Description |
|------|------|-------------|
| `NotificationsMigrationTest` | Feature | Assert table has all required columns and enum constraints. |
| `NotificationPayloadTest` | Unit | Assert DTO validates channel/type, determines dispatch mode. |
| `NotificationSenderInterfaceTest` | Unit | Assert interface defines `send(Payload)` method. |
| `UnsupportedNotificationChannelTest` | Unit | Assert exception message lists supported channels. |
| `NotificationFactoryTest` | Unit | Given `'email'`, assert factory returns `EmailSender`. Repeat for `sms`, `push`. Assert unknown channel throws exception. |
| `NotificationSenderTest` | Unit | Mock mail/gateway; assert `send()` creates notification record with correct status. |
| `SendNotificationJobTest` | Feature | Assert job implements `ShouldQueue`, uses factory correctly. |
| `NotificationDispatchTest` | Feature | Process transaction → Assert notification job dispatched for Email/SMS. Assert Push called inline (no job). |
| `NotificationResilienceTest` | Feature | Force `send()` to throw → Assert transaction record unaffected and notification status is `'failed'`. |

---

## 9. Implementation Checklist

### Module A: Ingestion
- [x] `wallets` table migration
- [x] `webhook_calls` table migration (buffer)
- [x] `transactions` table migration (with idempotency constraint)
- [x] `WebhookParserInterface` (Strategy Pattern)
- [x] `PayTechParser` implementation
- [x] `AcmeParser` implementation
- [x] `WebhookParserFactory`
- [x] `ProcessWebhookJob` (queued)
- [x] Webhook ingestion API endpoint
- [x] All tests passing (TDD)

### Module B: Payouts
- [x] `XmlGeneratorService`
- [x] Payout API endpoint
- [x] Conditional tag logic (Notes, PaymentType, ChargeDetails)
- [x] All tests passing (TDD)

### Module C: Notifications
- [x] `notifications` table migration
- [x] `Notification` model with status helpers
- [x] `NotificationPayload` DTO with validation
- [x] `NotificationSender` interface
- [x] `UnsupportedNotificationChannel` exception
- [x] `EmailSender` implementation (async)
- [x] `SmsSender` implementation (async)
- [x] `PushSender` implementation (sync)
- [x] `NotificationFactory` (Factory Method pattern)
- [x] `NotificationDispatcher` service
- [x] `SendNotificationJob` (queued)
- [x] Integration with `ProcessWebhookJob`
- [x] Integration with `PayoutController`
- [x] All tests passing (TDD)

---

## 10. Test Results Summary

```
Tests:    130 passed (1293 assertions)
Duration: 2.89s
```

### Test Breakdown by Module:

| Module | Tests | Assertions |
|--------|-------|------------|
| Module A: Ingestion | 52 | ~400 |
| Module B: Payouts | 11 | ~80 |
| Module C: Notifications | 52 | ~600 |
| Shared Infrastructure | 15 | ~213 |

---

## 11. File Structure

```
app/
├── Contracts/
│   └── NotificationSender.php          # Interface (FR-11)
├── DTO/
│   ├── NotificationPayload.php         # DTO (FR-13)
│   └── ParsedTransaction.php
├── Exceptions/
│   └── UnsupportedNotificationChannel.php
├── Http/Controllers/Api/
│   ├── PayoutController.php            # FR-07, integrated notifications
│   └── WebhookController.php
├── Jobs/
│   ├── ProcessWebhookJob.php           # Integrated notifications (FR-10)
│   └── SendNotificationJob.php         # Async dispatch (FR-14)
├── Models/
│   ├── Notification.php
│   ├── Transaction.php
│   ├── Wallet.php
│   └── WebhookCall.php
└── Services/
    ├── Notifications/
    │   ├── EmailSender.php             # Async sender (FR-14)
    │   ├── SmsSender.php               # Async sender (FR-14)
    │   ├── PushSender.php              # Sync sender (FR-14)
    │   ├── NotificationFactory.php     # Factory Method (FR-12)
    │   └── NotificationDispatcher.php  # Dispatch logic (FR-14)
    ├── Parsers/
    │   ├── WebhookParserInterface.php
    │   ├── PayTechParser.php
    │   ├── AcmeParser.php
    │   └── WebhookParserFactory.php
    └── XmlGeneratorService.php

database/migrations/
├── 0001_01_01_000000_create_users_table.php
├── 0001_01_01_000001_create_cache_table.php
├── 0001_01_01_000002_create_jobs_table.php
├── 2026_01_20_153337_create_wallets_table.php
├── 2026_01_20_154132_create_webhook_calls_table.php
├── 2026_01_20_154449_create_transactions_table.php
└── 2026_01_20_200000_create_notifications_table.php

tests/Feature/
├── NotificationsMigrationTest.php
├── NotificationPayloadTest.php
├── NotificationSenderInterfaceTest.php
├── UnsupportedNotificationChannelTest.php
├── NotificationFactoryTest.php
├── NotificationSenderTest.php
├── SendNotificationJobTest.php
├── NotificationDispatchTest.php
├── ... (other tests)
```

---

*BRD v4.0 — Digital Wallet with Notification System · January 20, 2026*
