<?php

declare(strict_types=1);

namespace Daraja\Webhooks\Payloads;

use Daraja\Exceptions\ValidationException;

/**
 * Represents a Reversal result POSTed to your ResultURL.
 *
 * Daraja posts this when the reversal succeeds or fails.
 *
 * Raw payload shape:
 * {
 *   "Result": {
 *     "ResultCode": 0,
 *     "ResultDesc": "The service request is processed successfully.",
 *     "OriginatorConversationID": "...",
 *     "ConversationID": "...",
 *     "TransactionID": "OEI2AK4Q16"
 *   }
 * }
 */
final class ReversalResult extends AbstractCallback
{
    public readonly string $resultCodeStr;
    public readonly string $resultDesc;
    public readonly string $originatorConversationId;
    public readonly string $conversationId;
    public readonly string $transactionId;

    /**
     * @param  array<string, mixed> $raw
     * @throws ValidationException
     */
    public function __construct(array $raw)
    {
        parent::__construct($raw);

        $result = $raw['Result'] ?? null;

        if (!is_array($result)) {
            throw new ValidationException(['payload' => 'Missing Result block in Reversal payload']);
        }

        $this->resultCodeStr           = (string) ($result['ResultCode'] ?? '');
        $this->resultDesc              = (string) ($result['ResultDesc'] ?? '');
        $this->originatorConversationId = (string) ($result['OriginatorConversationID'] ?? '');
        $this->conversationId          = (string) ($result['ConversationID'] ?? '');
        $this->transactionId           = (string) ($result['TransactionID'] ?? '');
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
}
