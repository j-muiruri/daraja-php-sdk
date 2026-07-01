# Integrating j-muiruri/daraja-php-sdk in a Plain PHP Project

**Enterprise Integration Guide — v1.0**

---

## Table of Contents

1. [Requirements](#1-requirements)
2. [Installation](#2-installation)
3. [Configuration Strategy](#3-configuration-strategy)
4. [Authentication & Token Management](#4-authentication--token-management)
5. [STK Push — End-to-End](#5-stk-push--end-to-end)
6. [C2B — Receiving Payments](#6-c2b--receiving-payments)
7. [B2C — Disbursements](#7-b2c--disbursements)
8. [B2B — Supplier Payments](#8-b2b--supplier-payments)
9. [Tax Remittance](#9-tax-remittance)
10. [Bill Manager](#10-bill-manager)
11. [Transaction Status & Reconciliation](#11-transaction-status--reconciliation)
12. [Account Balance](#12-account-balance)
13. [Transaction Reversal](#13-transaction-reversal)
14. [Dynamic QR Codes](#14-dynamic-qr-codes)
15. [Webhook Handling](#15-webhook-handling)
16. [Error Handling Strategy](#16-error-handling-strategy)
17. [Logging](#17-logging)
18. [Testing](#18-testing)
19. [Production Checklist](#19-production-checklist)

---

## 1. Requirements

| Requirement | Minimum |
|---|---|
| PHP | 8.2 |
| Composer | 2.x |
| `ext-openssl` | any |
| `ext-json` | any |
| HTTPS endpoint | TLS 1.2+ |
| Safaricom Daraja account | [developer.safaricom.co.ke](https://developer.safaricom.co.ke) |

---

## 2. Installation

```bash
composer require j-muiruri/daraja-php-sdk
```

Download Safaricom certificates (required for B2C, B2B, Reversal, Balance):

```bash
mkdir -p certs
# Sandbox
curl -o certs/safaricom_sandbox.cer \
  "https://developer.safaricom.co.ke/sites/default/files/cert/sandbox/cert.cer"

# Production
curl -o certs/safaricom_production.cer \
  "https://developer.safaricom.co.ke/sites/default/files/cert/prod/cert.cer"
```

---

## 3. Configuration Strategy

### 3.1 Environment File

Use a `.env` file at your project root and load it with `vlucas/phpdotenv`
or your own loader. Never hardcode credentials.

```bash
# .env
MPESA_ENVIRONMENT=sandbox

MPESA_CONSUMER_KEY=your_consumer_key
MPESA_CONSUMER_SECRET=your_consumer_secret
MPESA_SHORTCODE=174379
MPESA_PASSKEY=bfb279f9aa9bdbcf158e97dd71a467cd2e0c893059b10f78e6b72ada1ed2c919

MPESA_INITIATOR_NAME=testapi
MPESA_SECURITY_CREDENTIAL=   # Generate with the SDK (see §4)

MPESA_CALLBACK_URL=https://yourapp.co.ke/mpesa/stk/callback
MPESA_RESULT_URL=https://yourapp.co.ke/mpesa/result
MPESA_TIMEOUT_URL=https://yourapp.co.ke/mpesa/timeout
```

### 3.2 Client Bootstrap

Create a single bootstrap file that the rest of your application imports:

```php
<?php
// bootstrap/mpesa.php

declare(strict_types=1);

use Daraja\DarajaClient;
use Daraja\Enums\Environment;

// Load .env (if using vlucas/phpdotenv)
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/..');
$dotenv->safeLoad();

return DarajaClient::make(
    consumerKey:        $_ENV['MPESA_CONSUMER_KEY'],
    consumerSecret:     $_ENV['MPESA_CONSUMER_SECRET'],
    shortcode:          $_ENV['MPESA_SHORTCODE'],
    passkey:            $_ENV['MPESA_PASSKEY'],
    environment:        Environment::from($_ENV['MPESA_ENVIRONMENT'] ?? 'sandbox'),
    securityCredential: $_ENV['MPESA_SECURITY_CREDENTIAL'] ?? '',
    initiatorName:      $_ENV['MPESA_INITIATOR_NAME']      ?? '',
    callbackUrl:        $_ENV['MPESA_CALLBACK_URL']         ?? '',
    resultUrl:          $_ENV['MPESA_RESULT_URL']           ?? '',
    timeoutUrl:         $_ENV['MPESA_TIMEOUT_URL']          ?? '',
);
```

Usage anywhere in your app:

```php
$mpesa = require __DIR__ . '/bootstrap/mpesa.php';
$mpesa->stk()->push('0712345678', 1500, 'INV-001');
```

### 3.3 Multi-Process Token Caching (Redis)

For PHP-FPM or queue workers running multiple processes, inject a Redis token
cache to avoid redundant OAuth calls across processes:

```php
use Daraja\Auth\Cache\RedisTokenCache;
use Daraja\DarajaClient;

$redis = new Redis();
$redis->connect($_ENV['REDIS_HOST'], (int) $_ENV['REDIS_PORT']);
$redis->auth($_ENV['REDIS_PASSWORD']);

$tokenCache = new RedisTokenCache(
    redis:     $redis,
    keyPrefix: 'mpesa:token:' . $_ENV['MPESA_SHORTCODE'],
);

$mpesa = DarajaClient::make(
    /* ...credentials... */
    tokenCache: $tokenCache,
);
```

---

## 4. Authentication & Token Management

OAuth tokens are managed **automatically** — you never call the auth endpoint directly.
Every API call transparently obtains and reuses a cached token.

### Generating the SecurityCredential

Required once per environment before using B2C, B2B, Reversal, or Account Balance:

```php
<?php
// scripts/generate_credential.php

require 'vendor/autoload.php';

use Daraja\Concerns\HasSecurityCredential;

$generator = new class {
    use HasSecurityCredential;
    public function generate(string $password, string $certPath): string {
        return $this->generateSecurityCredential($password, $certPath);
    }
};

$env      = $argv[1] ?? 'sandbox';
$password = $argv[2] ?? readline('Initiator password: ');
$certPath = __DIR__ . "/../certs/safaricom_{$env}.cer";

$credential = $generator->generate($password, $certPath);

echo PHP_EOL . "MPESA_SECURITY_CREDENTIAL={$credential}" . PHP_EOL;
```

```bash
php scripts/generate_credential.php sandbox MyInitiatorPassword
```

Store the output in your `.env` as `MPESA_SECURITY_CREDENTIAL`.

---

## 5. STK Push — End-to-End

### 5.1 Initiate Payment

```php
<?php
// api/stk/initiate.php

declare(strict_types=1);

require 'vendor/autoload.php';

use Daraja\Exceptions\ApiException;
use Daraja\Exceptions\ValidationException;

$mpesa = require 'bootstrap/mpesa.php';

header('Content-Type: application/json');

try {
    $body   = json_decode(file_get_contents('php://input'), true, 512, JSON_THROW_ON_ERROR);
    $phone  = $body['phone']  ?? '';
    $amount = (int) ($body['amount'] ?? 0);
    $ref    = $body['order_id'] ?? '';

    $response = $mpesa->stk()->push(
        phone:            $phone,
        amount:           $amount,
        accountReference: $ref,
        description:      'Order Payment',
    );

    if ($response->isAccepted()) {
        // Persist CheckoutRequestID to match against the callback later
        $db->query(
            'UPDATE orders SET mpesa_checkout_id = ? WHERE id = ?',
            [$response->checkoutRequestId(), $ref]
        );

        echo json_encode([
            'status'             => 'pending',
            'checkout_request_id'=> $response->checkoutRequestId(),
            'message'            => 'Check your phone and enter your M-Pesa PIN',
        ]);
    } else {
        http_response_code(422);
        echo json_encode(['status' => 'rejected', 'message' => $response->responseDescription()]);
    }
} catch (ValidationException $e) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'errors' => $e->errors()]);
} catch (ApiException $e) {
    http_response_code(502);
    echo json_encode(['status' => 'error', 'message' => 'Payment gateway error']);
    error_log('[M-Pesa] STK Push API error: ' . $e->getMessage());
}
```

### 5.2 STK Push Callback Handler

```php
<?php
// webhooks/stk/callback.php

declare(strict_types=1);

require 'vendor/autoload.php';

use Daraja\Webhooks\CallbackProcessor;
use Daraja\Webhooks\Payloads\STKCallback;

// Daraja requires HTTP 200 — respond immediately, process asynchronously
http_response_code(200);
header('Content-Type: application/json');
echo json_encode(['ResultCode' => '0', 'ResultDesc' => 'Accepted']);

// Flush the response before processing
if (function_exists('fastcgi_finish_request')) {
    fastcgi_finish_request();
}

// ── Process ────────────────────────────────────────────────────────────────

$rawBody = file_get_contents('php://input');

// IMPORTANT: Only accept requests from Safaricom IP ranges (production)
$safaricomIps = require 'config/safaricom_ips.php';
$clientIp     = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? '';

if (!in_array($clientIp, $safaricomIps, true) && $_ENV['MPESA_ENVIRONMENT'] === 'production') {
    error_log("[M-Pesa] Blocked non-Safaricom IP: {$clientIp}");
    return;
}

try {
    $processor = new CallbackProcessor();
    $processor->onSTK(function (STKCallback $cb) use ($db): void {

        // Guard: ignore duplicate callbacks (idempotency)
        $existing = $db->query(
            'SELECT status FROM orders WHERE mpesa_checkout_id = ?',
            [$cb->checkoutRequestId]
        )->fetch();

        if (!$existing || $existing['status'] === 'paid') {
            return;
        }

        if ($cb->isSuccessful()) {
            $db->query(
                'UPDATE orders SET status = ?, mpesa_receipt = ?, paid_at = NOW()
                 WHERE mpesa_checkout_id = ?',
                ['paid', $cb->receiptNumber, $cb->checkoutRequestId]
            );
            sendPaymentConfirmationEmail($db, $cb->checkoutRequestId, $cb->amount);

        } elseif ($cb->wasCancelled()) {
            $db->query(
                'UPDATE orders SET status = ? WHERE mpesa_checkout_id = ?',
                ['cancelled', $cb->checkoutRequestId]
            );
        } elseif ($cb->hadInsufficientFunds()) {
            $db->query(
                'UPDATE orders SET status = ? WHERE mpesa_checkout_id = ?',
                ['failed_insufficient_funds', $cb->checkoutRequestId]
            );
        }
    });

    $processor->process($rawBody);

} catch (\Throwable $e) {
    error_log('[M-Pesa] STK callback processing error: ' . $e->getMessage());
    error_log('[M-Pesa] Raw body: ' . $rawBody);
}
```

### 5.3 Polling for STK Status

When a callback is delayed, poll using the CheckoutRequestID:

```php
$status = $mpesa->stk()->query($checkoutRequestId);

if ($status->isAccepted()) {
    $resultCode = (int) $status->resultDescription();
    // ResultCode 0 = success, 1032 = cancelled, 1 = insufficient funds
}
```

---

## 6. C2B — Receiving Payments

### 6.1 Register URLs (run once per shortcode per environment)

```php
// scripts/register_c2b_urls.php
$mpesa->c2b()->registerUrls(
    confirmationUrl: 'https://yourapp.co.ke/webhooks/c2b/confirmation',
    validationUrl:   'https://yourapp.co.ke/webhooks/c2b/validation',
    responseType:    'Completed',
);
```

### 6.2 Validation Endpoint

```php
// webhooks/c2b/validation.php

$raw = file_get_contents('php://input');
$cb  = \Daraja\Webhooks\Payloads\C2BValidation::fromJson($raw);

// Your business rule: accept only known account numbers
$account = $db->findAccountByRef($cb->billRefNumber);

if ($account === null) {
    echo json_encode(\Daraja\Webhooks\Payloads\C2BValidation::reject(
        \Daraja\Webhooks\Payloads\C2BValidation::REJECT_CODE_INVALID_ACCOUNT,
        'Account not found',
    ));
    exit;
}

echo json_encode(\Daraja\Webhooks\Payloads\C2BValidation::accept());
```

### 6.3 Confirmation Endpoint

```php
// webhooks/c2b/confirmation.php

http_response_code(200);
echo json_encode(['ResultCode' => '0', 'ResultDesc' => 'Accepted']);
if (function_exists('fastcgi_finish_request')) fastcgi_finish_request();

$cb = \Daraja\Webhooks\Payloads\C2BConfirmation::fromJson(file_get_contents('php://input'));

$db->query(
    'INSERT INTO payments (transaction_id, amount, phone, account_ref, paid_at)
     VALUES (?, ?, ?, ?, ?)',
    [$cb->transactionId, $cb->amount, $cb->msisdn, $cb->billRefNumber, $cb->transactionTime?->format('Y-m-d H:i:s')]
);
```

---

## 7. B2C — Disbursements

```php
// Salary payment
$response = $mpesa->b2c()->sendSalary(
    phone:   '0712345678',
    amount:  85000,
    remarks: 'June 2025 Salary',
);

// Store the ConversationID to correlate with the async result
$db->query(
    'UPDATE payroll SET mpesa_conversation_id = ? WHERE employee_id = ?',
    [$response->conversationId(), $employeeId]
);
```

**Result callback** (`$_ENV['MPESA_RESULT_URL']`):

```php
// webhooks/b2c/result.php

http_response_code(200);
echo json_encode(['ResultCode' => '0', 'ResultDesc' => 'Accepted']);
if (function_exists('fastcgi_finish_request')) fastcgi_finish_request();

$cb = \Daraja\Webhooks\Payloads\B2CResult::fromJson(file_get_contents('php://input'));

if ($cb->isSuccessful()) {
    $db->query(
        'UPDATE payroll SET status = ?, receipt = ?, paid_at = ? WHERE mpesa_conversation_id = ?',
        ['paid', $cb->transactionReceipt, $cb->completedAt?->format('Y-m-d H:i:s'), $cb->conversationId]
    );
} else {
    $db->query(
        'UPDATE payroll SET status = ?, failure_reason = ? WHERE mpesa_conversation_id = ?',
        ['failed', $cb->resultDescription(), $cb->conversationId]
    );
}
```

---

## 8. B2B — Supplier Payments

```php
// Pay supplier invoice
$response = $mpesa->b2b()->payBill(
    receiverShortcode: '000001',        // Supplier's paybill
    amount:            250000,
    accountReference:  'INV-2025-0042', // Account ref at supplier
    remarks:           'Invoice payment Q2',
);

// Buy goods from a till
$response = $mpesa->b2b()->buyGoods(
    tillNumber: '654321',
    amount:     15000,
    remarks:    'Office supplies',
);
```

---

## 9. Tax Remittance

```php
// Remit tax to KRA — uses your iTax PRN as the account reference
$response = $mpesa->taxRemittance()->remit(
    amount:           125000,
    accountReference: 'PRN20250630123', // Your KRA Payment Registration Number
    remarks:          'VAT June 2025',
);
```

---

## 10. Bill Manager

```php
use Daraja\ValueObjects\Invoice;
use Daraja\ValueObjects\InvoiceItem;

// Step 1: Opt in (one-time)
$mpesa->billManager()->optIn(
    email:           'billing@yourcompany.co.ke',
    officialContact: '0722000000',
    callbackUrl:     'https://yourapp.co.ke/webhooks/bill/reconciliation',
    sendReminders:   true,
);

// Step 2: Send invoice
$invoice = Invoice::make(
    externalReference: 'INV-2025-0090',
    billedFullName:    'Jane Wanjiru',
    billedPhone:       '0712345678',
    billedPeriod:      'June 2025',
    invoiceName:       'Monthly Service Fee',
    dueDate:           '2025-07-15',
    accountReference:  'ACC-JWIN-001',
    amount:            5000.00,
)->addItem(new InvoiceItem('Service Fee', 4310.34, 'Monthly subscription'))
 ->addItem(new InvoiceItem('VAT (16%)', 689.66, 'Value Added Tax'));

$mpesa->billManager()->sendInvoice($invoice);

// Bulk invoicing
$invoices = array_map(fn($customer) => Invoice::make(...$customer), $customerData);
$mpesa->billManager()->sendBulk($invoices);

// Cancel unpaid invoice
$mpesa->billManager()->cancelInvoice('INV-2025-0090');
```

---

## 11. Transaction Status & Reconciliation

Use when a callback was missed or a payment is disputed:

```php
$response = $mpesa->transactionStatus()->query(
    transactionId: 'QHT3XXXXXXXXXXX', // M-Pesa receipt number
    remarks:       'Dispute resolution',
);
// Result arrives at your resultUrl
```

**Result callback:**

```php
$cb = \Daraja\Webhooks\Payloads\TransactionStatusResult::fromJson($raw);

if ($cb->isCompleted()) {
    echo "Transaction {$cb->transactionId} completed — Amount: {$cb->amount} KES";
}
```

---

## 12. Account Balance

```php
use Daraja\Enums\IdentifierType;

$mpesa->accountBalance()->query(
    identifierType: IdentifierType::Shortcode,
    remarks:        'EOD reconciliation',
);
// Result arrives at your resultUrl
```

**Result callback:**

```php
$cb = \Daraja\Webhooks\Payloads\AccountBalanceResult::fromJson($raw);

$working = $cb->workingAccountBalance();
$utility = $cb->utilityAccountBalance();

error_log("[M-Pesa] Working: {$working} KES | Utility: {$utility} KES");

// Find a specific account
$float = $cb->findBalance('Float');
```

---

## 13. Transaction Reversal

```php
$mpesa->reversal()->reverse(
    transactionId: 'QHT3XXXXXXXXXXX',
    amount:        1500,
    remarks:       'Customer refund — order cancelled',
);
```

---

## 14. Dynamic QR Codes

```php
use Daraja\Enums\QRCodeType;

$response = $mpesa->qr()->generate(
    merchantName: 'Your Business Name',
    refNo:        'PAY-001',
    amount:       500,
    type:         QRCodeType::DynamicMerchant,
    size:         400,
);

// Embed in HTML
$base64 = $mpesa->qr()->extractImage($response);
echo '<img src="data:image/png;base64,' . $base64 . '" alt="M-Pesa QR">';

// Save to disk
$mpesa->qr()->saveImage($response, '/var/www/html/qr/payment-001.png');
```

---

## 15. Webhook Handling

### Central Router Pattern

Route all Daraja callbacks to a single entry point and dispatch by URL path:

```php
<?php
// public/webhooks/mpesa.php

declare(strict_types=1);

require __DIR__ . '/../../vendor/autoload.php';

use Daraja\Webhooks\CallbackProcessor;
use Daraja\Webhooks\Payloads\C2BValidation;

http_response_code(200);
header('Content-Type: application/json');

$path    = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$rawBody = file_get_contents('php://input');
$db      = require __DIR__ . '/../../bootstrap/db.php';

// IP whitelist check (production only)
if ($_ENV['MPESA_ENVIRONMENT'] === 'production') {
    $allowedIps = include __DIR__ . '/../../config/safaricom_ips.php';
    $ip         = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? '';
    if (!in_array($ip, $allowedIps, true)) {
        http_response_code(403);
        exit;
    }
}

// C2B Validation must respond with accept/reject — handle separately
if (str_ends_with($path, '/c2b/validation')) {
    $cb      = \Daraja\Webhooks\Payloads\C2BValidation::fromJson($rawBody);
    $account = $db->findAccount($cb->billRefNumber);
    echo json_encode($account ? C2BValidation::accept() : C2BValidation::reject());
    exit;
}

// Respond 200 immediately for all other callbacks
echo json_encode(['ResultCode' => '0', 'ResultDesc' => 'Accepted']);
if (function_exists('fastcgi_finish_request')) fastcgi_finish_request();

// Process asynchronously
$processor = new CallbackProcessor();

$processor
    ->onSTK(function ($cb) use ($db) { /* ... */ })
    ->onC2BConfirmation(function ($cb) use ($db) { /* ... */ })
    ->onB2C(function ($cb) use ($db) { /* ... */ })
    ->onB2B(function ($cb) use ($db) { /* ... */ })
    ->onBillManagerReconciliation(function ($cb) use ($db) { /* ... */ });

try {
    $processor->process($rawBody);
} catch (\Throwable $e) {
    error_log('[M-Pesa] Webhook error: ' . $e->getMessage() . "\nBody: " . $rawBody);
}
```

### Safaricom IP Whitelist

```php
<?php
// config/safaricom_ips.php
return [
    '196.201.214.200', '196.201.214.206', '196.201.213.114',
    '196.201.214.207', '196.201.214.208', '196.201.213.44',
    '196.201.212.127', '196.201.212.138', '196.201.212.129',
    '196.201.212.136', '196.201.212.74',  '196.201.212.69',
];
```

---

## 16. Error Handling Strategy

```php
use Daraja\Exceptions\ApiException;
use Daraja\Exceptions\AuthenticationException;
use Daraja\Exceptions\DarajaException;
use Daraja\Exceptions\ValidationException;

try {
    $response = $mpesa->b2c()->sendSalary($phone, $amount);

} catch (ValidationException $e) {
    // Bad input — your code should fix this, never reach production
    foreach ($e->errors() as $field => $message) {
        error_log("[M-Pesa] Validation: {$field} — {$message}");
    }
    // Return 400 to the caller

} catch (AuthenticationException $e) {
    // Consumer key/secret invalid or Daraja OAuth endpoint unreachable
    // Alert your on-call engineer — this blocks all payments
    alert_oncall('M-Pesa auth failure: ' . $e->getMessage());
    // Return 503

} catch (ApiException $e) {
    // Daraja returned an error response
    error_log("[M-Pesa] API [{$e->errorCode()}] {$e->getMessage()}");

    if ($e->statusCode() === 503) {
        // Safaricom downtime — queue for retry
        enqueue_retry($phone, $amount);
    }
    // Return 502

} catch (DarajaException $e) {
    // Catch-all for any SDK-level error
    error_log('[M-Pesa] SDK error: ' . $e->getMessage());
}
```

---

## 17. Logging

Structured logging example (PSR-3 compatible):

```php
use Monolog\Logger;
use Monolog\Handler\StreamHandler;

$log = new Logger('mpesa');
$log->pushHandler(new StreamHandler('logs/mpesa.log', Logger::DEBUG));

// Log every callback received
$log->info('STK callback received', [
    'checkout_id' => $cb->checkoutRequestId,
    'result_code' => $cb->resultCode(),
    'amount'      => $cb->amount,
    'receipt'     => $cb->receiptNumber,
]);
```

Log **at minimum**:

- Every outgoing API request (type, amount, phone — no full credentials)
- Every incoming callback (type, transaction ID, result code)
- Every exception with context
- Token refresh events

---

## 18. Testing

### Unit Test Your Integration Code

```php
use Daraja\Webhooks\Payloads\STKCallback;
use PHPUnit\Framework\TestCase;

final class PaymentServiceTest extends TestCase
{
    public function test_marks_order_paid_on_successful_stk_callback(): void
    {
        $payload = [
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
        ];

        $cb      = STKCallback::fromArray($payload);
        $service = new PaymentService(new InMemoryOrderRepository());

        $service->handleSTKCallback($cb);

        $order = $service->findByCheckoutId('ws_CO_test');
        self::assertSame('paid', $order->status);
        self::assertSame('QHT3XXX', $order->mpesaReceipt);
    }
}
```

### Test with Sandbox

1. Create a sandbox app at [developer.safaricom.co.ke](https://developer.safaricom.co.ke)
2. Use shortcode `174379`, passkey from the portal
3. Use Safaricom's test phone number `254708374149`
4. Use [ngrok](https://ngrok.com) or [Expose](https://expose.dev) to tunnel your local server

```bash
ngrok http 8000
# Then set MPESA_CALLBACK_URL=https://abc123.ngrok.io/webhooks/mpesa/stk
```

---

## 19. Production Checklist

- [ ] `MPESA_ENVIRONMENT=production`
- [ ] Production consumer key and secret configured
- [ ] Production shortcode and passkey configured
- [ ] SecurityCredential generated with **production** certificate
- [ ] All callback URLs are **HTTPS** with a valid certificate
- [ ] IP whitelist enforced on all webhook endpoints
- [ ] Redis token cache configured (`RedisTokenCache`)
- [ ] All webhook endpoints respond HTTP 200 within 5 seconds
- [ ] `fastcgi_finish_request()` or equivalent called before long processing
- [ ] Idempotency implemented: check `mpesa_receipt` before crediting accounts
- [ ] Structured logging in place for all callbacks and exceptions
- [ ] Alert on `AuthenticationException` (blocks all transactions)
- [ ] C2B URLs registered with Daraja for production shortcode
- [ ] Sandbox testing complete for every payment flow
- [ ] Timeout fallback: poll `stk()->query()` if callback not received in 30s
- [ ] Database transactions wrap payment recording to avoid partial writes
