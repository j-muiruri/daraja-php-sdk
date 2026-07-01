<?php

declare(strict_types=1);

namespace Daraja\Laravel\Events;

use Daraja\Webhooks\Payloads\B2CResult;

final class B2CResultReceived
{
    public function __construct(
        public readonly B2CResult $callback,
    ) {}
}
