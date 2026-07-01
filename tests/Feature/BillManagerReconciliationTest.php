<?php

declare(strict_types=1);

namespace Daraja\Tests\Feature;

use Daraja\Exceptions\ValidationException;
use Daraja\Services\BillManager;
use Daraja\Services\TaxRemittance;
use Daraja\Tests\DarajaTestCase;
use Daraja\ValueObjects\Invoice;
use Daraja\ValueObjects\InvoiceItem;
use Daraja\Webhooks\CallbackProcessor;
use Daraja\Webhooks\Payloads\B2BResult;
use Daraja\Webhooks\Payloads\BillManagerReconciliation;

// ============================================================
// TaxRemittance

final class BillManagerReconciliationTest extends DarajaTestCase
{
    private function payload(): array
    {
        return [
            'transactiontype'   => 'Pay Bill',
            'transID'           => 'OEL6XXXXXXX',
            'transTime'         => '20250630120000',
            'transAmount'       => '500.00',
            'businessShortCode' => '600610',
            'billRefNumber'     => 'ACC-001',
            'invoiceNumber'     => 'INV-2025-001',
            'OrgAccountBalance' => '',
            'ThirdPartyTransID' => '',
            'MSISDN'            => '254712345678',
            'FirstName'         => 'Jane',
            'MiddleName'        => 'A',
            'LastName'          => 'Wanjiru',
        ];
    }

    public function test_parses_bill_manager_reconciliation(): void
    {
        $cb = BillManagerReconciliation::fromArray($this->payload());

        self::assertTrue($cb->isSuccessful());
        self::assertSame('OEL6XXXXXXX', $cb->transactionId);
        self::assertSame(500.0, $cb->amount);
        self::assertSame('INV-2025-001', $cb->invoiceNumber);
        self::assertSame('ACC-001', $cb->billRefNumber);
        self::assertSame('254712345678', $cb->msisdn);
    }

    public function test_customer_full_name_with_middle(): void
    {
        $cb = BillManagerReconciliation::fromArray($this->payload());

        self::assertSame('Jane A Wanjiru', $cb->customerFullName());
    }

    public function test_parses_transaction_time(): void
    {
        $cb = BillManagerReconciliation::fromArray($this->payload());

        self::assertInstanceOf(\DateTimeImmutable::class, $cb->transactionTime);
        self::assertSame('2025-06-30', $cb->transactionTime->format('Y-m-d'));
    }

    public function test_processor_auto_detects_bill_manager_payload(): void
    {
        $processor = new CallbackProcessor();
        $json      = json_encode($this->payload(), JSON_THROW_ON_ERROR);
        $result    = $processor->parse($json);

        self::assertInstanceOf(BillManagerReconciliation::class, $result);
    }

    public function test_throws_on_missing_trans_id(): void
    {
        $this->expectException(ValidationException::class);

        BillManagerReconciliation::fromArray(['noTransId' => 'here']);
    }

    public function test_handles_uppercase_key_variant(): void
    {
        // Some Daraja environments send uppercase keys
        $payload              = $this->payload();
        $payload['TransID']   = $payload['transID'];
        unset($payload['transID']);

        $cb = BillManagerReconciliation::fromArray($payload);

        self::assertSame('OEL6XXXXXXX', $cb->transactionId);
    }
}
