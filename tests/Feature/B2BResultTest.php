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

final class B2BResultTest extends DarajaTestCase
{
    private function payload(): array
    {
        return [
            'Result' => [
                'ResultCode'              => 0,
                'ResultDesc'              => 'The service request is processed successfully.',
                'OriginatorConversationID' => 'b2b-conv-001',
                'ConversationID'          => 'AG_20250630_00001234',
                'TransactionID'           => 'QHT3XXXXXXXXXXX',
                'ResultParameters'        => [
                    'ResultParameter' => [
                        ['Key' => 'Amount',                         'Value' => 10000],
                        ['Key' => 'ReceiverPartyPublicName',        'Value' => '000001 - Supplier Ltd'],
                        ['Key' => 'Currency',                       'Value' => 'KES'],
                        ['Key' => 'DebitPartyCharges',              'Value' => ''],
                        ['Key' => 'DebitPartyAffectedAccountBalance', 'Value' => 'Working Account|KES|36713.00|36713.00|0.00|0.00'],
                        ['Key' => 'TransCompletedTime',             'Value' => 20250630120000],
                    ],
                ],
            ],
        ];
    }

    public function test_parses_b2b_result(): void
    {
        $cb = B2BResult::fromArray($this->payload());

        self::assertTrue($cb->isSuccessful());
        self::assertSame('QHT3XXXXXXXXXXX', $cb->transactionId);
        self::assertSame(10000.0, $cb->amount);
        self::assertSame('000001 - Supplier Ltd', $cb->receiverPublicName);
        self::assertSame('KES', $cb->currency);
        self::assertInstanceOf(\DateTimeImmutable::class, $cb->completedAt);
    }

    public function test_throws_on_missing_result_block(): void
    {
        $this->expectException(ValidationException::class);

        B2BResult::fromArray(['wrong' => 'structure']);
    }

    public function test_processor_detects_b2b_result(): void
    {
        $processor = new CallbackProcessor();
        $json      = json_encode($this->payload(), JSON_THROW_ON_ERROR);
        $result    = $processor->parse($json);

        self::assertInstanceOf(B2BResult::class, $result);
    }
}
