<?php

declare(strict_types=1);

namespace Daraja\Laravel\Events;

use Daraja\Webhooks\Payloads\STKCallback;

/**
 * Fired when a Daraja STK Push callback is received.
 *
 * Listen for this in your EventServiceProvider:
 *
 *   STKCallbackReceived::class => [
 *       UpdateOrderStatusListener::class,
 *       SendPaymentReceiptListener::class,
 *   ]
 */
final class STKCallbackReceived
{
    public function __construct(
        public readonly STKCallback $callback,
    ) {}
}
