# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [0.2.0] - 2025-11-06

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
- **Mass Assignment Protection**: Whitelisted options arrays in `addCredit()`, `createRefund()`, `getOrCreateCustomer()`
- **Rate Limiting**: Tiered limits (60/30/10 req/min) based on operation sensitivity

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
- Workspace-based billing support
- Proration support
- Invoice generation and management
- Payment method management
- Usage-based billing support
- Comprehensive test suite
- API endpoints for plans, subscriptions, invoices, and discounts
