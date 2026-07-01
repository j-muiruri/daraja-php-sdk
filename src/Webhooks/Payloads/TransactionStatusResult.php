<?php

declare(strict_types=1);

namespace Daraja\Webhooks\Payloads;

use Daraja\Exceptions\ValidationException;

/**
 * Represents a Transaction Status result POSTed to your ResultURL.
 *
 * Key ResultParameters returned by Daraja:
 *   DebitPartyName, CreditPartyName, TransactionStatus,
 *   Amount, DebitAmount, CreditAmount, ReceiptNo,
 *   InitiatedTime, FinalisedTime, DebitAccountType, ConversationID
 */
final class TransactionStatusResult extends AbstractCallback
{
    public readonly string $resultCodeStr;
    public readonly string $resultDesc;
    public readonly string $originatorConversationId;
    public readonly string $conversationId;
    public readonly string $transactionId;

    // ResultParameters
    public readonly ?string              $debitPartyName;
    public readonly ?string              $creditPartyName;
    public readonly ?string              $transactionStatus;
    public readonly ?float               $amount;
    public readonly ?float               $debitAmount;
    public readonly ?float               $creditAmount;
    public readonly ?string              $receiptNo;
    public readonly ?string              $debitAccountType;
    public readonly ?\DateTimeImmutable  $initiatedTime;
    public readonly ?\DateTimeImmutable  $finalisedTime;

    /**
     * @param  array<string, mixed> $raw
     * @throws ValidationException
     */
    public function __construct(array $raw)
    {
        parent::__construct($raw);

        $result = $raw['Result'] ?? null;

        if (!is_array($result)) {
            throw new ValidationException(['payload' => 'Missing Result block in TransactionStatus payload']);
        }

        $this->resultCodeStr           = (string) ($result['ResultCode'] ?? '');
        $this->resultDesc              = (string) ($result['ResultDesc'] ?? '');
        $this->originatorConversationId = (string) ($result['OriginatorConversationID'] ?? '');
        $this->conversationId          = (string) ($result['ConversationID'] ?? '');
        $this->transactionId           = (string) ($result['TransactionID'] ?? '');

        /** @var list<array{Key: string, Value: mixed}> $params */
        $params = $result['ResultParameters']['ResultParameter'] ?? [];

        $this->debitPartyName    = $this->strParam($params, 'DebitPartyName');
        $this->creditPartyName   = $this->strParam($params, 'CreditPartyName');
        $this->transactionStatus = $this->strParam($params, 'TransactionStatus');
        $this->receiptNo         = $this->strParam($params, 'ReceiptNo');
        $this->debitAccountType  = $this->strParam($params, 'DebitAccountType');
        $this->amount            = $this->floatParam($params, 'Amount');
        $this->debitAmount       = $this->floatParam($params, 'DebitAmount');
        $this->creditAmount      = $this->floatParam($params, 'CreditAmount');
        $this->initiatedTime     = $this->parseTimestamp($this->extractParam($params, 'InitiatedTime'));
        $this->finalisedTime     = $this->parseTimestamp($this->extractParam($params, 'FinalisedTime'));
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

    public function isCompleted(): bool
    {
        return strtolower($this->transactionStatus ?? '') === 'completed';
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
