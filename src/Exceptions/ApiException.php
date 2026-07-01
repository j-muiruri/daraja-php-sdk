<?php

declare(strict_types=1);

namespace Daraja\Exceptions;

/**
 * Thrown when the Daraja API returns a non-success response.
 */
class ApiException extends DarajaException
{
    public function __construct(
        private readonly int    $statusCode,
        private readonly string $errorCode,
        string                  $errorMessage,
    ) {
        parent::__construct("[{$errorCode}] {$errorMessage}", $statusCode);
    }

    public function statusCode(): int
    {
        return $this->statusCode;
    }

    public function errorCode(): string
    {
        return $this->errorCode;
    }
}
