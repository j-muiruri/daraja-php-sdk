<?php
declare(strict_types=1);
namespace Daraja\Laravel\Events;
use Daraja\Webhooks\Payloads\B2BResult;
final class B2BResultReceived
{
    public function __construct(public readonly B2BResult $callback) {}
}
