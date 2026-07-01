<?php

declare(strict_types=1);

namespace Daraja\Laravel\Events;

use Daraja\Webhooks\Payloads\TransactionStatusResult;

final class TransactionStatusReceived
{
    public function __construct(
        public readonly TransactionStatusResult $callback,
    ) {}
}
