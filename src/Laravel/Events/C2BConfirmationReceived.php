<?php

declare(strict_types=1);

namespace Daraja\Laravel\Events;

use Daraja\Webhooks\Payloads\C2BConfirmation;

final class C2BConfirmationReceived
{
    public function __construct(
        public readonly C2BConfirmation $callback,
    ) {}
}
