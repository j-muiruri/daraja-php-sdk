# Changelog

All notable changes to this project will be documented in this file.
Format follows [Keep a Changelog](https://keepachangelog.com/en/1.0.0/).
This project adheres to [Semantic Versioning](https://semver.org/).

## [Unreleased]

## [1.0.0] - 2025-06-30

### Added
- Full Daraja 3.0 API coverage: OAuth, STK Push, STK Query, C2B, B2C, B2B,
  Transaction Status, Account Balance, Reversal, Dynamic QR,
  Tax Remittance, and Bill Manager
- Typed webhook payload DTOs for all callback types (STKCallback, C2BConfirmation,
  C2BValidation, B2CResult, B2BResult, AccountBalanceResult, TransactionStatusResult,
  ReversalResult, BillManagerReconciliation)
- `CallbackProcessor` with fluent handler registration and auto-detection
- Pluggable token caching (TokenCacheInterface, InMemoryTokenCache, RedisTokenCache)
- Laravel 10/11/12 integration: DarajaServiceProvider, Mpesa facade, VerifyMpesaIp
  middleware, MpesaWebhookController with 16 auto-registered routes, and 9 typed events
- Artisan commands: `mpesa:generate-credential`, `mpesa:register-urls`, `mpesa:check-balance`
- `PhoneNumber` value object — normalises all Kenyan phone formats to E.164
- `Invoice` and `InvoiceItem` value objects for Bill Manager
- `BalanceLine` result parser for Account Balance pipe-delimited string
- PHPUnit test suite with unit and feature tests
- PHPStan level 8 static analysis configuration
- GitHub Actions CI (PHP 8.2 and 8.3) and release workflows
- MIT license
