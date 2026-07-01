<?php

declare(strict_types=1);

namespace Daraja\Webhooks\Payloads;

use Daraja\Exceptions\ValidationException;

/**
 * Represents the callback Daraja POSTs to your STK Push callback URL.
 *
 * Raw payload shape:
 * {
 *   "Body": {
 *     "stkCallback": {
 *       "MerchantRequestID": "...",
 *       "CheckoutRequestID": "ws_CO_...",
 *       "ResultCode": 0,
 *       "ResultDesc": "The service request is processed successfully.",
 *       "CallbackMetadata": {           ← only present on ResultCode 0
 *         "Item": [
 *           {"Name": "Amount",             "Value": 1500},
 *           {"Name": "MpesaReceiptNumber", "Value": "QHT3XXXXXXXXXXX"},
 *           {"Name": "Balance",            "Value": ""},
 *           {"Name": "TransactionDate",    "Value": 20191219102115},
 *           {"Name": "PhoneNumber",        "Value": 254712345678}
 *         ]
 *       }
 *     }
 *   }
 * }
 */
final class STKCallback extends AbstractCallback
{
    public readonly string  $merchantRequestId;
    public readonly string  $checkoutRequestId;
    public readonly int     $resultCodeInt;
    public readonly string  $resultCodeStr;
    public readonly string  $resultDesc;

    // Populated only on success (ResultCode 0)
    public readonly ?int                   $amount;
    public readonly ?string                $receiptNumber;
    public readonly ?string                $phoneNumber;
    public readonly ?\DateTimeImmutable    $transactionDate;
    public readonly ?string                $balance;

    /**
     * @param  array<string, mixed> $raw  The full decoded JSON body from Daraja
     * @throws ValidationException
     */
    public function __construct(array $raw)
    {
        parent::__construct($raw);

        $callback = $raw['Body']['stkCallback'] ?? null;

        if (!is_array($callback)) {
            throw new ValidationException(
                ['payload' => 'Missing Body.stkCallback in STK Push callback'],
            );
        }

        $this->merchantRequestId = (string) ($callback['MerchantRequestID'] ?? '');
        $this->checkoutRequestId = (string) ($callback['CheckoutRequestID'] ?? '');
        $this->resultCodeInt     = (int)    ($callback['ResultCode'] ?? -1);
        $this->resultCodeStr     = (string) ($callback['ResultCode'] ?? '');
        $this->resultDesc        = (string) ($callback['ResultDesc'] ?? '');

        $items = $callback['CallbackMetadata']['Item'] ?? [];

        $this->amount          = $this->resultCodeInt === 0
            ? (int) $this->extractItem($items, 'Amount', 0)
            : null;

        $this->receiptNumber   = $this->resultCodeInt === 0
            ? (string) $this->extractItem($items, 'MpesaReceiptNumber', '')
            : null;

        $this->balance         = $this->resultCodeInt === 0
            ? (string) $this->extractItem($items, 'Balance', '')
            : null;

        $rawPhone = $this->extractItem($items, 'PhoneNumber');
        $this->phoneNumber = $rawPhone !== null ? (string) $rawPhone : null;

        $rawDate = $this->extractItem($items, 'TransactionDate');
        $this->transactionDate = $rawDate !== null
            ? $this->parseTimestamp($rawDate)
            : null;
    }

    /**
     * Named constructor — parse directly from the raw JSON string.
     *
     * @throws ValidationException|\JsonException
     */
    public static function fromJson(string $json): self
    {
        /** @var array<string, mixed> $data */
        $data = json_decode($json, true, 512, JSON_THROW_ON_ERROR);

        return new self($data);
    }

    /**
     * Named constructor — parse from an already-decoded array.
     *
     * @param  array<string, mixed> $data
     * @throws ValidationException
     */
    public static function fromArray(array $data): self
    {
        return new self($data);
    }

    public function resultCode(): string
    {
        return $this->resultCodeStr;
    }

    public function resultDescription(): string
    {
        return $this->resultDesc;
    }

    /**
     * True when the customer successfully entered their PIN and funds were deducted.
     */
    public function isSuccessful(): bool
    {
        return $this->resultCodeInt === 0;
    }

    /**
     * True when the customer explicitly cancelled the prompt.
     * ResultCode 1032 = request cancelled by user.
     */
    public function wasCancelled(): bool
    {
        return $this->resultCodeInt === 1032;
    }

    /**
     * True when the customer's balance was insufficient.
     * ResultCode 1 = insufficient balance.
     */
    public function hadInsufficientFunds(): bool
    {
        return $this->resultCodeInt === 1;
    }
}
