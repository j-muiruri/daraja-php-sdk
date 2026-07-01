<?php
declare(strict_types=1);
namespace Daraja\Laravel\Events;
use Daraja\Webhooks\Payloads\BillManagerReconciliation;
final class BillManagerReconciliationReceived
{
    public function __construct(public readonly BillManagerReconciliation $callback) {}
}
