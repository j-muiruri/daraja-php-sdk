<?php

declare(strict_types=1);

namespace Daraja\Laravel\Events;

use Daraja\Webhooks\Payloads\C2BValidation;

final class C2BValidationReceived
{
    public function __construct(
        public readonly C2BValidation $callback,
    ) {}
}
