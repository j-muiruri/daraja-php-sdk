# Integrating j-muiruri/daraja-php-sdk in a Laravel Project

**Enterprise Integration Guide — v1.0**

---

## Table of Contents

1. [Requirements](#1-requirements)
2. [Installation](#2-installation)
3. [Configuration](#3-configuration)
4. [Security Credential Setup](#4-security-credential-setup)
5. [Using the Client](#5-using-the-client)
6. [Webhook Events — Architecture](#6-webhook-events--architecture)
7. [Creating Listeners](#7-creating-listeners)
8. [Queueing Webhook Processing](#8-queueing-webhook-processing)
9. [C2B Validation — Custom Logic](#9-c2b-validation--custom-logic)
10. [STK Push — Full Request Lifecycle](#10-stk-push--full-request-lifecycle)
11. [B2C Disbursements](#11-b2c-disbursements)
12. [B2B & Tax Remittance](#12-b2b--tax-remittance)
13. [Bill Manager](#13-bill-manager)
14. [Artisan Commands Reference](#14-artisan-commands-reference)
15. [Redis Token Cache](#15-redis-token-cache)
16. [Testing in Laravel](#16-testing-in-laravel)
17. [Production Checklist](#17-production-checklist)

---

## 1. Requirements

| Requirement | Version |
| --- | --- |
| PHP | 8.2+ |
| Laravel | 10, 11, or 12 |
| `ext-openssl` | any |
| Queue driver | database / Redis (recommended) |

---

## 2. Installation

```bash
composer require j-muiruri/daraja-php-sdk
```

The **service provider and facade auto-discover** via Composer — no manual
registration in `config/app.php` required.

Publish the configuration file:

```bash
php artisan vendor:publish --tag=daraja-config
```

This creates `config/daraja.php`.

---

## 3. Configuration

### 3.1 Environment Variables

Add to your `.env`:

```ini
MPESA_ENVIRONMENT=sandbox

MPESA_CONSUMER_KEY=your_consumer_key
MPESA_CONSUMER_SECRET=your_consumer_secret
MPESA_SHORTCODE=174379
MPESA_PASSKEY=bfb279f9aa9bdbcf158e97dd71a467cd2e0c893059b10f78e6b72ada1ed2c919

MPESA_INITIATOR_NAME=testapi
MPESA_SECURITY_CREDENTIAL=        # Generate in §4

MPESA_CALLBACK_URL="${APP_URL}/mpesa/stk/callback"
MPESA_RESULT_URL="${APP_URL}/mpesa/b2c/result"
MPESA_TIMEOUT_URL="${APP_URL}/mpesa/b2c/timeout"
```

### 3.2 config/daraja.php

After publishing, review every setting:

```php
// Key settings to verify:
'environment'      => env('MPESA_ENVIRONMENT', 'sandbox'),
'callback_url'     => env('MPESA_CALLBACK_URL'),    // STK Push callbacks
'result_url'       => env('MPESA_RESULT_URL'),       // B2C/B2B/Reversal/Balance
'timeout_url'      => env('MPESA_TIMEOUT_URL'),      // Async timeouts

// Route settings — disable auto-registration if you define routes manually
'routes' => [
    'register_routes' => true,
    'prefix'          => 'mpesa',      // All routes: POST /mpesa/stk/callback, etc.
    'middleware'       => ['api'],
],
```

### 3.3 Exclude Webhook Routes from CSRF

Laravel's `api` middleware group already disables CSRF — no action needed.

If your webhook routes use the `web` middleware, exclude them in
`app/Http/Middleware/VerifyCsrfToken.php`:

```php
protected $except = [
    'mpesa/*',
];
```

---

## 4. Security Credential Setup

Required before using B2C, B2B, Reversal, or Account Balance.

**Step 1:** Download Safaricom certificates:

```bash
mkdir -p certs
# Sandbox
curl -o certs/safaricom_sandbox.cer \
  "https://developer.safaricom.co.ke/sites/default/files/cert/sandbox/cert.cer"
# Production
curl -o certs/safaricom_production.cer \
  "https://developer.safaricom.co.ke/sites/default/files/cert/prod/cert.cer"
```

**Step 2:** Run the Artisan command:

```bash
php artisan mpesa:generate-credential
```

The command will prompt for your initiator password and certificate path,
then offer to write the result directly into your `.env`.

Or with options:

```bash
php artisan mpesa:generate-credential \
  --password=MyInitiatorPassword \
  --cert=certs/safaricom_sandbox.cer \
  --env
```

---

## 5. Using the Client

### 5.1 Dependency Injection (Recommended)

```php
<?php

namespace App\Http\Controllers;

use Daraja\DarajaClient;
use Daraja\Exceptions\ApiException;
use Daraja\Exceptions\ValidationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class PaymentController extends Controller
{
    public function __construct(private readonly DarajaClient $mpesa) {}

    public function initiateSTK(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'phone'    => 'required|string',
            'amount'   => 'required|integer|min:1',
            'order_id' => 'required|string',
        ]);

        try {
            $response = $this->mpesa->stk()->push(
                phone:            $validated['phone'],
                amount:           (int) $validated['amount'],
                accountReference: $validated['order_id'],
                description:      'Order Payment',
            );

            if ($response->isAccepted()) {
                Order::where('id', $validated['order_id'])
                    ->update(['mpesa_checkout_id' => $response->checkoutRequestId()]);

                return response()->json([
                    'status'  => 'pending',
                    'message' => 'Check your phone and enter your M-Pesa PIN',
                ]);
            }

            return response()->json(['status' => 'rejected'], 422);

        } catch (ValidationException $e) {
            return response()->json(['errors' => $e->errors()], 400);
        } catch (ApiException $e) {
            report($e);
            return response()->json(['message' => 'Payment gateway error'], 502);
        }
    }
}
```

### 5.2 Via the Facade

```php
use Daraja\Laravel\Facades\Mpesa;

$response = Mpesa::stk()->push('0712345678', 1500, 'INV-001');
$response = Mpesa::b2c()->sendSalary('0712345678', 45000);
$response = Mpesa::accountBalance()->query();
```

### 5.3 Via the Container

```php
$mpesa = app(DarajaClient::class);
$mpesa->reversal()->reverse('QHT3XXXXXXXXXXX', 1500);
```

---

## 6. Webhook Events — Architecture

The SDK auto-registers all Daraja callback routes and dispatches typed Laravel
events for each one. Your application reacts by registering event listeners.

**Registered routes** (all under the configured prefix, default `/mpesa`):

| Route | Event Dispatched |
| --- | --- |
| `POST /mpesa/stk/callback` | `STKCallbackReceived` |
| `POST /mpesa/c2b/validation` | `C2BValidationReceived` |
| `POST /mpesa/c2b/confirmation` | `C2BConfirmationReceived` |
| `POST /mpesa/b2c/result` | `B2CResultReceived` |
| `POST /mpesa/b2b/result` | `B2BResultReceived` |
| `POST /mpesa/balance/result` | `AccountBalanceReceived` |
| `POST /mpesa/status/result` | `TransactionStatusReceived` |
| `POST /mpesa/reversal/result` | `ReversalResultReceived` |
| `POST /mpesa/bill/reconciliation` | `BillManagerReconciliationReceived` |
| `POST /mpesa/tax/result` | `B2BResultReceived` |
| `POST /mpesa/*/timeout` | Logged (no event) |

---

## 7. Creating Listeners

### 7.1 Register in EventServiceProvider

```php
// app/Providers/EventServiceProvider.php

use Daraja\Laravel\Events\AccountBalanceReceived;
use Daraja\Laravel\Events\B2BResultReceived;
use Daraja\Laravel\Events\B2CResultReceived;
use Daraja\Laravel\Events\BillManagerReconciliationReceived;
use Daraja\Laravel\Events\C2BConfirmationReceived;
use Daraja\Laravel\Events\C2BValidationReceived;
use Daraja\Laravel\Events\ReversalResultReceived;
use Daraja\Laravel\Events\STKCallbackReceived;
use Daraja\Laravel\Events\TransactionStatusReceived;

protected $listen = [
    STKCallbackReceived::class => [
        Listeners\HandleSTKCallback::class,
        Listeners\SendPaymentReceipt::class,     // multiple listeners supported
    ],
    C2BConfirmationReceived::class => [
        Listeners\RecordC2BPayment::class,
    ],
    B2CResultReceived::class => [
        Listeners\UpdateDisbursementStatus::class,
    ],
    B2BResultReceived::class => [
        Listeners\UpdateSupplierPayment::class,
    ],
    AccountBalanceReceived::class => [
        Listeners\LogAccountBalance::class,
    ],
    ReversalResultReceived::class => [
        Listeners\ProcessRefund::class,
    ],
    BillManagerReconciliationReceived::class => [
        Listeners\MarkInvoicePaid::class,
    ],
];
```

### 7.2 STK Push Listener

```bash
php artisan make:listener HandleSTKCallback
```

```php
<?php

namespace App\Listeners;

use App\Models\Order;
use App\Notifications\PaymentReceived;
use Daraja\Laravel\Events\STKCallbackReceived;
use Daraja\Webhooks\Payloads\STKCallback;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

final class HandleSTKCallback implements ShouldQueue
{
    use InteractsWithQueue;

    // Retry up to 3 times on failure
    public int $tries = 3;
    public int $backoff = 60;

    public function handle(STKCallbackReceived $event): void
    {
        $cb = $event->callback;

        $order = Order::where('mpesa_checkout_id', $cb->checkoutRequestId)->first();

        if (!$order) {
            return; // Unknown transaction — log and ignore
        }

        // Idempotency guard
        if ($order->status === 'paid') {
            return;
        }

        if ($cb->isSuccessful()) {
            $order->update([
                'status'        => 'paid',
                'mpesa_receipt' => $cb->receiptNumber,
                'amount_paid'   => $cb->amount,
                'paid_at'       => $cb->transactionDate ?? now(),
            ]);

            $order->customer->notify(new PaymentReceived($order, $cb->receiptNumber));

        } elseif ($cb->wasCancelled()) {
            $order->update(['status' => 'payment_cancelled']);

        } elseif ($cb->hadInsufficientFunds()) {
            $order->update(['status' => 'payment_failed_insufficient_funds']);
        } else {
            $order->update([
                'status'         => 'payment_failed',
                'failure_reason' => $cb->resultDescription(),
            ]);
        }
    }

    public function failed(STKCallbackReceived $event, \Throwable $e): void
    {
        \Log::error('STK callback listener failed', [
            'checkout_id' => $event->callback->checkoutRequestId,
            'error'       => $e->getMessage(),
        ]);
    }
}
```

### 7.3 C2B Confirmation Listener

```php
<?php

namespace App\Listeners;

use App\Models\Payment;
use Daraja\Laravel\Events\C2BConfirmationReceived;
use Illuminate\Contracts\Queue\ShouldQueue;

final class RecordC2BPayment implements ShouldQueue
{
    public function handle(C2BConfirmationReceived $event): void
    {
        $cb = $event->callback;

        // Idempotency — M-Pesa may occasionally send duplicates
        Payment::firstOrCreate(
            ['transaction_id' => $cb->transactionId],
            [
                'amount'        => $cb->amount,
                'phone'         => $cb->msisdn,
                'account_ref'   => $cb->billRefNumber,
                'customer_name' => $cb->customerFullName(),
                'type'          => $cb->isPayBill() ? 'paybill' : 'buy_goods',
                'paid_at'       => $cb->transactionTime,
            ]
        );
    }
}
```

---

## 8. Queueing Webhook Processing

Daraja requires **HTTP 200 within 5 seconds**. The `MpesaWebhookController`
already returns 200 before dispatching events. To ensure listeners don't block
the event loop, implement `ShouldQueue` on all listeners.

### Configure the Queue

```bash
# .env
QUEUE_CONNECTION=redis  # or database
```

```bash
# Run the queue worker (use Supervisor in production)
php artisan queue:work redis --queue=mpesa --tries=3 --backoff=60
```

### Dispatch to a Dedicated Queue

```php
// In your listener
public string $queue = 'mpesa';
```

### Supervisor Configuration

```ini
; /etc/supervisor/conf.d/mpesa-worker.conf
[program:mpesa-queue]
command=php /var/www/html/artisan queue:work redis --queue=mpesa --sleep=3 --tries=3 --backoff=60 --max-time=3600
directory=/var/www/html
user=www-data
autostart=true
autorestart=true
numprocs=2
redirect_stderr=true
stdout_logfile=/var/log/mpesa-worker.log
stopwaitsecs=3600
```

---

## 9. C2B Validation — Custom Logic

The SDK returns `C2BValidation::accept()` by default. Override this in a
subclass of `MpesaWebhookController`:

```php
<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use Daraja\Laravel\Http\Controllers\MpesaWebhookController as BaseController;
use Daraja\Webhooks\Payloads\C2BValidation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class MpesaWebhookController extends BaseController
{
    public function handleC2BValidation(Request $request): JsonResponse
    {
        try {
            $cb       = $this->processor->parseC2BValidation($request->getContent());
            $customer = Customer::where('account_ref', $cb->billRefNumber)->first();

            if (!$customer) {
                return response()->json(C2BValidation::reject(
                    C2BValidation::REJECT_CODE_INVALID_ACCOUNT,
                    'Account number not found',
                ));
            }

            if ($cb->amount < $customer->minimum_payment) {
                return response()->json(C2BValidation::reject(
                    C2BValidation::REJECT_CODE_INVALID_AMOUNT,
                    "Minimum payment is KES {$customer->minimum_payment}",
                ));
            }

            event(new \Daraja\Laravel\Events\C2BValidationReceived($cb));

            return response()->json(C2BValidation::accept());

        } catch (\Throwable $e) {
            \Log::error('C2B validation error', ['error' => $e->getMessage()]);

            return response()->json(C2BValidation::reject(
                C2BValidation::REJECT_CODE_GENERAL,
                'Validation service unavailable',
            ));
        }
    }
}
```

Bind your controller in `AppServiceProvider`:

```php
$this->app->singleton(
    \Daraja\Laravel\Http\Controllers\MpesaWebhookController::class,
    \App\Http\Controllers\MpesaWebhookController::class,
);
```

---

## 10. STK Push — Full Request Lifecycle

```php
Client → POST /api/payments/stk
      → PaymentController::initiateSTK()
      → DarajaClient::stk()->push()
      → Save CheckoutRequestID to Order
      → Return { status: pending }

Daraja → POST /mpesa/stk/callback
       → MpesaWebhookController::handleSTKCallback()
       → dispatch(STKCallbackReceived)
       → HandleSTKCallback listener (queued)
       → Order updated, receipt email sent
```

### STK Push with Polling Fallback

When a callback is delayed, poll for status using a scheduled command:

```php
// app/Console/Commands/PollPendingSTKPayments.php

final class PollPendingSTKPayments extends Command
{
    protected $signature   = 'mpesa:poll-stk';
    protected $description = 'Poll status of STK Push payments pending >2 minutes';

    public function handle(DarajaClient $mpesa): void
    {
        $pending = Order::where('status', 'payment_pending')
            ->where('created_at', '<', now()->subMinutes(2))
            ->whereNotNull('mpesa_checkout_id')
            ->get();

        foreach ($pending as $order) {
            try {
                $status = $mpesa->stk()->query($order->mpesa_checkout_id);
                $this->info("Queried {$order->mpesa_checkout_id}: " . $status->resultDescription());
                // The result callback will arrive; this just triggers a recheck
            } catch (\Throwable $e) {
                $this->error("Poll failed for order {$order->id}: " . $e->getMessage());
            }
        }
    }
}
```

Register in `app/Console/Kernel.php`:

```php
$schedule->command('mpesa:poll-stk')->everyFiveMinutes();
```

---

## 11. B2C Disbursements

```php
// In a job or service class
final class ProcessPayrollJob implements ShouldQueue
{
    public function handle(DarajaClient $mpesa): void
    {
        $employees = Employee::whereDate('salary_due', today())->get();

        foreach ($employees as $employee) {
            try {
                $response = $mpesa->b2c()->sendSalary(
                    phone:   $employee->phone,
                    amount:  $employee->salary,
                    remarks: 'Salary ' . now()->format('F Y'),
                );

                $employee->update([
                    'mpesa_conversation_id' => $response->conversationId(),
                    'payroll_status'        => 'processing',
                ]);

            } catch (\Throwable $e) {
                \Log::error("Payroll failed for employee {$employee->id}", [
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }
}
```

---

## 12. B2B & Tax Remittance

```php
// Supplier payment
$response = $mpesa->b2b()->payBill(
    receiverShortcode: $supplier->paybill,
    amount:            $invoice->total,
    accountReference:  $invoice->reference,
    remarks:           "Invoice {$invoice->number}",
);

// Tax to KRA
$response = $mpesa->taxRemittance()->remit(
    amount:           $taxAmount,
    accountReference: $prn,         // Payment Registration Number from KRA iTax
    remarks:          'VAT Q2 2025',
);
```

---

## 13. Bill Manager

```php
// One-time opt-in (run once per environment)
$mpesa->billManager()->optIn(
    email:           config('company.billing_email'),
    officialContact: config('company.phone'),
    callbackUrl:     route('mpesa.bill.reconciliation'),
    sendReminders:   true,
);

// Send invoice
use Daraja\ValueObjects\Invoice;
use Daraja\ValueObjects\InvoiceItem;

$response = $mpesa->billManager()->sendInvoice(
    Invoice::make(
        externalReference: "INV-{$invoice->id}",
        billedFullName:    $customer->name,
        billedPhone:       $customer->phone,
        billedPeriod:      now()->format('F Y'),
        invoiceName:       $invoice->description,
        dueDate:           $invoice->due_date->format('Y-m-d'),
        accountReference:  $customer->account_number,
        amount:            $invoice->total,
    )->addItem(new InvoiceItem($invoice->description, $invoice->subtotal))
     ->addItem(new InvoiceItem('VAT 16%', $invoice->vat))
);
```

---

## 14. Artisan Commands Reference

| Command | Description |
| --- | --- |
| `php artisan mpesa:generate-credential` | Generate SecurityCredential from certificate |
| `php artisan mpesa:register-urls` | Register C2B validation/confirmation URLs |
| `php artisan mpesa:check-balance` | Trigger an account balance query |

### Examples

```bash
# Generate credential and write to .env automatically
php artisan mpesa:generate-credential --env

# Register C2B URLs for production
php artisan mpesa:register-urls \
  --confirmation=https://app.example.com/mpesa/c2b/confirmation \
  --validation=https://app.example.com/mpesa/c2b/validation

# Check balance (result arrives at your resultUrl)
php artisan mpesa:check-balance --type=shortcode
```

---

## 15. Redis Token Cache

For apps running multiple PHP-FPM workers or multiple pods, configure the
Redis token cache to avoid redundant OAuth calls:

```php
// app/Providers/AppServiceProvider.php

use Daraja\Auth\Cache\RedisTokenCache;
use Daraja\Auth\Cache\TokenCacheInterface;
use Daraja\DarajaClient;

public function register(): void
{
    // Bind the Redis cache implementation
    $this->app->singleton(TokenCacheInterface::class, function () {
        return new RedisTokenCache(
            redis:     \Illuminate\Support\Facades\Redis::connection('mpesa')->client(),
            keyPrefix: 'mpesa:token:' . config('daraja.shortcode'),
        );
    });

    // Override the DarajaClient singleton to inject the cache
    $this->app->singleton(DarajaClient::class, function ($app) {
        $cfg = $app['config']['daraja'];
        return DarajaClient::make(
            consumerKey:        $cfg['consumer_key'],
            consumerSecret:     $cfg['consumer_secret'],
            shortcode:          $cfg['shortcode'],
            passkey:            $cfg['passkey'],
            environment:        \Daraja\Enums\Environment::from($cfg['environment']),
            securityCredential: $cfg['security_credential'] ?? '',
            initiatorName:      $cfg['initiator_name']      ?? '',
            callbackUrl:        $cfg['callback_url']        ?? '',
            resultUrl:          $cfg['result_url']          ?? '',
            timeoutUrl:         $cfg['timeout_url']         ?? '',
            tokenCache:         $app->make(TokenCacheInterface::class),
        );
    });
});
```

Add a dedicated Redis connection in `config/database.php`:

```php
'redis' => [
    'mpesa' => [
        'url'      => env('REDIS_URL'),
        'host'     => env('REDIS_HOST', '127.0.0.1'),
        'port'     => env('REDIS_PORT', 6379),
        'password' => env('REDIS_PASSWORD'),
        'database' => env('REDIS_MPESA_DB', 2),
    ],
],
```

---

## 16. Testing in Laravel

### 16.1 Mock the DarajaClient

```php
// tests/Feature/PaymentControllerTest.php

use Daraja\DarajaClient;
use Daraja\Http\Response;
use Daraja\Services\STKPush;

it('initiates stk push and returns pending status', function () {
    $mockResponse = Response::fromArray([
        'ResponseCode'      => '0',
        'CheckoutRequestID' => 'ws_CO_test123',
        'ResponseDescription' => 'Success',
    ]);

    $mockSTK = Mockery::mock(STKPush::class);
    $mockSTK->shouldReceive('push')
        ->once()
        ->with('254712345678', 1500, 'ORD-001', Mockery::any(), Mockery::any())
        ->andReturn($mockResponse);

    $mockClient = Mockery::mock(DarajaClient::class);
    $mockClient->shouldReceive('stk')->andReturn($mockSTK);

    app()->instance(DarajaClient::class, $mockClient);

    $this->postJson('/api/payments/stk', [
        'phone'    => '0712345678',
        'amount'   => 1500,
        'order_id' => 'ORD-001',
    ])->assertJson(['status' => 'pending']);
});
```

### 16.2 Test Webhook Handlers

```php
// tests/Feature/WebhookTest.php

use Daraja\Laravel\Events\STKCallbackReceived;
use Illuminate\Support\Facades\Event;

it('fires STKCallbackReceived event on stk callback', function () {
    Event::fake([STKCallbackReceived::class]);

    $payload = json_encode([
        'Body' => ['stkCallback' => [
            'MerchantRequestID' => '1',
            'CheckoutRequestID' => 'ws_CO_test',
            'ResultCode'        => 0,
            'ResultDesc'        => 'Success',
            'CallbackMetadata'  => ['Item' => [
                ['Name' => 'Amount',             'Value' => 1500],
                ['Name' => 'MpesaReceiptNumber', 'Value' => 'QHT3XXX'],
                ['Name' => 'TransactionDate',    'Value' => 20250630120000],
                ['Name' => 'PhoneNumber',        'Value' => 254712345678],
            ]],
        ]],
    ]);

    $this->postJson('/mpesa/stk/callback', json_decode($payload, true))
        ->assertOk()
        ->assertJson(['ResultCode' => '0']);

    Event::assertDispatched(STKCallbackReceived::class, function ($event) {
        return $event->callback->checkoutRequestId === 'ws_CO_test'
            && $event->callback->isSuccessful();
    });
});

it('returns accept on c2b validation for known account', function () {
    Customer::factory()->create(['account_ref' => 'ACC-001']);

    $this->postJson('/mpesa/c2b/validation', [
        'TransID'           => 'LGR019G3J4',
        'TransTime'         => '20250630120000',
        'TransAmount'       => '500.00',
        'BusinessShortCode' => '600610',
        'BillRefNumber'     => 'ACC-001',
        'MSISDN'            => '254712345678',
        'FirstName'         => 'John',
        'MiddleName'        => '',
        'LastName'          => 'Doe',
    ])->assertJson(['ResultCode' => '0']);
});
```

### 16.3 Test Listeners in Isolation

```php
// tests/Unit/Listeners/HandleSTKCallbackTest.php

use App\Listeners\HandleSTKCallback;
use App\Models\Order;
use Daraja\Laravel\Events\STKCallbackReceived;
use Daraja\Webhooks\Payloads\STKCallback;

it('marks order as paid on successful callback', function () {
    $order = Order::factory()->create(['mpesa_checkout_id' => 'ws_CO_test']);

    $payload = STKCallback::fromArray([
        'Body' => ['stkCallback' => [
            'MerchantRequestID' => '1',
            'CheckoutRequestID' => 'ws_CO_test',
            'ResultCode'        => 0,
            'ResultDesc'        => 'Success',
            'CallbackMetadata'  => ['Item' => [
                ['Name' => 'Amount',             'Value' => 1500],
                ['Name' => 'MpesaReceiptNumber', 'Value' => 'QHT3XXX'],
                ['Name' => 'TransactionDate',    'Value' => 20250630120000],
                ['Name' => 'PhoneNumber',        'Value' => 254712345678],
            ]],
        ]],
    ]);

    (new HandleSTKCallback())->handle(new STKCallbackReceived($payload));

    $order->refresh();
    expect($order->status)->toBe('paid')
        ->and($order->mpesa_receipt)->toBe('QHT3XXX');
});
```

---

## 17. Production Checklist

### Configuration

- [ ] `MPESA_ENVIRONMENT=production`
- [ ] Production consumer key/secret from Safaricom portal
- [ ] Production shortcode and passkey set
- [ ] SecurityCredential generated with **production** certificate
- [ ] All callback URLs are **HTTPS** with a valid TLS certificate

### Architecture

- [ ] Queue driver is Redis (`QUEUE_CONNECTION=redis`)
- [ ] Dedicated `mpesa` queue with Supervisor running 2+ workers
- [ ] Redis token cache configured in `AppServiceProvider`
- [ ] C2B URLs registered via `php artisan mpesa:register-urls`

### Security

- [ ] `safaricom_ips` whitelist is populated in `config/daraja.php`
- [ ] `APP_ENV=production` (VerifyMpesaIp enforces IP check)
- [ ] Webhook routes excluded from CSRF (handled by `api` middleware)
- [ ] No Daraja credentials in version control

### Reliability

- [ ] `ShouldQueue` implemented on all M-Pesa event listeners
- [ ] `$tries` and `$backoff` configured on queued listeners
- [ ] `failed()` method implemented for alerting on listener failure
- [ ] Idempotency guards in every listener (check before updating records)
- [ ] STK poll command scheduled every 5 minutes as fallback

### Observability

- [ ] Laravel Telescope (or equivalent) capturing events and queue jobs
- [ ] Structured logging in place (channel `mpesa` recommended)
- [ ] Alert configured on repeated `AuthenticationException`
- [ ] Alert configured on repeated listener failures via Horizon/queue monitor
