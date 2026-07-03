# j-muiruri/daraja-php-sdk

A modern, fully typed PHP 8.2 SDK for the **Safaricom Daraja 3.0 M-Pesa API**.

[![Latest Version](https://img.shields.io/packagist/v/j-muiruri/daraja-php-sdk.svg)](https://packagist.org/packages/j-muiruri/daraja-php-sdk)
[![Total Downloads](https://img.shields.io/packagist/dt/j-muiruri/daraja-php-sdk.svg)](https://packagist.org/packages/j-muiruri/daraja-php-sdk)
[![Tests](https://github.com/j-muiruri/daraja-php-sdk/actions/workflows/tests.yml/badge.svg)](https://github.com/j-muiruri/daraja-php-sdk/actions)
[![PHP](https://img.shields.io/badge/PHP-8.2%2B-blue)](https://www.php.net)
[![License](https://img.shields.io/badge/license-MIT-green)](LICENSE)
---

## Features

| API | Service | Method(s) |
|---|---|---|
| OAuth 2.0 | `AccessTokenManager` | Auto-managed (transparent) |
| STK Push | `stk()` | `push()`, `pushBuyGoods()`, `query()` |
| C2B | `c2b()` | `registerUrls()`, `simulate()` |
| B2C | `b2c()` | `sendSalary()`, `sendBusinessPayment()`, `sendPromotion()`, `pay()` |
| B2B | `b2b()` | `payBill()`, `buyGoods()`, `pay()` |
| Transaction Status | `transactionStatus()` | `query()` |
| Account Balance | `accountBalance()` | `query()` |
| Reversal | `reversal()` | `reverse()` |
| Dynamic QR | `qr()` | `generate()`, `extractImage()`, `saveImage()` |

---

## Requirements

- PHP 8.2+
- Composer
- `ext-openssl`, `ext-json`
- Guzzle 7.x

---

## Installation

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
    callbackUrl:    'https://yourapp.co.ke/mpesa/callback',
    resultUrl:      'https://yourapp.co.ke/mpesa/result',
    timeoutUrl:     'https://yourapp.co.ke/mpesa/timeout',
);
```

Or read directly from environment variables:

```php
$mpesa = DarajaClient::fromEnv();
```

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

---

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

// Promotion / betting payout
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

These APIs need the initiator password encrypted with Safaricom's public certificate.

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
