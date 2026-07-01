<?php

declare(strict_types=1);

namespace Daraja\Laravel\Http\Controllers;

use Daraja\Laravel\Events\AccountBalanceReceived;
use Daraja\Laravel\Events\B2BResultReceived;
use Daraja\Laravel\Events\B2CResultReceived;
use Daraja\Laravel\Events\BillManagerReconciliationReceived;
use Daraja\Laravel\Events\C2BConfirmationReceived;
use Daraja\Laravel\Events\C2BValidationReceived;
use Daraja\Laravel\Events\ReversalResultReceived;
use Daraja\Laravel\Events\STKCallbackReceived;
use Daraja\Laravel\Events\TransactionStatusReceived;
use Daraja\Webhooks\CallbackProcessor;
use Daraja\Webhooks\Payloads\C2BValidation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Psr\Log\LoggerInterface;

/**
 * Handles all Daraja M-Pesa webhook routes.
 *
 * Auto-registered routes (prefix configurable via config/daraja.php):
 *   POST /mpesa/stk/callback
 *   POST /mpesa/c2b/validation
 *   POST /mpesa/c2b/confirmation
 *   POST /mpesa/b2c/result           + /mpesa/b2c/timeout
 *   POST /mpesa/b2b/result           + /mpesa/b2b/timeout
 *   POST /mpesa/balance/result       + /mpesa/balance/timeout
 *   POST /mpesa/status/result        + /mpesa/status/timeout
 *   POST /mpesa/reversal/result      + /mpesa/reversal/timeout
 *   POST /mpesa/tax/result           + /mpesa/tax/timeout
 *   POST /mpesa/bill/reconciliation
 *
 * Override behaviour: extend this class and rebind in the IoC container.
 *
 * IMPORTANT: Every Daraja callback MUST receive HTTP 200 immediately.
 * All exceptions are caught and logged — the response is always 200.
 */
class MpesaWebhookController extends Controller
{
    public function __construct(
        private readonly CallbackProcessor $processor,
        private readonly LoggerInterface   $logger,
    ) {}

    // ── STK Push ──────────────────────────────────────────────────────────────

    public function handleSTKCallback(Request $request): JsonResponse
    {
        return $this->handle($request, function (string $json): void {
            $cb = $this->processor->parseSTK($json);
            $this->log('stk_callback', $cb->checkoutRequestId, $cb->isSuccessful());
            event(new STKCallbackReceived($cb));
        });
    }

    // ── C2B ───────────────────────────────────────────────────────────────────

    public function handleC2BValidation(Request $request): JsonResponse
    {
        try {
            $cb = $this->processor->parseC2BValidation($request->getContent());
            $this->log('c2b_validation', $cb->transactionId, true);
            event(new C2BValidationReceived($cb));

            return response()->json(C2BValidation::accept());
        } catch (\Throwable $e) {
            $this->logger->error('Daraja C2BValidation error', [
                'error' => $e->getMessage(),
                'body'  => $request->getContent(),
            ]);

            return response()->json(C2BValidation::reject(
                C2BValidation::REJECT_CODE_GENERAL,
                'Validation service unavailable',
            ));
        }
    }

    public function handleC2BConfirmation(Request $request): JsonResponse
    {
        return $this->handle($request, function (string $json): void {
            $cb = $this->processor->parseC2BConfirmation($json);
            $this->log('c2b_confirmation', $cb->transactionId, true);
            event(new C2BConfirmationReceived($cb));
        });
    }

    // ── B2C ───────────────────────────────────────────────────────────────────

    public function handleB2CResult(Request $request): JsonResponse
    {
        return $this->handle($request, function (string $json): void {
            $cb = $this->processor->parseB2C($json);
            $this->log('b2c_result', $cb->transactionId, $cb->isSuccessful());
            event(new B2CResultReceived($cb));
        });
    }

    // ── B2B (also covers Tax Remittance) ──────────────────────────────────────

    public function handleB2BResult(Request $request): JsonResponse
    {
        return $this->handle($request, function (string $json): void {
            $cb = $this->processor->parseB2B($json);
            $this->log('b2b_result', $cb->transactionId, $cb->isSuccessful());
            event(new B2BResultReceived($cb));
        });
    }

    // ── Account Balance ───────────────────────────────────────────────────────

    public function handleAccountBalance(Request $request): JsonResponse
    {
        return $this->handle($request, function (string $json): void {
            $cb = $this->processor->parseAccountBalance($json);
            $this->log('account_balance', $cb->transactionId, $cb->isSuccessful());
            event(new AccountBalanceReceived($cb));
        });
    }

    // ── Transaction Status ────────────────────────────────────────────────────

    public function handleTransactionStatus(Request $request): JsonResponse
    {
        return $this->handle($request, function (string $json): void {
            $cb = $this->processor->parseTransactionStatus($json);
            $this->log('transaction_status', $cb->transactionId, $cb->isSuccessful());
            event(new TransactionStatusReceived($cb));
        });
    }

    // ── Reversal ──────────────────────────────────────────────────────────────

    public function handleReversal(Request $request): JsonResponse
    {
        return $this->handle($request, function (string $json): void {
            $cb = $this->processor->parseReversal($json);
            $this->log('reversal_result', $cb->transactionId, $cb->isSuccessful());
            event(new ReversalResultReceived($cb));
        });
    }

    // ── Bill Manager ─────────────────────────────────────────────────────────

    public function handleBillManagerReconciliation(Request $request): JsonResponse
    {
        return $this->handle($request, function (string $json): void {
            $cb = $this->processor->parseBillManagerReconciliation($json);
            $this->log('bill_manager_reconciliation', $cb->transactionId, true);
            event(new BillManagerReconciliationReceived($cb));
        });
    }

    // ── Shared timeout handler ────────────────────────────────────────────────

    public function handleTimeout(Request $request): JsonResponse
    {
        $this->logger->warning('Daraja: queue timeout received', [
            'path' => $request->path(),
            'body' => $request->getContent(),
        ]);

        return $this->accepted();
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    /** @param \Closure(string): void $handler */
    private function handle(Request $request, \Closure $handler): JsonResponse
    {
        try {
            $handler($request->getContent());
        } catch (\Throwable $e) {
            $this->logger->error('Daraja webhook error', [
                'error' => $e->getMessage(),
                'path'  => $request->path(),
                'body'  => $request->getContent(),
            ]);
        }

        return $this->accepted();
    }

    private function accepted(): JsonResponse
    {
        return response()->json(['ResultCode' => '0', 'ResultDesc' => 'Accepted']);
    }

    private function log(string $type, string $id, bool $success): void
    {
        $this->logger->info("Daraja: {$type}", ['id' => $id, 'success' => $success]);
    }
}
