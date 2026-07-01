<?php

declare(strict_types=1);

namespace Daraja\Webhooks;

use Daraja\Exceptions\ValidationException;
use Daraja\Webhooks\Contracts\Callback;
use Daraja\Webhooks\Payloads\AbstractCallback;
use Daraja\Webhooks\Payloads\AccountBalanceResult;
use Daraja\Webhooks\Payloads\B2BResult;
use Daraja\Webhooks\Payloads\B2CResult;
use Daraja\Webhooks\Payloads\BillManagerReconciliation;
use Daraja\Webhooks\Payloads\C2BConfirmation;
use Daraja\Webhooks\Payloads\C2BValidation;
use Daraja\Webhooks\Payloads\ReversalResult;
use Daraja\Webhooks\Payloads\STKCallback;
use Daraja\Webhooks\Payloads\TransactionStatusResult;

/**
 * Central factory for parsing raw Daraja webhook payloads into typed objects.
 *
 * Covers all Daraja APIs:
 *  - STK Push callback
 *  - C2B confirmation & validation
 *  - B2C result
 *  - B2B result (including Tax Remittance)
 *  - Account Balance result
 *  - Transaction Status result
 *  - Reversal result
 *  - Bill Manager reconciliation
 *
 * Usage (standalone PHP):
 *   $processor = new CallbackProcessor();
 *   $processor
 *       ->onSTK(fn(STKCallback $cb) => Order::markPaid($cb->checkoutRequestId))
 *       ->onC2BConfirmation(fn(C2BConfirmation $cb) => Payment::record($cb))
 *       ->onB2C(fn(B2CResult $cb) => Disbursement::finalise($cb))
 *       ->onBillManagerReconciliation(fn(BillManagerReconciliation $cb) => Invoice::markPaid($cb));
 *
 *   $callback = $processor->process($request->getContent());
 */
final class CallbackProcessor
{
    /** @var list<\Closure(STKCallback): void> */
    private array $stkHandlers = [];

    /** @var list<\Closure(C2BConfirmation): void> */
    private array $c2bConfirmHandlers = [];

    /** @var list<\Closure(C2BValidation): void> */
    private array $c2bValidationHandlers = [];

    /** @var list<\Closure(B2CResult): void> */
    private array $b2cHandlers = [];

    /** @var list<\Closure(B2BResult): void> */
    private array $b2bHandlers = [];

    /** @var list<\Closure(AccountBalanceResult): void> */
    private array $balanceHandlers = [];

    /** @var list<\Closure(TransactionStatusResult): void> */
    private array $txStatusHandlers = [];

    /** @var list<\Closure(ReversalResult): void> */
    private array $reversalHandlers = [];

    /** @var list<\Closure(BillManagerReconciliation): void> */
    private array $billManagerHandlers = [];

    // -------------------------------------------------------------------------
    // Handler registration (fluent)
    // -------------------------------------------------------------------------

    /** @param \Closure(STKCallback): void $handler */
    public function onSTK(\Closure $handler): self
    {
        $this->stkHandlers[] = $handler;
        return $this;
    }

    /** @param \Closure(C2BConfirmation): void $handler */
    public function onC2BConfirmation(\Closure $handler): self
    {
        $this->c2bConfirmHandlers[] = $handler;
        return $this;
    }

    /** @param \Closure(C2BValidation): void $handler */
    public function onC2BValidation(\Closure $handler): self
    {
        $this->c2bValidationHandlers[] = $handler;
        return $this;
    }

    /** @param \Closure(B2CResult): void $handler */
    public function onB2C(\Closure $handler): self
    {
        $this->b2cHandlers[] = $handler;
        return $this;
    }

    /** @param \Closure(B2BResult): void $handler */
    public function onB2B(\Closure $handler): self
    {
        $this->b2bHandlers[] = $handler;
        return $this;
    }

    /** @param \Closure(AccountBalanceResult): void $handler */
    public function onAccountBalance(\Closure $handler): self
    {
        $this->balanceHandlers[] = $handler;
        return $this;
    }

    /** @param \Closure(TransactionStatusResult): void $handler */
    public function onTransactionStatus(\Closure $handler): self
    {
        $this->txStatusHandlers[] = $handler;
        return $this;
    }

    /** @param \Closure(ReversalResult): void $handler */
    public function onReversal(\Closure $handler): self
    {
        $this->reversalHandlers[] = $handler;
        return $this;
    }

    /** @param \Closure(BillManagerReconciliation): void $handler */
    public function onBillManagerReconciliation(\Closure $handler): self
    {
        $this->billManagerHandlers[] = $handler;
        return $this;
    }

    // -------------------------------------------------------------------------
    // Auto-detect and dispatch
    // -------------------------------------------------------------------------

    /**
     * Auto-detect callback type, parse it, invoke handlers, return the payload.
     *
     * @throws ValidationException|\JsonException
     */
    public function process(string $json): Callback
    {
        $callback = $this->parse($json);

        match (true) {
            $callback instanceof STKCallback                => $this->dispatch($this->stkHandlers, $callback),
            $callback instanceof C2BConfirmation            => $this->dispatch($this->c2bConfirmHandlers, $callback),
            $callback instanceof C2BValidation              => $this->dispatch($this->c2bValidationHandlers, $callback),
            $callback instanceof B2CResult                  => $this->dispatch($this->b2cHandlers, $callback),
            $callback instanceof B2BResult                  => $this->dispatch($this->b2bHandlers, $callback),
            $callback instanceof AccountBalanceResult        => $this->dispatch($this->balanceHandlers, $callback),
            $callback instanceof TransactionStatusResult     => $this->dispatch($this->txStatusHandlers, $callback),
            $callback instanceof ReversalResult              => $this->dispatch($this->reversalHandlers, $callback),
            $callback instanceof BillManagerReconciliation   => $this->dispatch($this->billManagerHandlers, $callback),
            default                                         => null,
        };

        return $callback;
    }

    /**
     * Auto-detect the callback type from raw JSON.
     *
     * @throws ValidationException|\JsonException
     */
    public function parse(string $json): Callback
    {
        /** @var array<string, mixed> $data */
        $data = json_decode($json, true, 512, JSON_THROW_ON_ERROR);

        return $this->parseArray($data);
    }

    /**
     * @param  array<string, mixed> $data
     * @throws ValidationException
     */
    public function parseArray(array $data): Callback
    {
        // STK Push callback
        if (isset($data['Body']['stkCallback'])) {
            return new STKCallback($data);
        }

        // Bill Manager uses lowercase transID key
        if (isset($data['transID']) && !isset($data['Result'])) {
            return new BillManagerReconciliation($data);
        }

        // C2B / Bill Manager — uppercase TransID
        if (isset($data['TransID']) && !isset($data['Result'])) {
            return new C2BConfirmation($data);
        }

        // All async result callbacks
        if (isset($data['Result'])) {
            return $this->parseResultBlock($data);
        }

        throw new ValidationException(
            ['payload' => 'Unable to determine callback type from payload structure'],
        );
    }

    // -------------------------------------------------------------------------
    // Named parsers (use when you know the type from your route)
    // -------------------------------------------------------------------------

    /** @throws ValidationException|\JsonException */
    public function parseSTK(string $json): STKCallback
    {
        return STKCallback::fromJson($json);
    }

    /** @throws ValidationException|\JsonException */
    public function parseC2BConfirmation(string $json): C2BConfirmation
    {
        return C2BConfirmation::fromJson($json);
    }

    /** @throws ValidationException|\JsonException */
    public function parseC2BValidation(string $json): C2BValidation
    {
        return C2BValidation::fromJson($json);
    }

    /** @throws ValidationException|\JsonException */
    public function parseB2C(string $json): B2CResult
    {
        return B2CResult::fromJson($json);
    }

    /** @throws ValidationException|\JsonException */
    public function parseB2B(string $json): B2BResult
    {
        return B2BResult::fromJson($json);
    }

    /** @throws ValidationException|\JsonException */
    public function parseAccountBalance(string $json): AccountBalanceResult
    {
        return AccountBalanceResult::fromJson($json);
    }

    /** @throws ValidationException|\JsonException */
    public function parseTransactionStatus(string $json): TransactionStatusResult
    {
        return TransactionStatusResult::fromJson($json);
    }

    /** @throws ValidationException|\JsonException */
    public function parseReversal(string $json): ReversalResult
    {
        return ReversalResult::fromJson($json);
    }

    /** @throws ValidationException|\JsonException */
    public function parseBillManagerReconciliation(string $json): BillManagerReconciliation
    {
        return BillManagerReconciliation::fromJson($json);
    }

    // -------------------------------------------------------------------------
    // Private helpers
    // -------------------------------------------------------------------------

    /**
     * @param  array<string, mixed> $data
     * @throws ValidationException
     */
    private function parseResultBlock(array $data): Callback
    {
        $result = $data['Result'];

        /** @var list<array{Key: string, Value: mixed}> $params */
        $params    = $result['ResultParameters']['ResultParameter'] ?? [];
        $paramKeys = array_column($params, 'Key');

        if (in_array('AccountBalance', $paramKeys, true)) {
            return new AccountBalanceResult($data);
        }

        if (in_array('TransactionStatus', $paramKeys, true)) {
            return new TransactionStatusResult($data);
        }

        if (in_array('TransactionAmount', $paramKeys, true)) {
            return new B2CResult($data);
        }

        // B2B results contain Amount and ReceiverPartyPublicName
        if (in_array('Amount', $paramKeys, true) && in_array('ReceiverPartyPublicName', $paramKeys, true)) {
            return new B2BResult($data);
        }

        // Reversal has no ResultParameters
        return new ReversalResult($data);
    }

    /**
     * @template T of Callback
     * @param list<\Closure(T): void> $handlers
     * @param T $payload
     */
    private function dispatch(array $handlers, Callback $payload): void
    {
        foreach ($handlers as $handler) {
            $handler($payload);  // @phpstan-ignore-line
        }
    }
}
