<?php

declare(strict_types=1);

namespace Daraja\Webhooks\Payloads;

use Daraja\Exceptions\ValidationException;

/**
 * Represents a B2B payment result POSTed by Daraja to your ResultURL.
 *
 * Raw payload shape:
 * {
 *   "Result": {
 *     "ResultType": 0,
 *     "ResultCode": 0,
 *     "ResultDesc": "The service request is processed successfully.",
 *     "OriginatorConversationID": "...",
 *     "ConversationID": "AG_20191219_...",
 *     "TransactionID": "QHT3XXXXXXXXXXX",
 *     "ResultParameters": {
 *       "ResultParameter": [
 *         {"Key": "InitiatorAccountCurrentBalance", "Value": "{Amount={BasicAmount=46713.00,MinimumAmount=467...}}"},
 *         {"Key": "DebitAccountCurrentBalance",     "Value": "{Amount={BasicAmount=46713.00,...}}"},
 *         {"Key": "Amount",                         "Value": 10000},
 *         {"Key": "DebitPartyAffectedAccountBalance", "Value": "Working Account|KES|36713.00|36713.00|0.00|0.00"},
 *         {"Key": "TransCompletedTime",             "Value": 20191219102115},
 *         {"Key": "DebitPartyCharges",              "Value": ""},
 *         {"Key": "ReceiverPartyPublicName",        "Value": "000001 - Test Supplier Ltd"},
 *         {"Key": "Currency",                       "Value": "KES"}
 *       ]
 *     }
 *   }
 * }
 */
final class B2BResult extends AbstractCallback
{
    public readonly string $resultCodeStr;
    public readonly string $resultDesc;
    public readonly string $originatorConversationId;
    public readonly string $conversationId;
    public readonly string $transactionId;

    // ResultParameters
    public readonly ?float               $amount;
    public readonly ?string              $receiverPublicName;
    public readonly ?string              $currency;
    public readonly ?string              $debitPartyCharges;
    public readonly ?string              $debitPartyBalance;
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
            throw new ValidationException(['payload' => 'Missing Result block in B2B result payload']);
        }

        $this->resultCodeStr           = (string) ($result['ResultCode'] ?? '');
        $this->resultDesc              = (string) ($result['ResultDesc'] ?? '');
        $this->originatorConversationId = (string) ($result['OriginatorConversationID'] ?? '');
        $this->conversationId          = (string) ($result['ConversationID'] ?? '');
        $this->transactionId           = (string) ($result['TransactionID'] ?? '');

        /** @var list<array{Key: string, Value: mixed}> $params */
        $params = $result['ResultParameters']['ResultParameter'] ?? [];

        $this->amount             = $this->floatParam($params, 'Amount');
        $this->receiverPublicName = $this->strParam($params, 'ReceiverPartyPublicName');
        $this->currency           = $this->strParam($params, 'Currency');
        $this->debitPartyCharges  = $this->strParam($params, 'DebitPartyCharges');
        $this->debitPartyBalance  = $this->strParam($params, 'DebitPartyAffectedAccountBalance');
        $this->completedAt        = $this->parseTimestamp($this->extractParam($params, 'TransCompletedTime'));
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

    /** @param list<array{Key: string, Value: mixed}> $params */
    private function strParam(array $params, string $key): ?string
    {
        $val = $this->extractParam($params, $key);

        return $val !== null && $val !== '' ? (string) $val : null;
    }

    /** @param list<array{Key: string, Value: mixed}> $params */
    private function floatParam(array $params, string $key): ?float
    {
        $val = $this->extractParam($params, $key);

        return $val !== null ? (float) $val : null;
    }
}
