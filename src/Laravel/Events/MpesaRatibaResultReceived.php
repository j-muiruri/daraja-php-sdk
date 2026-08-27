<?php

declare(strict_types=1);

namespace Daraja\Laravel\Events;

use Daraja\Webhooks\Payloads\MpesaRatibaResult;

final class MpesaRatibaResultReceived
{
    public function __construct(
        public readonly MpesaRatibaResult $callback,
    ) {}
}
