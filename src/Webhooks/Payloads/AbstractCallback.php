<?php

declare(strict_types=1);

namespace Daraja\Webhooks\Payloads;

use Daraja\Webhooks\Contracts\Callback;

/**
 * Base class for all M-Pesa callback payload objects.
 */
abstract class AbstractCallback implements Callback
{
    /** @param array<string, mixed> $raw */
    public function __construct(protected readonly array $raw) {}

    public function isSuccessful(): bool
    {
        return $this->resultCode() === '0';
    }

    public function raw(): array
    {
        return $this->raw;
    }

    // -------------------------------------------------------------------------
    // Helpers for subclasses
    // -------------------------------------------------------------------------

    /**
     * Extract a value from M-Pesa's "Item" array format used in STK callbacks:
     * [{"Name": "Amount", "Value": 100}, ...]
     *
     * @param  array<int, array{Name: string, Value: mixed}> $items
     */
    protected function extractItem(array $items, string $name, mixed $default = null): mixed
    {
        foreach ($items as $item) {
            if (($item['Name'] ?? '') === $name) {
                return $item['Value'] ?? $default;
            }
        }

        return $default;
    }

    /**
     * Extract a value from M-Pesa's "ResultParameter" array format:
     * [{"Key": "TransactionAmount", "Value": 500}, ...]
     *
     * @param  array<int, array{Key: string, Value: mixed}> $params
     */
    protected function extractParam(array $params, string $key, mixed $default = null): mixed
    {
        foreach ($params as $param) {
            if (($param['Key'] ?? '') === $key) {
                return $param['Value'] ?? $default;
            }
        }

        return $default;
    }

    /**
     * Parse an M-Pesa timestamp string (YmdHis or YmdHis as int) to DateTimeImmutable.
     */
    protected function parseTimestamp(int|string|null $value): ?\DateTimeImmutable
    {
        if ($value === null || $value === '') {
            return null;
        }

        $str = (string) $value;

        $dt = \DateTimeImmutable::createFromFormat('YmdHis', $str, new \DateTimeZone('Africa/Nairobi'));

        return $dt !== false ? $dt : null;
    }
}
