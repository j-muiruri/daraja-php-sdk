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

final class STKCallbackTest extends TestCase
{
    private function successPayload(): array
    {
        return [
            'Body' => [
                'stkCallback' => [
                    'MerchantRequestID' => '29115-34620561-1',
                    'CheckoutRequestID' => 'ws_CO_191220191020363925',
                    'ResultCode'        => 0,
                    'ResultDesc'        => 'The service request is processed successfully.',
                    'CallbackMetadata'  => [
                        'Item' => [
                            ['Name' => 'Amount',             'Value' => 1500],
                            ['Name' => 'MpesaReceiptNumber', 'Value' => 'QHT3XXXXXXXXXXX'],
                            ['Name' => 'Balance',            'Value' => ''],
                            ['Name' => 'TransactionDate',    'Value' => 20191219102115],
                            ['Name' => 'PhoneNumber',        'Value' => 254712345678],
                        ],
                    ],
                ],
            ],
        ];
    }

    private function failurePayload(int $code = 1032): array
    {
        return [
            'Body' => [
                'stkCallback' => [
                    'MerchantRequestID' => '29115-34620561-1',
                    'CheckoutRequestID' => 'ws_CO_191220191020363925',
                    'ResultCode'        => $code,
                    'ResultDesc'        => 'Request cancelled by user',
                ],
            ],
        ];
    }

    public function test_parses_successful_stk_callback(): void
    {
        $cb = STKCallback::fromArray($this->successPayload());

        self::assertTrue($cb->isSuccessful());
        self::assertSame('ws_CO_191220191020363925', $cb->checkoutRequestId);
        self::assertSame(1500, $cb->amount);
        self::assertSame('QHT3XXXXXXXXXXX', $cb->receiptNumber);
        self::assertSame('254712345678', $cb->phoneNumber);
        self::assertSame('0', $cb->resultCode());
        self::assertInstanceOf(\DateTimeImmutable::class, $cb->transactionDate);
        self::assertSame('2019', $cb->transactionDate->format('Y'));
    }

    public function test_parses_failed_stk_callback(): void
    {
        $cb = STKCallback::fromArray($this->failurePayload(1032));

        self::assertFalse($cb->isSuccessful());
        self::assertTrue($cb->wasCancelled());
        self::assertNull($cb->amount);
        self::assertNull($cb->receiptNumber);
    }

    public function test_detects_insufficient_funds(): void
    {
        $cb = STKCallback::fromArray($this->failurePayload(1));

        self::assertTrue($cb->hadInsufficientFunds());
        self::assertFalse($cb->wasCancelled());
    }

    public function test_parses_from_json_string(): void
    {
        $json = json_encode($this->successPayload(), JSON_THROW_ON_ERROR);
        $cb   = STKCallback::fromJson($json);

        self::assertTrue($cb->isSuccessful());
    }

    public function test_throws_on_missing_stk_callback_block(): void
    {
        $this->expectException(ValidationException::class);

        STKCallback::fromArray(['Body' => ['wrongKey' => []]]);
    }

    public function test_raw_returns_original_payload(): void
    {
        $payload = $this->successPayload();
        $cb      = STKCallback::fromArray($payload);

        self::assertSame($payload, $cb->raw());
    }
}
