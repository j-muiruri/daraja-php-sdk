<?php

declare(strict_types=1);

namespace Daraja\Tests\Unit\Webhooks;

use Daraja\Exceptions\ValidationException;
use Daraja\Webhooks\CallbackProcessor;
use Daraja\Webhooks\Payloads\AccountBalanceResult;
use Daraja\Webhooks\Payloads\B2CResult;
use Daraja\Webhooks\Payloads\C2BConfirmation;
use Daraja\Webhooks\Payloads\C2BValidation;
use Daraja\Webhooks\Payloads\ReversalResult;
use Daraja\Webhooks\Payloads\STKCallback;
use Daraja\Webhooks\Payloads\TransactionStatusResult;
use PHPUnit\Framework\TestCase;

// ============================================================
// STKCallback

final class B2CResultTest extends TestCase
{
    private function payload(int $code = 0): array
    {
        return [
            'Result' => [
                'ResultType'              => 0,
                'ResultCode'              => $code,
                'ResultDesc'              => 'The service request is processed successfully.',
                'OriginatorConversationID' => '29112-34801843-1',
                'ConversationID'          => 'AG_20191219_00005797af5d7d75f652',
                'TransactionID'           => 'QHT3XXXXXXXXXXX',
                'ResultParameters'        => [
                    'ResultParameter' => [
                        ['Key' => 'TransactionAmount',                   'Value' => 500],
                        ['Key' => 'TransactionReceipt',                  'Value' => 'QHT3XXXXXXXXXXX'],
                        ['Key' => 'B2CRecipientIsRegisteredCustomer',    'Value' => 'Y'],
                        ['Key' => 'B2CChargesPaidAccountAvailableFunds', 'Value' => -4510.00],
                        ['Key' => 'ReceiverPartyPublicName',             'Value' => '254708374149 - John Doe'],
                        ['Key' => 'TransactionCompletedDateTime',        'Value' => '19.12.2019 11:45:50'],
                        ['Key' => 'B2CUtilityAccountAvailableFunds',     'Value' => 10116.00],
                        ['Key' => 'B2CWorkingAccountAvailableFunds',     'Value' => 900000.00],
                    ],
                ],
            ],
        ];
    }

    public function test_parses_successful_b2c_result(): void
    {
        $cb = B2CResult::fromArray($this->payload());

        self::assertTrue($cb->isSuccessful());
        self::assertSame('QHT3XXXXXXXXXXX', $cb->transactionId);
        self::assertSame(500.0, $cb->transactionAmount);
        self::assertSame('QHT3XXXXXXXXXXX', $cb->transactionReceipt);
        self::assertTrue($cb->recipientIsRegistered);
        self::assertSame('254708374149 - John Doe', $cb->receiverPublicName);
        self::assertSame(10116.0, $cb->utilityAccountBalance);
        self::assertSame(900000.0, $cb->workingAccountBalance);
    }

    public function test_parses_b2c_completed_datetime(): void
    {
        $cb = B2CResult::fromArray($this->payload());

        self::assertInstanceOf(\DateTimeImmutable::class, $cb->completedAt);
        self::assertSame('2019-12-19 11:45:50', $cb->completedAt->format('Y-m-d H:i:s'));
    }

    public function test_parses_failed_b2c_result(): void
    {
        $cb = B2CResult::fromArray($this->payload(2001));

        self::assertFalse($cb->isSuccessful());
        self::assertNull($cb->transactionAmount);
        self::assertNull($cb->transactionReceipt);
    }

    public function test_throws_on_missing_result_block(): void
    {
        $this->expectException(ValidationException::class);

        B2CResult::fromArray(['wrong' => 'structure']);
    }
}
