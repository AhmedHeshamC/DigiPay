# DigiPay Digital Wallet - Detailed Task Breakdown

**Status:** 📋 For Discussion
**TDD Methodology:** Red (Write Test) → Green (Make Pass) → Refactor

---

## Phase 1: Foundation & Infrastructure (Tasks 1-8)

| Task | Description | Files | TDD Test |
|------|-------------|-------|----------|
| **T-01** | Create `wallets` migration with schema (id, owner_name, email, balance, currency, timestamps) | `database/migrations/xxxx_create_wallets_table.php` | WalletMigrationTest |
| **T-02** | Create `webhook_calls` migration (buffer table: id, bank_provider, payload, status, error_message) | `database/migrations/xxxx_create_webhook_calls_table.php` | WebhookCallsMigrationTest |
| **T-03** | Create `transactions` migration with unique constraint for idempotency | `database/migrations/xxxx_create_transactions_table.php` | TransactionsMigrationTest |
| **T-04** | Create Wallet model with fillable fields and casts | `app/Models/Wallet.php` | WalletModelTest |
| **T-05** | Create WebhookCall model with fillable fields | `app/Models/WebhookCall.php` | WebhookCallModelTest |
| **T-06** | Create Transaction model with wallet relationship | `app/Models/Transaction.php` | TransactionModelTest |
| **T-07** | Configure queue driver (database or redis) | `.env`, `config/queue.php` | QueueConfigTest |
| **T-08** | Create database seeder for default wallet (ID: 1) | `database/seeders/DatabaseSeeder.php` | WalletSeederTest |

---

## Phase 2: Parser Strategy Pattern (Tasks 9-14)

| Task | Description | Files | TDD Test |
|------|-------------|-------|----------|
| **T-09** | Create `WebhookParserInterface` with `parse(string $payload): array` method | `app/Services/Parsers/WebhookParserInterface.php` | InterfaceExistsTest |
| **T-10** | Create `PayTechParser` - parse format: `date,amount#ref#key1/value1/key2/value2` | `app/Services/Parsers/PayTechParser.php` | **PayTechParserTest** (Unit) |
| **T-11** | Create `AcmeParser` - parse format: delimiter `//` | `app/Services/Parsers/AcmeParser.php` | **AcmeParserTest** (Unit) |
| **T-12** | Create `WebhookParserFactory` / `StrategySelector` | `app/Services/Parsers/WebhookParserFactory.php` | ParserFactoryTest |
| **T-13** | Create DTO/ValueObject `ParsedTransaction` | `app/DTO/ParsedTransaction.php` | ParsedTransactionTest |
| **T-14** | Handle multi-line payloads (bulk parsing) | Parsers | BulkParsingTest |

**Sample PayTech Payload for T-10:**
```
20250615156,50.00#202506159000001#note/debt payment/internal_reference/A462JE81
```
Expected output: `{ amount: 50.00, reference: "202506159000001", metadata: { note: "debt payment", internal_reference: "A462JE81" } }`

---

## Phase 3: Ingestion API & Job Processing (Tasks 15-21)

| Task | Description | Files | TDD Test |
|------|-------------|-------|----------|
| **T-15** | Create `ProcessWebhookJob` - dispatches to queue | `app/Jobs/ProcessWebhookJob.php` | ProcessWebhookJobTest |
| **T-16** | Create `WebhookController` - POST `/api/v1/webhooks/{bank}` endpoint | `app/Http/Controllers/Api/WebhookController.php` | **WebhookIngestionTest** (Feature) |
| **T-17** | API route registration with `{bank}` validation | `routes/api.php` | RouteRegistrationTest |
| **T-18** | Request validation for bank name (paytech/acme only) | `app/Http/Requests/WebhookRequest.php` | WebhookRequestValidationTest |
| **T-19** | Store raw payload to `webhook_calls` (status: pending) | Controller/WebhookCall | WebhookBufferStorageTest |
| **T-20** | Job processes webhook: select strategy → parse → insert to transactions | `ProcessWebhookJob` | JobProcessingTest |
| **T-21** | Update webhook_call status (pending → processed/failed) | `ProcessWebhookJob` | StatusUpdateTest |

**T-16 API Specification:**
```php
POST /api/v1/webhooks/{bank_name}
Headers: Content-Type: text/plain
Response: 202 Accepted
```

---

## Phase 4: Idempotency & Wallet Integration (Tasks 22-25)

| Task | Description | Files | TDD Test |
|------|-------------|-------|----------|
| **T-22** | Implement idempotency check using unique constraint (bank_provider + bank_reference) | Job/Transaction | **IdempotencyTest** (Feature) |
| **T-23** | Link transactions to wallet_id = 1 (default) | Job/Transaction | WalletLinkingTest |
| **T-24** | Update wallet balance on credit | Job/Transaction | WalletBalanceUpdateTest |
| **T-25** | Handle duplicate gracefully (skip, no error) | Job/Transaction | DuplicateHandlingTest |

**T-22 Idempotency Test:**
1. Send webhook with reference "REF123"
2. Verify 1 transaction created
3. Send same webhook again
4. Verify still only 1 transaction exists

---

## Phase 5: XML Payout Module (Tasks 26-30)

| Task | Description | Files | TDD Test |
|------|-------------|-------|----------|
| **T-26** | Create `XmlGeneratorService` | `app/Services/XmlGeneratorService.php` | **XmlGeneratorTest** (Unit) |
| **T-27** | Generate XML with required fields (Date, Amount, Currency) | Service | XmlRequiredFieldsTest |
| **T-28** | Conditional logic: omit `<Notes>` if empty | Service | XmlConditionalNotesTest |
| **T-29** | Conditional logic: omit `<PaymentType>` if 99 | Service | XmlConditionalPaymentTypeTest |
| **T-30** | Conditional logic: omit `<ChargeDetails>` if SHA | Service | XmlConditionalChargeDetailsTest |

**T-26 XML Generator Input/Output:**
```json
Input:  { "date": "2025-02-25 06:33:00+03", "amount": 177.39, "currency": "SAR" }
Output: <PaymentRequestMessage>...</PaymentRequestMessage>
```

---

## Phase 6: Payout API Endpoint (Tasks 31-34)

| Task | Description | Files | TDD Test |
|------|-------------|-------|----------|
| **T-31** | Create `PayoutController` - POST `/api/v1/payouts/xml` | `app/Http/Controllers/Api/PayoutController.php` | PayoutEndpointTest |
| **T-32** | Request validation for payout payload | `app/Http/Requests/PayoutRequest.php` | PayoutValidationTest |
| **T-33** | Set response headers: Content-Type: application/xml, charset=UTF-8 | Controller | ResponseHeadersTest |
| **T-34** | API route registration | `routes/api.php` | PayoutRouteTest |

---

## Phase 7: Performance & Bulk Processing (Tasks 35-37)

| Task | Description | Files | TDD Test |
|------|-------------|-------|----------|
| **T-35** | Implement bulk insert for transactions | Job/Transaction | BulkInsertTest |
| **T-36** | Optimize parser for 1000+ line payloads | Parsers | **BulkPerformanceTest** (Feature) |
| **T-37** | Add database indexing if needed | Migrations | QueryPerformanceTest |

---

## Phase 8: Documentation & Finalization (Tasks 38-40)

| Task | Description | Files | TDD Test |
|------|-------------|-------|----------|
| **T-38** | Create comprehensive README.md | `README.md` | N/A |
| **T-39** | Remove all debug code (dd, dump, var_dump) | All files | CodeQualityCheck |
| **T-40** | Run full test suite and ensure 100% coverage on core logic | All | CoverageReport |

---

## Summary by Module

| Phase | Tasks | Domain |
|-------|-------|--------|
| 1 | T-01 to T-08 | Database & Models |
| 2 | T-09 to T-14 | Parser Strategy Pattern |
| 3 | T-15 to T-21 | Ingestion API & Queue |
| 4 | T-22 to T-25 | Idempotency & Wallets |
| 5 | T-26 to T-30 | XML Generation |
| 6 | T-31 to T-34 | Payout API |
| 7 | T-35 to T-37 | Performance |
| 8 | T-38 to T-40 | Documentation |

**Total: 40 Tasks**

---

## For Discussion

Please review and provide feedback on:

1. **Task Granularity:** Are tasks too large/small? Should any be split further?
2. **Task Ordering:** Does the sequence make sense? Any dependencies missed?
3. **Missing Tasks:** Are there any requirements from the BRD not covered?
4. **TDD Coverage:** Are all the critical tests identified (marked in bold)?
