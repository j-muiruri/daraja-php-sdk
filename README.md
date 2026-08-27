# j-muiruri/daraja-php-sdk

A modern, fully typed PHP 8.2+ SDK built for the Safaricom Daraja 3.0 M-Pesa API.

[![Latest Version](https://img.shields.io/packagist/v/j-muiruri/daraja-php-sdk.svg?style=flat-square)](https://packagist.org/packages/j-muiruri/daraja-php-sdk)
[![Total Downloads](https://img.shields.io/packagist/dt/j-muiruri/daraja-php-sdk.svg?style=flat-square)](https://packagist.org/packages/j-muiruri/daraja-php-sdk)
[![Tests](https://github.com/j-muiruri/daraja-php-sdk/actions/workflows/tests.yml/badge.svg?style=flat-square)](https://github.com/j-muiruri/daraja-php-sdk/actions)
[![PHP](https://img.shields.io/badge/PHP-8.2%2B-blue?style=flat-square)](https://www.php.net)
[![License](https://img.shields.io/badge/license-MIT-green?style=flat-square)](LICENSE)

---

## Index

* [System Requirements](#system-requirements)
* [Installation & Setup](#installation--setup)
* [Integration Guides](https://github.com/j-muiruri/daraja-php-sdk/tree/main/docs/integration)
* [Initialization](#initialization)
* [Feature Matrix](#feature-matrix)
* [API Reference](#api-reference)
  * [STK Push (Lipa na M-Pesa Online)](#stk-push-lipa-na-m-pesa-online)
  * [C2B (Customer to Business)](#c2b--register-urls)
  * [B2C (Disbursements)](#b2c--disbursements)

---

## System Requirements

* **PHP** 8.2 or higher
* **Composer** dependency manager
* **Extensions:** `ext-openssl`, `ext-json`
* **HTTP Client:** Guzzle 7.x

---

## Installation & Setup

Pull the package into your project via Composer:

```bash
composer require j-muiruri/daraja-php-sdk
```

---

## Quick Start

### 1. Create the client

```php
use Daraja\DarajaClient;
use Daraja\Enums\Environment;

$mpesa = DarajaClient::make(
    consumerKey:    $_ENV['MPESA_CONSUMER_KEY'],
    consumerSecret: $_ENV['MPESA_CONSUMER_SECRET'],
    shortcode:      $_ENV['MPESA_SHORTCODE'],
    passkey:        $_ENV['MPESA_PASSKEY'],
    environment:    Environment::Sandbox,
    callbackUrl:    '[https://yourapp.co.ke/mpesa/callback](https://yourapp.co.ke/mpesa/callback)',
    resultUrl:      '[https://yourapp.co.ke/mpesa/result](https://yourapp.co.ke/mpesa/result)',
    timeoutUrl:     '[https://yourapp.co.ke/mpesa/timeout](https://yourapp.co.ke/mpesa/timeout)',
);
```

Or read directly from environment variables:

```php
$mpesa = DarajaClient::fromEnv();
```

---
## Feature Matrix

| API Category       | Interfacing Service   | Exposed Method(s)                                                   |
| ------------------ | --------------------- | ------------------------------------------------------------------- |
| OAuth 2.0          | `AccessTokenManager`  | Auto-managed internally (transparent)                               |
| STK Push           | `stk()`               | `push()`, `pushBuyGoods()`, `query()`                               |
| C2B                | `c2b()`               | `registerUrls()`, `simulate()`                                      |
| B2C                | `b2c()`               | `sendSalary()`, `sendBusinessPayment()`, `sendPromotion()`, `pay()` |
| B2C Account Top Up | `b2cAccountTopUp()`   | `topUp()`                                                           |
| Business To Pochi  | `businessToPochi()`   | `pay()`                                                             |
| B2B                | `b2b()`               | `payBill()`, `buyGoods()`, `pay()`                                  |
| B2B Express Checkout | `b2bExpressCheckout()` | `push()`                                                          |
| M-Pesa Ratiba      | `mpesaRatiba()`       | `createForPayBill()`, `createForBuyGoods()`, `create()`             |
| Pull Transactions  | `pullTransaction()`   | `register()`, `query()`                                             |
| Transaction Status | `transactionStatus()` | `query()`                                                           |
| Account Balance    | `accountBalance()`    | `query()`                                                           |
| Reversal           | `reversal()`          | `reverse()`                                                         |
| Dynamic QR         | `qr()`                | `generate()`, `extractImage()`, `saveImage()`                       |
| SIM Swap           | `simSwap()`           | `checkLastSwapDate()`                                               |
| IMSI               | `imsi()`              | `check()`                                                           |
| IoT SIM Management | `iotSim()`            | `getAllSims()`, `activateSim()`, `sendSingleMessage()`, +10 more    |

---

## API Reference

### STK Push (Lipa na M-Pesa Online)

Initiates a payment prompt on the customer's phone. The customer enters their M-Pesa PIN to confirm.

```php
// Initiate an STK Push to a Paybill
$response = $mpesa->stk()->push(
    phone:            '0712345678',   // or '+254712345678' or '254712345678'
    amount:           1500,           // KES, minimum 1
    accountReference: 'INV-0042',    // Max 12 chars — shown to customer
    description:      'Order #42',   // Max 13 chars
    callbackUrl:      'https://yourapp.co.ke/mpesa/callback', // override per request
);

if ($response->isAccepted()) {
    $checkoutId = $response->checkoutRequestId();
    // Store $checkoutId to poll status or match against the callback
}

// Buy Goods (till number) variant
$response = $mpesa->stk()->pushBuyGoods(
    phone:       '0712345678',
    amount:      250,
    till:        '123456',
    callbackUrl: 'https://yourapp.co.ke/mpesa/callback',
);

// Query status (when callback isn't received)
$status = $mpesa->stk()->query($checkoutId);
echo $status->resultDescription(); // "The service request is processed successfully."
```

**STK Push Callback payload** (POST to your `callbackUrl`):

```json
{
  "Body": {
    "stkCallback": {
      "MerchantRequestID": "29115-34620561-1",
      "CheckoutRequestID": "ws_CO_191220191020363925",
      "ResultCode": 0,
      "ResultDesc": "The service request is processed successfully.",
      "CallbackMetadata": {
        "Item": [
          { "Name": "Amount",              "Value": 1500 },
          { "Name": "MpesaReceiptNumber",  "Value": "QHT3XXXXXXXXXXX" },
          { "Name": "PhoneNumber",         "Value": 254712345678 }
        ]
      }
    }
  }
}
```

---

### C2B — Register URLs

Register validation and confirmation URLs before your customers start paying.

```php
$mpesa->c2b()->registerUrls(
    confirmationUrl: 'https://yourapp.co.ke/mpesa/confirm',
    validationUrl:   'https://yourapp.co.ke/mpesa/validate', // optional
    responseType:    'Completed', // 'Completed' or 'Cancelled'
);

// Sandbox only — simulate a payment
$mpesa->c2b()->simulate(
    phone:         '0712345678',
    amount:        500,
    billRefNumber: 'TEST001',
    commandId:     'CustomerPayBillOnline',
);
```


### ⚠️ WARNING

B2C, B2B, Reversals, and Account Balance operations explicitly require you to supply an initiatorName and valid securityCredential string within your initialization configuration. Safely disburse outbound capital transfers from your operational utility wallets.

### B2C — Disbursements

Send money to customers (salaries, promotions, refunds).

> Requires `initiatorName` and `securityCredential` in Config.

```php
// Salary payment
$mpesa->b2c()->sendSalary(
    phone:   '0712345678',
    amount:  45000,
    remarks: 'April Salary',
);

//Promotion/betting payout
$mpesa->b2c()->sendPromotion(
    phone:   '0733123456',
    amount:  500,
    remarks: 'Jackpot winnings',
);

// General payment
$mpesa->b2c()->sendBusinessPayment('0722123456', 1200, 'Refund - Order #112');
```

**B2C Result callback payload** (async, POST to `resultUrl`):

```json
{
  "Result": {
    "ResultType": 0,
    "ResultCode": 0,
    "ResultDesc": "The service request is processed successfully.",
    "OriginatorConversationID": "29112-34801843-1",
    "ConversationID": "AG_20191219_00005797af5d7d75f652",
    "TransactionID": "QHT3XXXXXXXXXXX"
  }
}
```

---
### B2C Account Top Up

Moves funds from your paybill's Working account into a B2C shortcode's Utility account, so that
shortcode has balance available to disburse. Same underlying `/mpesa/b2b/v1/paymentrequest`
endpoint as B2B, restricted to `CommandID: BusinessPayToBulk`.

```php
$mpesa->b2cAccountTopUp()->topUp(
    b2cShortcode:     '600000',
    amount:           239,
    accountReference: '353353',
);
```

Callback payload matches the standard B2B result shape — handle it via `onB2B()` /
`parseB2B()`.

---
### Business To Pochi

Pays a customer's "Pochi La Biashara" business wallet instead of their personal M-Pesa account.
Amounts are constrained to 10–250,000 KES per transaction.

```php
$mpesa->businessToPochi()->pay(
    phone:   '0705912645',
    amount:  1500,
    remarks: 'Stock payment',
);
```

Callback payload matches the standard B2C result shape — handle it via `onB2C()` / `parseB2C()`.

---
### M-Pesa Ratiba — Standing Orders

Sets up a recurring payment: the customer approves once via a PIN prompt, then M-Pesa
auto-executes on your schedule with no further customer action.

> ⚠️ Commercial API — sandbox testing is self-serve, but going live requires a commercial
> agreement with Safaricom (email apisupport@safaricom.co.ke) before this is attached to your
> shortcode.

```php
use Daraja\Enums\Frequency;

$response = $mpesa->mpesaRatiba()->createForPayBill(
    standingOrderName: 'Monthly Rent - Unit 4B',   // must be unique per customer
    startDate:          new DateTimeImmutable('2026-09-01'),
    endDate:             new DateTimeImmutable('2027-09-01'),
    amount:              4500,
    payerPhone:          '0708374149',
    accountReference:    'UNIT-4B',
    frequency:           Frequency::Monthly,
);

// Ratiba nests its status differently to every other Daraja response —
// use the service's own accessors rather than $response->isAccepted():
if ($mpesa->mpesaRatiba()->isAccepted($response)) {
    // accepted for processing
}
```

Handle the callback (its own `responseHeader`/`responseBody` envelope) with:

```php
$processor->onMpesaRatiba(function (MpesaRatibaResult $result) {
    if ($result->isSuccessful()) {
        // $result->transactionId, $result->status
    }
});
```

---
### Pull Transactions

Recovers C2B transactions that never reached your callback URLs — one-time `register()`, then
`query()` per reconciliation window (max 48 hours of history).

```php
// One-time setup
$mpesa->pullTransaction()->register(
    shortCode:       '600000',
    nominatedNumber: '254722000000',
);

// Reconcile a window
$response = $mpesa->pullTransaction()->query(
    startDate: new DateTimeImmutable('-2 days'),
    endDate:   new DateTimeImmutable('now'),
);

$transactions = $response->get('Transaction', []);
```

> ⚠️ Safaricom's own docs are inconsistent about whether `query()` is GET or POST — see the
> docblock on `PullTransaction::query()` for details. This SDK sends POST with a JSON body;
> verify against your sandbox app.

---

### B2B — Pay Suppliers

```php
// Pay a supplier's paybill
$mpesa->b2b()->payBill(
    receiverShortcode: '000001',
    amount:            75000,
    accountReference:  'SUPP-ACC-001',
    remarks:           'Invoice #INV-2025-03',
);

// Pay a merchant till
$mpesa->b2b()->buyGoods('987654', 12000, 'Office supplies');
```

---
### B2B Express Checkout (USSD Push to Till)

Prompts a fellow merchant to pay you from their own till, via a USSD PIN prompt. Unlike other
operator APIs, this endpoint does **not** use `initiatorName`/`securityCredential` — auth is
handled entirely by your app's consumer key/secret.

```php
$response = $mpesa->b2bExpressCheckout()->push(
    primaryShortCode:  '000001',        // Merchant's till (debit party)
    receiverShortCode: '000002',        // Your paybill (credit party)
    amount:            100,
    paymentRef:        'INV-0042',      // Shown to the merchant in the USSD prompt
    partnerName:        'Acme Traders', // Your org's friendly name, shown to the merchant
    callbackUrl:        'https://yourapp.co.ke/mpesa/b2b-checkout/callback',
);

if ($response->isSuccessful()) {
    // "USSD Initiated Successfully" — final result arrives at callbackUrl
}
```

Handle the callback with `CallbackProcessor`:

```php
$processor->onB2BExpressCheckout(function (B2BExpressCheckoutResult $result) {
    if ($result->isSuccessful()) {
        // $result->transactionId, $result->amount
    } elseif ($result->wasCancelled()) {
        // Merchant cancelled the USSD prompt
    }
});
```

> ⚠️ This is a merchant-to-merchant product — the *receiver* must have a paybill able to receive
> B2B Express Checkout payments, and the payer must have a till number. See the
> [official docs](https://developer.safaricom.co.ke/apis/B2BExpressCheckout) for onboarding
> requirements.

---
### Transaction Status

Reconcile transactions when callbacks were missed.

```php
use Daraja\Enums\IdentifierType;

$status = $mpesa->transactionStatus()->query(
    transactionId:  'QHT3XXXXXXXXXXX',  // M-Pesa receipt number
    identifierType: IdentifierType::Shortcode,
    remarks:        'Reconciliation check',
);
```

---

### Account Balance

```php
use Daraja\Enums\IdentifierType;

$mpesa->accountBalance()->query(
    identifierType: IdentifierType::Shortcode,
    remarks:        'EOD balance check',
);
// Result arrives asynchronously on your resultUrl
```

---

### Transaction Reversal

```php
$mpesa->reversal()->reverse(
    transactionId: 'QHT3XXXXXXXXXXX',
    amount:        1500,
    remarks:       'Customer cancellation',
);
```

---

### Dynamic QR Code

```php
use Daraja\Enums\QRCodeType;

$response = $mpesa->qr()->generate(
    merchantName: 'Asante Coffee',
    refNo:        'INV-001',
    amount:       350,
    type:         QRCodeType::DynamicMerchant,
    size:         400,
);

// Get the Base64 PNG to embed in an <img> tag
$base64 = $mpesa->qr()->extractImage($response);
echo '<img src="data:image/png;base64,' . $base64 . '">';

// Or save to disk
$mpesa->qr()->saveImage($response, '/var/www/html/qr/payment.png');
```

---
### SIM Swap

Query the last date a customer's SIM was swapped — a fraud/risk signal for banking due
diligence. Returns a default date of `01-01-1900 00:00` if the SIM has not swapped in the
last 3 months.

> ⚠️ Commercial API — requires a signed commercial agreement with Safaricom before onboarding
> (email apisupport@safaricom.co.ke or your account manager). Won't function on a plain
> sandbox app without it. KES 50,000 connection fee; first 200,000 requests free, then KES 1
> each.

```php
$response = $mpesa->simSwap()->checkLastSwapDate('254722000000');

$lastSwap = $response->getString('lastSwapDate'); // e.g. "01-01-1900 00:00"
```

---
### IMSI

Returns a hashed IMSI, network registration date, and last SIM swap date for a Safaricom
number — a fuller fraud/risk-check signal set than SIM Swap alone.

> ⚠️ Commercial API — same onboarding requirements as SIM Swap above. KES 20 per call.

```php
$response = $mpesa->imsi()->check('254722000000');

$imsi                   = $response->getString('imsi');
$lastSwapDate           = $response->getString('lastSwapDate');
$msisdnRegistrationDate = $response->getString('msisdnRegistrationDate');
```

---
### IoT SIM Management

Manages Safaricom IoT SIM cards — activation, suspension, status checks, renaming — and their
messaging channel, via the `/simportal/*` product family.

> ⚠️ Requires the separate [Safaricom IoT SIM Management](https://www.business.safaricom.co.ke/products/IoTSimManagement)
> platform product — not unlocked by a standard M-Pesa Daraja app. Uses the same OAuth Bearer
> token as the rest of this SDK.

```php
// Check a SIM's status
$response = $mpesa->iotSim()->queryLifeCycleStatus(
    msisdn:   '300000020000',
    vpnGroup: '1-225560081663_VPN',
    username: 'user@safaricom.co.ke',
);

// Every response uses its own header/body envelope — use these helpers
// rather than Response::isAccepted():
if ($mpesa->iotSim()->isSuccessful($response)) {
    $status = $response->data()['body']['status'] ?? null;
}

// Activate a SIM
$mpesa->iotSim()->activateSim('300000443539', '1-225560081663_VPN', 'user@safaricom.co.ke');

// Suspend a subscriber
use Daraja\Enums\SimSubscriberOperation;

$mpesa->iotSim()->suspendOrResumeSubscriber(
    msisdn:    '300000100000',
    username:  'user@safaricom.co.ke',
    vpnGroup:  '1-225560081663_VPN',
    product:   '14205000',
    operation: SimSubscriberOperation::Suspend,
);

// Send a message to a SIM
$mpesa->iotSim()->sendSingleMessage('300001172000', 'Hello device', '1-47820525000_VPN');
```

See `IotSimManagement`'s docblock for the full endpoint list (13 operations across SIM
lifecycle and messaging).

---

## Phone Number Formats

The `PhoneNumber` value object accepts any common Kenyan format:

```php
use Daraja\ValueObjects\PhoneNumber;

PhoneNumber::from('0712345678');     // → 254712345678
PhoneNumber::from('+254712345678'); // → 254712345678
PhoneNumber::from('254712345678'); // → 254712345678
PhoneNumber::from('712345678');    // → 254712345678
```

Invalid numbers throw `Daraja\Exceptions\ValidationException`.

---

## Security Credentials (B2C, B2B, Reversal, Balance)

These APIs require the initiator password to be encrypted with Safaricom's public certificate.

**Step 1** — Download the certificate from the Daraja portal:
- Sandbox: `https://developer.safaricom.co.ke/sites/default/files/cert/sandbox/cert.cer`
- Production: `https://developer.safaricom.co.ke/sites/default/files/cert/prod/cert.cer`

**Step 2** — Generate the credential once and store it:

```php
use Daraja\Concerns\HasSecurityCredential;

class CredentialGenerator
{
    use HasSecurityCredential;

    public function generate(string $password, string $certPath): string
    {
        return $this->generateSecurityCredential($password, $certPath);
    }
}

$gen        = new CredentialGenerator();
$credential = $gen->generate('MyInitiatorPassword', '/path/to/cert.cer');
// Store $credential in your .env as MPESA_SECURITY_CREDENTIAL
```

---

## Error Handling

```php
use Daraja\Exceptions\ApiException;
use Daraja\Exceptions\AuthenticationException;
use Daraja\Exceptions\ValidationException;

try {
    $response = $mpesa->stk()->push(...);
} catch (ValidationException $e) {
    // Bad parameters — check before hitting the API
    foreach ($e->errors() as $field => $message) {
        echo "{$field}: {$message}\n";
    }
} catch (AuthenticationException $e) {
    // OAuth token failure — check consumer key/secret
    logger()->error('M-Pesa auth failed', ['error' => $e->getMessage()]);
} catch (ApiException $e) {
    // API returned an error response
    logger()->error('M-Pesa API error', [
        'status' => $e->statusCode(),
        'code'   => $e->errorCode(),
        'msg'    => $e->getMessage(),
    ]);
}
```

---

## Laravel Integration

```php
// config/mpesa.php
return [
    'consumer_key'        => env('MPESA_CONSUMER_KEY'),
    'consumer_secret'     => env('MPESA_CONSUMER_SECRET'),
    'shortcode'           => env('MPESA_SHORTCODE'),
    'passkey'             => env('MPESA_PASSKEY'),
    'environment'         => env('MPESA_ENVIRONMENT', 'sandbox'),
    'security_credential' => env('MPESA_SECURITY_CREDENTIAL'),
    'initiator_name'      => env('MPESA_INITIATOR_NAME'),
    'callback_url'        => env('MPESA_CALLBACK_URL'),
    'result_url'          => env('MPESA_RESULT_URL'),
    'timeout_url'         => env('MPESA_TIMEOUT_URL'),
];

// app/Providers/AppServiceProvider.php
use Daraja\DarajaClient;
use Daraja\Enums\Environment;

$this->app->singleton(DarajaClient::class, function () {
    return DarajaClient::make(
        consumerKey:        config('mpesa.consumer_key'),
        consumerSecret:     config('mpesa.consumer_secret'),
        shortcode:          config('mpesa.shortcode'),
        passkey:            config('mpesa.passkey'),
        environment:        Environment::from(config('mpesa.environment')),
        securityCredential: config('mpesa.security_credential'),
        initiatorName:      config('mpesa.initiator_name'),
        callbackUrl:        config('mpesa.callback_url'),
        resultUrl:          config('mpesa.result_url'),
        timeoutUrl:         config('mpesa.timeout_url'),
    );
});
```

---

## Testing

```bash
composer test
```

---

## Contributing

1. Fork the repo and create a feature branch
2. Write tests first — every new feature needs passing tests
3. Run `composer test` and `composer analyse` before opening a PR
4. Follow PSR-12 coding style

---

## License

MIT — see [LICENSE](LICENSE).
