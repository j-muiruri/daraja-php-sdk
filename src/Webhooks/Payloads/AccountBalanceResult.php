<?php

declare(strict_types=1);

namespace Daraja\Webhooks\Payloads;

use Daraja\Exceptions\ValidationException;
use Daraja\Webhooks\Results\BalanceLine;

/**
 * Represents an Account Balance result POSTed by Daraja to your ResultURL.
 *
 * Raw payload shape:
 * {
 *   "Result": {
 *     "ResultCode": 0,
 *     "ResultDesc": "The service request is processed successfully.",
 *     "OriginatorConversationID": "...",
 *     "ConversationID": "...",
 *     "TransactionID": "...",
 *     "ResultParameters": {
 *       "ResultParameter": [
 *         {
 *           "Key": "AccountBalance",
 *           "Value": "Working Account|KES|46713.00|46713.00|0.00|0.00&Float Account|KES|0.00|0.00|0.00|0.00"
 *         }
 *       ]
 *     }
 *   }
 * }
 */
final class AccountBalanceResult extends AbstractCallback
{
    public readonly string $resultCodeStr;
    public readonly string $resultDesc;
    public readonly string $originatorConversationId;
    public readonly string $conversationId;
    public readonly string $transactionId;

    /** @var list<BalanceLine> */
    public readonly array $balances;

    /** Raw balance string before parsing */
    public readonly string $rawBalanceString;

    /**
     * @param  array<string, mixed> $raw
     * @throws ValidationException
     */
    public function __construct(array $raw)
    {
        parent::__construct($raw);

        $result = $raw['Result'] ?? null;

        if (!is_array($result)) {
            throw new ValidationException(['payload' => 'Missing Result block in AccountBalance payload']);
        }

        $this->resultCodeStr           = (string) ($result['ResultCode'] ?? '');
        $this->resultDesc              = (string) ($result['ResultDesc'] ?? '');
        $this->originatorConversationId = (string) ($result['OriginatorConversationID'] ?? '');
        $this->conversationId          = (string) ($result['ConversationID'] ?? '');
        $this->transactionId           = (string) ($result['TransactionID'] ?? '');

        /** @var list<array{Key: string, Value: mixed}> $params */
        $params = $result['ResultParameters']['ResultParameter'] ?? [];

        $this->rawBalanceString = (string) $this->extractParam($params, 'AccountBalance', '');

        $this->balances = !empty($this->rawBalanceString)
            ? BalanceLine::parseAll($this->rawBalanceString)
            : [];
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

    /**
     * Find a balance line by account name (case-insensitive partial match).
     * e.g. findBalance('working') returns the Working Account line.
     */
    public function findBalance(string $name): ?BalanceLine
    {
        $lower = strtolower($name);

        foreach ($this->balances as $line) {
            if (str_contains(strtolower($line->accountName), $lower)) {
                return $line;
            }
        }

        return null;
    }

    public function workingAccountBalance(): ?float
    {
        return $this->findBalance('working')?->availableBalance;
    }

    public function utilityAccountBalance(): ?float
    {
        return $this->findBalance('utility')?->availableBalance;
    }
}
