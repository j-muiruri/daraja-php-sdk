<?php

declare(strict_types=1);

namespace Daraja\ValueObjects;

use Daraja\Exceptions\ValidationException;

/**
 * Represents a validated, normalised Kenyan phone number.
 *
 * Accepted input formats:
 *   - 0712345678
 *   - +254712345678
 *   - 254712345678
 *   - 712345678
 *
 * Canonical output: 2547XXXXXXXX (12 digits, no + prefix)
 */
final class PhoneNumber
{
    private readonly string $e164;

    public function __construct(string $phone)
    {
        $this->e164 = self::normalise($phone);
    }

    public static function from(string $phone): self
    {
        return new self($phone);
    }

    private static function normalise(string $phone): string
    {
        $phone = trim(preg_replace('/\s+/', '', $phone) ?? '');

        // Strip leading +
        if (str_starts_with($phone, '+')) {
            $phone = substr($phone, 1);
        }

        // 07XXXXXXXX → 2547XXXXXXXX
        if (str_starts_with($phone, '0') && strlen($phone) === 10) {
            $phone = '254' . substr($phone, 1);
        }

        // 7XXXXXXXX → 2547XXXXXXXX
        if (str_starts_with($phone, '7') && strlen($phone) === 9) {
            $phone = '254' . $phone;
        }

        // Final validation: must be 254 7XX XXX XXX (12 digits)
        if (!preg_match('/^2547\d{8}$/', $phone)) {
            throw new ValidationException(
                ['phone' => "Invalid Kenyan phone number: '{$phone}'"],
            );
        }

        return $phone;
    }

    /**
     * Returns the number in Daraja E.164 format: 2547XXXXXXXX
     */
    public function value(): string
    {
        return $this->e164;
    }

    public function __toString(): string
    {
        return $this->e164;
    }
}
