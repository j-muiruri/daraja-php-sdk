<?php

declare(strict_types=1);

namespace Daraja\Laravel\Events;

use Daraja\Webhooks\Payloads\ReversalResult;

final class ReversalResultReceived
{
    public function __construct(
        public readonly ReversalResult $callback,
    ) {}
}
