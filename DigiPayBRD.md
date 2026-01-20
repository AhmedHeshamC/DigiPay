# Business Requirement Document (BRD)

**Project:** Digital Wallet Coding Challenge
**Version:** 3.0 (Final with TDD)
**Date:** January 20, 2026

---

## 1. Executive Summary

The objective is to develop a robust back-end module for a Digital Wallet Application. The system acts as a middleware between banking infrastructure and an internal ledger, responsible for ingesting money (handling raw webhook streams) and facilitating payouts (generating standardized XML).

Key drivers for this project are **Idempotency** (preventing duplicate entries), **Resilience** (zero data loss), and **Code Reliability**. To ensure the highest level of stability and maintainability, this project will strictly adhere to the **Test-Driven Development (TDD)** methodology.

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
- **Commits:** The Git commit history should ideally reflect the progression of adding a test, then adding the feature.
- **Coverage:** 100% of the core business logic (Parsers, XML Generation, Idempotency checks) must be covered by automated tests.

---

## 3. Functional Requirements

### 3.1 Module A: Ingestion (Receiving Money)

| ID | Requirement | Description |
|----|-------------|-------------|
| FR-01 | Multi-Provider Support | Accept webhooks from "PayTech" and "Acme". The architecture must allow adding new banks easily (Strategy Pattern). |
| FR-02 | Raw Data Handling | The API must accept `text/plain` payloads containing CSV-like or custom delimited strings. |
| FR-03 | Resilient Buffering | **Crucial:** The system must accept and store incoming webhooks immediately to a buffer table, ensuring no data is dropped even if workers are stopped. |
| FR-04 | Bulk Parsing | A single webhook request may contain multiple transaction lines. The system must process all of them. |
| FR-05 | Idempotency | If a bank sends the same transaction reference twice, the system must ignore the duplicate to prevent double-crediting. |
| FR-06 | Client Linking | All transactions must be linked to a client wallet. For this challenge, link to a Default Seeded Wallet (ID: 1). |

### 3.2 Module B: Payouts (Sending Money)

| ID | Requirement | Description |
|----|-------------|-------------|
| FR-07 | XML Generation | Generate an XML file compliant with the provided schema based on JSON input. |
| FR-08 | Conditional Tags | - Omit `<Notes>` if empty.<br>- Omit `<PaymentType>` if 99.<br>- Omit `<ChargeDetails>` if SHA. |
| FR-09 | Output Format | The response must be `application/xml`, encoded in UTF-8. |

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
    </TransferInfo>
    <!-- Conditional Logic Applied Here -->
</PaymentRequestMessage>
```

---

## 6. Technical Workflows

### 6.1 Ingestion (Async)

1. **API:** Receives Request → Saves to `webhook_calls` → Returns 202.
2. **Job:** Dispatched to Queue.
3. **Worker:**
   - Reads `webhook_calls`.
   - Selects Strategy (PayTech vs Acme).
   - Parses text to Objects.
   - DB Insert: Uses `INSERT IGNORE` or upsert on `transactions` table.
4. **Handling Duplicates:** If unique key collision, the row is skipped (Idempotency achieved).

### 6.2 Parsing Logic

- **PayTech:** Delimiters are `,` and `#`. Key-Value pairs use `/`.
- **Acme:** Delimiter is `//`.

---

## 7. Non-Functional Requirements (NFR)

| ID | Category | Requirement |
|----|----------|-------------|
| NFR-01 | Methodology | **Strict TDD.** Tests must be written before implementation. |
| NFR-02 | Performance | System must handle a test case with 1,000 transactions in a single webhook efficiently (Bulk Inserts). |
| NFR-03 | Architecture | Event-Driven/Queued architecture is mandatory to support non-blocking ingestion. |
| NFR-04 | Code Quality | PSR-12 standards. No debug code (`dd`, `dump`) in final submission. |
| NFR-05 | Documentation | README.md must explain the TDD approach and design decisions. |

---

## 8. Testing Strategy (TDD Implementation)

To fulfill **NFR-01**, the following tests are required:

### Unit Tests (The "Red" Phase starts here):

| Test | Description |
|------|-------------|
| `PayTechParserTest` | Feed raw string, assert correct Amount/Reference/Metadata extracted. |
| `AcmeParserTest` | Feed raw string, assert correct Amount/Reference/Date extracted. |
| `XmlGeneratorTest` | Feed JSON with and without optional fields, assert XML tags appear/disappear. |

### Feature Tests:

| Test | Description |
|------|-------------|
| `WebhookIngestionTest` | Post to API → Assert 202 → Assert DB `webhook_calls` has record. |
| `IdempotencyTest` | Process the same webhook twice → Assert only 1 record exists in `transactions`. |
| `BulkPerformanceTest` | Process a payload with 1,000 lines → Assert 1,000 records created → Assert execution time < X seconds. |
