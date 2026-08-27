<?php

declare(strict_types=1);

namespace Daraja\Webhooks\Payloads;

use Daraja\Exceptions\ValidationException;

/**
 * Represents the callback Daraja POSTs to your CallBackURL after a Ratiba
 * standing order creation request completes.
 *
 * Ratiba uses its own envelope shape (`responseHeader`/`responseBody`),
 * distinct from the `Result` block used by every other async Daraja API.
 *
 * Successful shape:
 * {
 *   "responseHeader": {
 *     "responseRefID": "0acc0239-20fa-4a52-8b9d-9bd64c0465c3",
 *     "requestRefID": "0acc0239-20fa-4a52-8b9d-9bd64c0465c3",
 *     "responseCode": "0",
 *     "responseDescription": "The service request is processed successfully"
 *   },
 *   "responseBody": {
 *     "responseData": [
 *       {"name": "TransactionID", "value": "SC8F2IQMH5"},
 *       {"name": "responseCode", "value": "0"},
 *       {"name": "Status", "value": "OKAY"},
 *       {"name": "Msisdn", "value": "254******867"}
 *     ]
 *   }
 * }
 */
final class MpesaRatibaResult extends AbstractCallback
{
    public readonly string  $responseRefId;
    public readonly string  $requestRefId;
    public readonly string  $resultCodeStr;
    public readonly string  $resultDesc;
    public readonly ?string $transactionId;
    public readonly ?string $status;
    public readonly ?string $maskedMsisdn;

    /**
     * @param  array<string, mixed> $raw
     * @throws ValidationException
     */
    public function __construct(array $raw)
    {
        parent::__construct($raw);

        $header = $raw['responseHeader'] ?? $raw['ResponseHeader'] ?? null;

        if (!is_array($header)) {
            throw new ValidationException(['payload' => 'Missing responseHeader in Ratiba result payload']);
        }

        $this->responseRefId = (string) ($header['responseRefID'] ?? '');
        $this->requestRefId  = (string) ($header['requestRefID'] ?? '');
        $this->resultCodeStr = (string) ($header['responseCode'] ?? '');
        $this->resultDesc    = (string) ($header['responseDescription'] ?? '');

        /** @var list<array{name?: string, Name?: string, value?: mixed, Value?: mixed}> $data */
        $data = $raw['responseBody']['responseData'] ?? $raw['ResponseBody']['ResponseData'] ?? [];

        $this->transactionId = $this->dataValue($data, 'TransactionID');
        $this->status         = $this->dataValue($data, 'Status');
        $this->maskedMsisdn   = $this->dataValue($data, 'Msisdn');
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

    /** Ratiba reports success via "0" here — unrelated to HTTP responseCode "200" on the sync response. */
    public function isSuccessful(): bool
    {
        return $this->resultCodeStr === '0';
    }

    /** @param list<array{name?: string, Name?: string, value?: mixed, Value?: mixed}> $data */
    private function dataValue(array $data, string $name): ?string
    {
        foreach ($data as $item) {
            $itemName = $item['name'] ?? $item['Name'] ?? null;

            if ($itemName === $name) {
                $value = $item['value'] ?? $item['Value'] ?? null;

                return $value !== null ? (string) $value : null;
            }
        }

        return null;
    }
}
