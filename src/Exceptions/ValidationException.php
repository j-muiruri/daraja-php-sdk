<?php

declare(strict_types=1);

namespace Daraja\Exceptions;

/**
 * Thrown when request payload validation fails before hitting the API.
 */
class ValidationException extends DarajaException
{
    /** @param array<string, string> $errors */
    public function __construct(
        private readonly array $errors,
        string $message = 'Validation failed',
    ) {
        parent::__construct($message . ': ' . implode(', ', $errors));
    }

    /** @return array<string, string> */
    public function errors(): array
    {
        return $this->errors;
    }
}
