<?php

declare(strict_types=1);

namespace Daraja\Laravel\Events;

use Daraja\Webhooks\Payloads\B2BExpressCheckoutResult;

final class B2BExpressCheckoutResultReceived
{
    public function __construct(
        public readonly B2BExpressCheckoutResult $callback,
    ) {}
}
