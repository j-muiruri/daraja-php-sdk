# Changelog

All notable changes to this project will be documented in this file.
Format follows [Keep a Changelog](https://keepachangelog.com/en/1.0.0/).
This project adheres to [Semantic Versioning](https://semver.org/).

## [Unreleased]

## [1.1.1] - 2026-08-27

### Fixed
- Removed the unused `Config` constructor dependency from `Imsi`, `SimSwap`, and
  `IotSimManagement` — all three take every parameter explicitly from the caller and never
  read `$config` internally, which PHPStan correctly flagged as a dead property
  (`Property ...::$config is never read, only written`). No public API impact: `DarajaClient`'s
  `imsi()`, `simSwap()`, and `iotSim()` accessor methods are unchanged.

## [1.1.0] - 2026-08-27

### Added
- **Experience-category batch**: `iotSim()` service (`IotSimManagement`) covering 13 operations
  across SIM lifecycle management (get all SIMs, lifecycle/customer-info queries, activation,
  suspend/resume, rename, activation trends) and messaging (send/search/filter/delete messages)
  over the `/simportal/*` product family. New `SimSubscriberOperation` enum for
  suspend/resume. Uses its own `header`/`body` response envelope — see `isSuccessful()` and
  `message()` helpers on the service, same pattern as `MpesaRatiba`.
  ⚠️ Requires the separate IoT SIM Management platform product (purchased independently of
  standard Daraja/M-Pesa access) — documented in the service docblock and README.
- ⚠️ **Not implemented — no public documentation found**: "B2B Hakikisha" and "Mobile Data
  Bundles", the two remaining Experience-category cards on Safaricom's Discover APIs page.
  Only a third-party SDK's marketing copy could be found naming these products; no concrete
  request/response schema is publicly available. Left out pending partner docs, consistent
  with the earlier decision on Mobile Number Validation / Age on Network.
- **Security-category batch**: `simSwap()` and `imsi()` services, wrapping the `Swap`
  (`POST /imsi/v2/checkATI`) and `IMSI` (`POST /imsi/v1/checkATI`) fraud/risk-check APIs.
  Both are synchronous (no callback wiring needed) and return the plain `Response` object,
  consistent with `DynamicQR`.
  ⚠️ Both are **commercial APIs** requiring a signed agreement with Safaricom before
  onboarding — documented prominently in both the service docblocks and the README.
- ⚠️ **Not implemented — no public documentation found**: "Mobile Number Validation" and
  "Age on Network", two Security-category cards listed on Safaricom's Discover APIs page.
  Unlike Swap and IMSI (verified against Safaricom's own docs), no request/response schema
  for these two could be located publicly. Rather than fabricate one, these are left out
  pending partner docs or API access — see the CHANGELOG entry for follow-up.
- **B2B Express Checkout (USSD Push to Till)** — new `b2bExpressCheckout()` service on
  `DarajaClient`, wrapping `POST /v1/ussdpush/get-msisdn`. Prompts a fellow merchant to pay
  from their own till number into your paybill.
- `B2BExpressCheckoutResult` typed webhook payload, plus `onB2BExpressCheckout()` handler and
  auto-detection support in `CallbackProcessor`.
- **B2C Account Top Up** — new `b2cAccountTopUp()` service. Moves funds from a paybill's Working
  account into a B2C shortcode's Utility account (`CommandID: BusinessPayToBulk`).
- **Business To Pochi** — new `businessToPochi()` service, paying into a customer's Pochi La
  Biashara wallet via `POST /mpesa/b2pochi/v1/paymentrequest`. Reuses the existing `B2CResult`
  webhook DTO (identical callback shape).
- **M-Pesa Ratiba (Standing Orders)** — new `mpesaRatiba()` service with `createForPayBill()`
  and `createForBuyGoods()` helpers, plus a new `Frequency` enum. Ships with its own
  `MpesaRatibaResult` webhook DTO and `onMpesaRatiba()` handler, since Ratiba's response/callback
  envelope (`ResponseHeader`/`ResponseBody`) differs from every other Daraja API in this SDK —
  see the docblock on `MpesaRatiba` for `isAccepted()`/`responseDescription()` usage.
- **Pull Transactions** — new `pullTransaction()` service with `register()` and `query()` for
  recovering C2B transactions that missed their callback. ⚠️ Safaricom's own documentation is
  internally inconsistent about the query step's HTTP method (see docblock); this SDK sends
  POST with a JSON body pending confirmation.
- Laravel integration: new `handleB2BExpressCheckout()` / `handleMpesaRatiba()` controller
  actions, `mpesa/b2b-express-checkout/callback` and `mpesa/ratiba/callback` routes, and
  `B2BExpressCheckoutResultReceived` / `MpesaRatibaResultReceived` events. B2C Account Top Up and
  Business To Pochi callbacks route through the existing B2B/B2C handlers since they share those
  payload shapes.
- This completes the Payments-category batch of previously-missing Daraja APIs identified
  against the live [Discover APIs](https://developer.safaricom.co.ke/apis) listing.

## [1.0.1] - 2026-07-07

### Added
- Links to the PHP and Laravel integration guides in the README.

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
