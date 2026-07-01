<?php

declare(strict_types=1);

namespace Daraja\Laravel\Events;

use Daraja\Webhooks\Payloads\AccountBalanceResult;

final class AccountBalanceReceived
{
    public function __construct(
        public readonly AccountBalanceResult $callback,
    ) {}
}
