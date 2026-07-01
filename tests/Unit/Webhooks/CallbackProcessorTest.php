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

final class CallbackProcessorTest extends TestCase
{
    private CallbackProcessor $processor;

    protected function setUp(): void
    {
        $this->processor = new CallbackProcessor();
    }

    public function test_detects_stk_callback(): void
    {
        $json = json_encode([
            'Body' => [
                'stkCallback' => [
                    'MerchantRequestID' => '1',
                    'CheckoutRequestID' => 'ws_CO_xxx',
                    'ResultCode'        => 0,
                    'ResultDesc'        => 'OK',
                    'CallbackMetadata'  => ['Item' => [
                        ['Name' => 'Amount',             'Value' => 100],
                        ['Name' => 'MpesaReceiptNumber', 'Value' => 'QHT3X'],
                        ['Name' => 'TransactionDate',    'Value' => 20191219102115],
                        ['Name' => 'PhoneNumber',        'Value' => 254712345678],
                    ]],
                ],
            ],
        ], JSON_THROW_ON_ERROR);

        $result = $this->processor->parse($json);

        self::assertInstanceOf(STKCallback::class, $result);
    }

    public function test_detects_c2b_confirmation(): void
    {
        $json = json_encode([
            'TransactionType'   => 'Pay Bill',
            'TransID'           => 'LGR019G3J4',
            'TransTime'         => '20170816190243',
            'TransAmount'       => '200.00',
            'BusinessShortCode' => '600610',
            'BillRefNumber'     => 'acc',
            'MSISDN'            => '254708374149',
            'FirstName'         => 'John',
            'MiddleName'        => '',
            'LastName'          => 'Doe',
        ], JSON_THROW_ON_ERROR);

        $result = $this->processor->parse($json);

        self::assertInstanceOf(C2BConfirmation::class, $result);
    }

    public function test_detects_account_balance_result(): void
    {
        $json = json_encode([
            'Result' => [
                'ResultCode'              => 0,
                'ResultDesc'              => 'OK',
                'OriginatorConversationID' => 'abc',
                'ConversationID'          => 'xyz',
                'TransactionID'           => 'T1',
                'ResultParameters'        => [
                    'ResultParameter' => [
                        ['Key' => 'AccountBalance', 'Value' => 'Working Account|KES|100.00|100.00|0.00|0.00'],
                    ],
                ],
            ],
        ], JSON_THROW_ON_ERROR);

        $result = $this->processor->parse($json);

        self::assertInstanceOf(AccountBalanceResult::class, $result);
    }

    public function test_detects_b2c_result(): void
    {
        $json = json_encode([
            'Result' => [
                'ResultCode'              => 0,
                'ResultDesc'              => 'OK',
                'OriginatorConversationID' => 'abc',
                'ConversationID'          => 'xyz',
                'TransactionID'           => 'T1',
                'ResultParameters'        => [
                    'ResultParameter' => [
                        ['Key' => 'TransactionAmount', 'Value' => 500],
                        ['Key' => 'TransactionReceipt', 'Value' => 'QHT3X'],
                    ],
                ],
            ],
        ], JSON_THROW_ON_ERROR);

        $result = $this->processor->parse($json);

        self::assertInstanceOf(B2CResult::class, $result);
    }

    public function test_detects_reversal_result(): void
    {
        $json = json_encode([
            'Result' => [
                'ResultCode'              => 0,
                'ResultDesc'              => 'OK',
                'OriginatorConversationID' => 'abc',
                'ConversationID'          => 'xyz',
                'TransactionID'           => 'OEI2AK4Q16',
            ],
        ], JSON_THROW_ON_ERROR);

        $result = $this->processor->parse($json);

        self::assertInstanceOf(ReversalResult::class, $result);
    }

    public function test_throws_on_unknown_payload(): void
    {
        $this->expectException(ValidationException::class);

        $this->processor->parse(json_encode(['unknown' => 'payload'], JSON_THROW_ON_ERROR));
    }

    public function test_handler_is_invoked_on_process(): void
    {
        $invoked = false;

        $this->processor->onSTK(function (STKCallback $cb) use (&$invoked): void {
            $invoked = true;
            self::assertSame('ws_CO_xxx', $cb->checkoutRequestId);
        });

        $json = json_encode([
            'Body' => [
                'stkCallback' => [
                    'MerchantRequestID' => '1',
                    'CheckoutRequestID' => 'ws_CO_xxx',
                    'ResultCode'        => 0,
                    'ResultDesc'        => 'OK',
                    'CallbackMetadata'  => ['Item' => [
                        ['Name' => 'Amount',             'Value' => 100],
                        ['Name' => 'MpesaReceiptNumber', 'Value' => 'QHT3X'],
                        ['Name' => 'TransactionDate',    'Value' => 20191219102115],
                        ['Name' => 'PhoneNumber',        'Value' => 254712345678],
                    ]],
                ],
            ],
        ], JSON_THROW_ON_ERROR);

        $this->processor->process($json);

        self::assertTrue($invoked, 'STK handler was not invoked');
    }

    public function test_multiple_handlers_all_invoked(): void
    {
        $count = 0;

        $this->processor
            ->onReversal(function (ReversalResult $cb) use (&$count): void { $count++; })
            ->onReversal(function (ReversalResult $cb) use (&$count): void { $count++; });

        $json = json_encode([
            'Result' => [
                'ResultCode'              => 0,
                'ResultDesc'              => 'OK',
                'OriginatorConversationID' => 'abc',
                'ConversationID'          => 'xyz',
                'TransactionID'           => 'OEI2AK4Q16',
            ],
        ], JSON_THROW_ON_ERROR);

        $this->processor->process($json);

        self::assertSame(2, $count);
    }
}
