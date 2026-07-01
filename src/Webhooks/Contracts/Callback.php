<?php

declare(strict_types=1);

namespace Daraja\Webhooks\Contracts;

/**
 * Represents a parsed M-Pesa callback/result payload.
 *
 * All concrete payload classes are immutable value objects constructed
 * from the raw JSON body that Daraja POSTs to your callback URL.
 */
interface Callback
{
    /**
     * True when Daraja reports a successful transaction (ResultCode 0).
     */
    public function isSuccessful(): bool;

    /**
     * The M-Pesa result/response code. "0" = success.
     */
    public function resultCode(): string;

    /**
     * Human-readable description of the result.
     */
    public function resultDescription(): string;

    /**
     * Original raw payload as received from Daraja.
     *
     * @return array<string, mixed>
     */
    public function raw(): array;
}
