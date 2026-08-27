<?php

declare(strict_types=1);

namespace Daraja\Webhooks\Payloads;

use Daraja\Exceptions\ValidationException;

/**
 * Represents the callback Daraja POSTs to your callbackUrl after a
 * B2B Express Checkout (USSD Push to Till) request completes.
 *
 * Unlike most other Daraja async results, this payload is a flat JSON
 * object with no "Result" wrapper.
 *
 * Successful shape:
 * {
 *   "resultCode":"0",
 *   "resultDesc":"The service request is processed successfully.",
 *   "amount":"71.0",
 *   "requestId":"404e1aec-19e0-4ce3-973d-bd92e94c8021",
 *   "resultType":"0",
 *   "conversationID":"AG_20230426_2010434680d9f5a73766",
 *   "transactionId":"RDQ01NFT1Q",
 *   "status":"SUCCESS"
 * }
 *
 * Cancelled/failed shape:
 * {
 *   "resultCode":"4001",
 *   "resultDesc":"User cancelled transaction",
 *   "requestId":"c2a9ba32-9e11-4b90-892c-7bc54944609a",
 *   "amount":"71.0",
 *   "paymentReference":"MAndbubry3hi"
 * }
 */
final class B2BExpressCheckoutResult extends AbstractCallback
{
    public readonly string  $resultCodeStr;
    public readonly string  $resultDesc;
    public readonly string  $requestId;
    public readonly float   $amount;
    public readonly ?string $conversationId;
    public readonly ?string $transactionId;
    public readonly ?string $status;
    public readonly ?string $paymentReference;

    /**
     * @param  array<string, mixed> $raw
     * @throws ValidationException
     */
    public function __construct(array $raw)
    {
        parent::__construct($raw);

        if (!isset($raw['requestId'])) {
            throw new ValidationException(
                ['payload' => 'Missing requestId in B2B Express Checkout result payload'],
            );
        }

        $this->resultCodeStr    = (string) ($raw['resultCode'] ?? '');
        $this->resultDesc       = (string) ($raw['resultDesc'] ?? '');
        $this->requestId        = (string) $raw['requestId'];
        $this->amount           = (float) ($raw['amount'] ?? 0);
        $this->conversationId   = isset($raw['conversationID']) ? (string) $raw['conversationID'] : null;
        $this->transactionId    = isset($raw['transactionId']) ? (string) $raw['transactionId'] : null;
        $this->status           = isset($raw['status']) ? (string) $raw['status'] : null;
        $this->paymentReference = isset($raw['paymentReference']) ? (string) $raw['paymentReference'] : null;
    }

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        return new self($data);
    }

    /** @throws \JsonException */
    public static function fromJson(string $json): self
    {
        /** @var array<string, mixed> $data */
        $data = json_decode($json, true, 512, JSON_THROW_ON_ERROR);

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

    /** True when the merchant cancelled the USSD prompt (resultCode 4001). */
    public function wasCancelled(): bool
    {
        return $this->resultCodeStr === '4001';
    }
}
