<?php

declare(strict_types=1);

namespace Daraja\Enums;

enum Environment: string
{
    case Sandbox    = 'sandbox';
    case Production = 'production';

    public function baseUrl(): string
    {
        return match ($this) {
            self::Sandbox    => 'https://sandbox.safaricom.co.ke',
            self::Production => 'https://api.safaricom.co.ke',
        };
    }

    public function isSandbox(): bool
    {
        return $this === self::Sandbox;
    }
}
