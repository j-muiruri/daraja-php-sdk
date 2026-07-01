<?php

declare(strict_types=1);

namespace Daraja\Webhooks\Payloads;

use Daraja\Exceptions\ValidationException;

/**
 * Represents a B2C payment result POSTed by Daraja to your ResultURL.
 *
 * Raw payload shape:
 * {
 *   "Result": {
 *     "ResultType": 0,
 *     "ResultCode": 0,
 *     "ResultDesc": "The service request is processed successfully.",
 *     "OriginatorConversationID": "29112-34801843-1",
 *     "ConversationID": "AG_20191219_00005797af5d7d75f652",
 *     "TransactionID": "QHT3XXXXXXXXXXX",
 *     "ResultParameters": {
 *       "ResultParameter": [
 *         {"Key": "TransactionAmount",                      "Value": 500},
 *         {"Key": "TransactionReceipt",                     "Value": "QHT3XXXXXXXXXXX"},
 *         {"Key": "B2CRecipientIsRegisteredCustomer",       "Value": "Y"},
 *         {"Key": "B2CChargesPaidAccountAvailableFunds",    "Value": -4510.00},
 *         {"Key": "ReceiverPartyPublicName",                "Value": "254708374149 - John Doe"},
 *         {"Key": "TransactionCompletedDateTime",           "Value": "19.12.2019 11:45:50"},
 *         {"Key": "B2CUtilityAccountAvailableFunds",        "Value": 10116.00},
 *         {"Key": "B2CWorkingAccountAvailableFunds",        "Value": 900000.00}
 *       ]
 *     }
 *   }
 * }
 */
final class B2CResult extends AbstractCallback
{
    public readonly int     $resultType;
    public readonly string  $resultCodeStr;
    public readonly string  $resultDesc;
    public readonly string  $originatorConversationId;
    public readonly string  $conversationId;
    public readonly string  $transactionId;

    // ResultParameters — populated on success
    public readonly ?float               $transactionAmount;
    public readonly ?string              $transactionReceipt;
    public readonly ?bool                $recipientIsRegistered;
    public readonly ?float               $chargesAccountBalance;
    public readonly ?string              $receiverPublicName;
    public readonly ?float               $utilityAccountBalance;
    public readonly ?float               $workingAccountBalance;
    public readonly ?\DateTimeImmutable  $completedAt;

    /**
     * @param  array<string, mixed> $raw
     * @throws ValidationException
     */
    public function __construct(array $raw)
    {
        parent::__construct($raw);

        $result = $raw['Result'] ?? null;

        if (!is_array($result)) {
            throw new ValidationException(['payload' => 'Missing Result block in B2C result payload']);
        }

        $this->resultType              = (int)    ($result['ResultType'] ?? 0);
        $this->resultCodeStr           = (string) ($result['ResultCode'] ?? '');
        $this->resultDesc              = (string) ($result['ResultDesc'] ?? '');
        $this->originatorConversationId = (string) ($result['OriginatorConversationID'] ?? '');
        $this->conversationId          = (string) ($result['ConversationID'] ?? '');
        $this->transactionId           = (string) ($result['TransactionID'] ?? '');

        /** @var list<array{Key: string, Value: mixed}> $params */
        $params = $result['ResultParameters']['ResultParameter'] ?? [];

        $this->transactionAmount   = $this->isSuccessful()
            ? (float) $this->extractParam($params, 'TransactionAmount', 0)
            : null;

        $this->transactionReceipt  = $this->isSuccessful()
            ? (string) $this->extractParam($params, 'TransactionReceipt', '')
            : null;

        $registered = $this->extractParam($params, 'B2CRecipientIsRegisteredCustomer');
        $this->recipientIsRegistered = $registered !== null ? ($registered === 'Y') : null;

        $this->chargesAccountBalance = $this->castFloat($this->extractParam($params, 'B2CChargesPaidAccountAvailableFunds'));
        $this->receiverPublicName    = $this->extractParamStr($params, 'ReceiverPartyPublicName');
        $this->utilityAccountBalance = $this->castFloat($this->extractParam($params, 'B2CUtilityAccountAvailableFunds'));
        $this->workingAccountBalance = $this->castFloat($this->extractParam($params, 'B2CWorkingAccountAvailableFunds'));

        $rawDate = $this->extractParam($params, 'TransactionCompletedDateTime');
        $this->completedAt = $rawDate !== null ? $this->parseB2CDateTime((string) $rawDate) : null;
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

    // -------------------------------------------------------------------------
    // Private helpers
    // -------------------------------------------------------------------------

    private function castFloat(mixed $value): ?float
    {
        return $value !== null ? (float) $value : null;
    }

    /** @param list<array{Key: string, Value: mixed}> $params */
    private function extractParamStr(array $params, string $key): ?string
    {
        $val = $this->extractParam($params, $key);

        return $val !== null ? (string) $val : null;
    }

    /**
     * B2C timestamps come as "19.12.2019 11:45:50" (DD.MM.YYYY HH:MM:SS).
     */
    private function parseB2CDateTime(string $value): ?\DateTimeImmutable
    {
        if (empty($value)) {
            return null;
        }

        $dt = \DateTimeImmutable::createFromFormat(
            'd.m.Y H:i:s',
            $value,
            new \DateTimeZone('Africa/Nairobi'),
        );

        return $dt !== false ? $dt : null;
    }
}
