# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [0.4.0] - 2026-04-28

### BREAKING CHANGES

#### Money is now integer cents

All monetary columns and arithmetic now use **integer minor units (cents)** instead of `decimal(10,2)` dollars. This eliminates float precision bugs in proration, refund, and credit math, and aligns the package with Stripe's API conventions.

**Affected columns** (all migrated automatically):
- `billing_invoices`: `subtotal`, `tax`, `total`, `discount` → `bigInteger`
- `billing_invoice_items`: `unit_price`, `amount` → `bigInteger`
- `billing_plans`: `price` → `bigInteger`
- `billing_refunds`: `amount` → `bigInteger`
- `billing_customer_credits`: `amount`, `balance_before`, `balance_after` → `bigInteger` (signed)
- `billing_customers`: `credit_balance` → `bigInteger` (signed)

**Affected method signatures** (now accept and return `int` cents):
- `Customer::createRefund(int $amount, ...)`
- `Customer::addCredit(int $amount, ...)`, `Customer::deductCredit(int $amount, ...)`
- `Customer::getAvailableCredit(): int`
- `Customer::applyCreditsToInvoice(Invoice): int`
- `Invoice::refund(?int $amount = null, ...)`
- `Invoice::getRemainingRefundable(): int`, `Invoice::getTotalRefunded(): int`
- `Discount::calculateDiscount(int $amount, ...): int`
- `Discount::getDiscountedPrice(int $amount, ...): int`
- `InvoiceFactory::withAmount(int $amount)`, `InvoiceFactory::withTax(int $tax)`
- `DiscountFactory::fixed(int $amountCents, ...)`

**Discount split**: `billing_discounts.value` (a dual-purpose decimal column) was split into two clearer columns:
- `percentage` — `decimal(5,2)` nullable, range 0-100, used when `type='percentage'`
- `amount_cents` — `bigInteger` nullable, used when `type='fixed'`

**Subscription proration math** (`Subscription::previewPlanChange`) now uses integer math throughout. The `abs()` calls that masked invalid period direction have been removed in favor of an explicit guard.

#### Upgrade guide

1. Run `php artisan migrate` after upgrading; the included migration converts existing decimal data to cents (multiplies by 100 with rounding).
2. Update any application code that passes dollar amounts to package methods to pass cents instead. For instance:
   ```php
   // before
   $customer->createRefund(50.00, $invoice);
   // after
   $customer->createRefund(5000, $invoice);
   ```
3. Update any direct database writes (`Invoice::create(['total' => 100.00])`) to use cents (`['total' => 10000]`).
4. Update any output rendering that formatted prices directly from the model — divide by 100 before display, or use `Number::currency($cents / 100, ...)`.

The migration is reversible: rolling back will restore the `decimal(10,2)` schema and divide values back by 100.

### Added

#### Webhook Idempotency
- New `billing_webhook_events` table records every Stripe event ID processed by `WebhookController`
- Duplicate deliveries (Stripe retries, multi-endpoint setups, manual replay) are deduped and return 200 without re-running handlers
- Each handler invocation is wrapped in a database transaction
- Failed events leave `processed_at` null so a subsequent re-delivery will retry
- New `billing:prune-webhook-events` command and weekly schedule entry; retention configurable via `billing.webhook.retention_days` (default 90)
- New `WebhookEvent` model

#### Payment Method Expiry Warnings
- New `PaymentMethodExpiring` event carrying `PaymentMethod $paymentMethod` and `int $daysUntilExpiry`
- New `billing:check-expiring-payment-methods` command iterates card payment methods and dispatches the event when within the warning window
- Warning window configurable via `billing.payment_method_expiry.warning_days` (default 60)
- Per-card debounce: each (card, expiry-month) pair fires the event exactly once; subsequent runs are skipped unless `--force` is passed
- Auto-scheduled daily at 09:00 when `auto_register_scheduler` is enabled
- Supports `--days`, `--force`, and `--dry-run` flags

### Configuration
- New `billing.webhook.retention_days` (env: `BILLING_WEBHOOK_RETENTION_DAYS`)
- New `billing.payment_method_expiry.warning_days` (env: `BILLING_PAYMENT_METHOD_EXPIRY_WARNING_DAYS`)
- New `billing.tables.webhook_events` for table name override

### Migrations
- `2026_04_28_000000_create_billing_webhook_events_table.php`
- `2026_04_28_000010_convert_billing_money_to_cents.php`

## [0.3.1] - 2025-11-06

### Updated
- Enhanced README with comprehensive feature documentation
- Added usage examples for plan changes, customer credits, and refunds
- Reorganized features into logical categories (Subscription, Financial, Security, Developer Experience)

## [0.3.0] - 2025-11-06

### Added

#### Refund System
- Full and partial refund support for paid invoices
- Refund status tracking (pending, succeeded, failed, canceled)
- Automatic customer credit creation on successful refunds
- Prevention of over-refunding with validation
- Stripe integration for automatic refund processing
- `RefundController` API with index, show, store, and destroy endpoints
- `RefundCreated`, `RefundSucceeded`, and `RefundFailed` events
- Comprehensive refund tracking on invoices (`getTotalRefunded`, `getRemainingRefundable`, `isFullyRefunded`, `isPartiallyRefunded`)

#### Customer Credit System
- Complete credit/debit transaction tracking with running balances
- Multiple credit types: refund, promotional, manual_adjustment, invoice_payment
- Automatic credit application to invoices during generation
- Credit expiration support for promotional credits
- Balance before/after tracking for audit trails
- `CreditController` API with balance, summary, index, and show endpoints
- `CreditAdded` and `CreditApplied` events
- Customer credit balance field for quick access

#### Plan Change Logic
- Immediate and scheduled plan changes
- Proration preview with detailed cost breakdown
- Upgrade/downgrade detection based on pricing
- Plan change history tracking
- Automatic application of scheduled changes via billing:process command
- `SubscriptionController` endpoints for preview and plan changes
- `PlanChanged` and `PlanChangeScheduled` events
- Support for disabling proration per change

### Security
- **Authorization Policies**: Comprehensive policies for Refund, Credit, Subscription, and Invoice operations
- **Form Request Validation**: `CreateRefundRequest`, `ChangePlanRequest`, `PreviewPlanChangeRequest` with business logic validation
- **Idempotency Checks**: Prevents duplicate refund requests within 5-minute window
- **API Resources**: Secure response transformers excluding internal IDs, Stripe IDs, and metadata (9 resource classes)
- **Database Transactions**: All financial operations wrapped in transactions for ACID compliance
- **Mass Assignment Protection**: Whitelisted options arrays in `addCredit()`, `createRefund()`, `getOrCreateCustomer()` plus `$guarded` properties on all models
- **Rate Limiting**: Tiered limits (60/30/10 req/min) based on operation sensitivity
- **Model Security**: Added `$hidden` properties to all models to prevent sensitive data exposure (Stripe IDs, payment details)
- **Stripe Validation**: Added Stripe API key validation on service provider boot
- **Invoice Number Safety**: Fixed invoice number race condition with database locking
- **Webhook Security**: Enhanced webhook security with comprehensive error logging and monitoring
- **Input Validation**: Added input validation for subscription cancellation endpoint

### Changed
- Updated SubscriptionController to use explicit authorization checks
- Updated InvoiceController to use explicit authorization checks
- Updated RefundController to use policies, Form Requests, and API Resources
- PlanController now only exposes active plans via public API
- WebhookController now includes detailed security logging for all events and failures
- Routes file now includes CSRF exclusion documentation for webhooks

### Updated
- `CLAUDE.md` documentation with all new features, usage examples, and API endpoints
- Comprehensive test coverage (40 new tests with 83 assertions)
- Database migrations for refunds, customer credits, and plan change tracking
- Factories for Refund and CustomerCredit models
- Controllers updated to use policies, Form Requests, and API Resources
- Routes reorganized with rate limiting middleware

### Fixed
- Proration calculation now uses absolute values for date differences
- Event::fake() compatibility in tests (selective event faking)
- Invoice total recalculation after credit application
- Mass assignment vulnerabilities in financial operations

## [0.1.0] - 2025-01-05

### Added
- Initial release
- Stripe-based subscription and billing management
- Multi-plan and add-on support
- Discount system with codes and admin-applied discounts
- Trial periods and grace periods
- Workspace-based billing support (optional)
- Proration support
- Invoice generation and management
- Payment method management
- Usage-based billing support
- Webhook handling for Stripe events
- Daily billing processing command
- Comprehensive test suite with Pest
- API endpoints for plans, subscriptions, invoices, and discounts
