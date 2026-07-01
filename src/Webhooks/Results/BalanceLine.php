<?php

declare(strict_types=1);

namespace Daraja\Webhooks\Results;

/**
 * Represents a single account balance line parsed from Daraja's pipe-delimited string.
 *
 * Raw format from API:
 *   "Working Account|KES|46713.00|46713.00|0.00|0.00"
 *
 * Fields: AccountName | Currency | CurrentBalance | AvailableBalance | ReservedBalance | UnClearedBalance
 */
final class BalanceLine
{
    public function __construct(
        public readonly string $accountName,
        public readonly string $currency,
        public readonly float  $currentBalance,
        public readonly float  $availableBalance,
        public readonly float  $reservedBalance,
        public readonly float  $unclearedBalance,
    ) {}

    /**
     * Parse a single pipe-delimited balance line.
     *
     * @throws \InvalidArgumentException
     */
    public static function fromString(string $line): self
    {
        $parts = explode('|', $line);

        if (count($parts) < 6) {
            throw new \InvalidArgumentException(
                "Cannot parse balance line — expected 6 pipe-delimited segments, got: '{$line}'"
            );
        }

        return new self(
            accountName:      trim($parts[0]),
            currency:         trim($parts[1]),
            currentBalance:   (float) $parts[2],
            availableBalance: (float) $parts[3],
            reservedBalance:  (float) $parts[4],
            unclearedBalance: (float) $parts[5],
        );
    }

    /**
     * Parse the full AccountBalance string into multiple BalanceLine objects.
     * Lines are separated by '&'.
     *
     * @return list<self>
     */
    public static function parseAll(string $raw): array
    {
        return array_values(array_map(
            static fn(string $line) => self::fromString(trim($line)),
            explode('&', $raw),
        ));
    }
}
