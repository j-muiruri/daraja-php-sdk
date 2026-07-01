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

final class C2BConfirmationTest extends TestCase
{
    private function payload(): array
    {
        return [
            'TransactionType'   => 'Pay Bill',
            'TransID'           => 'LGR019G3J4',
            'TransTime'         => '20170816190243',
            'TransAmount'       => '200.00',
            'BusinessShortCode' => '600610',
            'BillRefNumber'     => 'account001',
            'InvoiceNumber'     => '',
            'OrgAccountBalance' => '49197.00',
            'ThirdPartyTransID' => '',
            'MSISDN'            => '254708374149',
            'FirstName'         => 'John',
            'MiddleName'        => '',
            'LastName'          => 'Doe',
        ];
    }

    public function test_parses_c2b_confirmation(): void
    {
        $cb = C2BConfirmation::fromArray($this->payload());

        self::assertTrue($cb->isSuccessful());
        self::assertSame('LGR019G3J4', $cb->transactionId);
        self::assertSame(200.0, $cb->amount);
        self::assertSame('254708374149', $cb->msisdn);
        self::assertSame('600610', $cb->businessShortCode);
        self::assertSame('account001', $cb->billRefNumber);
    }

    public function test_customer_full_name_is_concatenated(): void
    {
        $cb = C2BConfirmation::fromArray($this->payload());

        self::assertSame('John Doe', $cb->customerFullName());
    }

    public function test_customer_full_name_skips_empty_middle_name(): void
    {
        $payload               = $this->payload();
        $payload['MiddleName'] = '';
        $cb                    = C2BConfirmation::fromArray($payload);

        self::assertSame('John Doe', $cb->customerFullName());
    }

    public function test_is_pay_bill_detection(): void
    {
        $cb = C2BConfirmation::fromArray($this->payload());

        self::assertTrue($cb->isPayBill());
        self::assertFalse($cb->isBuyGoods());
    }

    public function test_is_buy_goods_detection(): void
    {
        $payload                    = $this->payload();
        $payload['TransactionType'] = 'Buy Goods';
        $cb                         = C2BConfirmation::fromArray($payload);

        self::assertTrue($cb->isBuyGoods());
        self::assertFalse($cb->isPayBill());
    }

    public function test_parses_transaction_time(): void
    {
        $cb = C2BConfirmation::fromArray($this->payload());

        self::assertInstanceOf(\DateTimeImmutable::class, $cb->transactionTime);
        self::assertSame('2017', $cb->transactionTime->format('Y'));
        self::assertSame('08', $cb->transactionTime->format('m'));
        self::assertSame('16', $cb->transactionTime->format('d'));
    }

    public function test_throws_on_missing_trans_id(): void
    {
        $this->expectException(ValidationException::class);

        C2BConfirmation::fromArray(['TransID' => '']);
    }
}
