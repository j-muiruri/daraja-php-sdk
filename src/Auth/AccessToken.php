<?php

declare(strict_types=1);

namespace Daraja\Auth;

/**
 * Represents a Daraja OAuth access token with expiry tracking.
 */
final class AccessToken
{
    private readonly int $expiresAt;

    public function __construct(
        private readonly string $token,
        int                     $expiresIn,
    ) {
        // Subtract 60s buffer to avoid using a token right as it expires
        $this->expiresAt = time() + $expiresIn - 60;
    }

    public function value(): string
    {
        return $this->token;
    }

    public function isExpired(): bool
    {
        return time() >= $this->expiresAt;
    }

    public function bearerHeader(): string
    {
        return 'Bearer ' . $this->token;
    }

    public function expiresAt(): int
    {
        return $this->expiresAt;
    }
}
