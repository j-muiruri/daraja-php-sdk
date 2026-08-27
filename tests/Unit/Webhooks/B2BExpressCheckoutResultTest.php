<?php

declare(strict_types=1);

namespace Daraja\Tests\Unit\Webhooks;

use Daraja\Exceptions\ValidationException;
use Daraja\Webhooks\CallbackProcessor;
use Daraja\Webhooks\Payloads\B2BExpressCheckoutResult;
use PHPUnit\Framework\TestCase;

final class B2BExpressCheckoutResultTest extends TestCase
{
    private function successPayload(): array
    {
        return [
            'resultCode'     => '0',
            'resultDesc'     => 'The service request is processed successfully.',
            'amount'         => '71.0',
            'requestId'      => '404e1aec-19e0-4ce3-973d-bd92e94c8021',
            'resultType'     => '0',
            'conversationID' => 'AG_20230426_2010434680d9f5a73766',
            'transactionId'  => 'RDQ01NFT1Q',
            'status'         => 'SUCCESS',
        ];
    }

    private function cancelledPayload(): array
    {
        return [
            'resultCode'       => '4001',
            'resultDesc'       => 'User cancelled transaction',
            'requestId'        => 'c2a9ba32-9e11-4b90-892c-7bc54944609a',
            'amount'           => '71.0',
            'paymentReference' => 'MAndbubry3hi',
        ];
    }

    public function test_parses_successful_payload(): void
    {
        $result = B2BExpressCheckoutResult::fromArray($this->successPayload());

        self::assertTrue($result->isSuccessful());
        self::assertSame('0', $result->resultCode());
        self::assertSame('404e1aec-19e0-4ce3-973d-bd92e94c8021', $result->requestId);
        self::assertSame(71.0, $result->amount);
        self::assertSame('RDQ01NFT1Q', $result->transactionId);
        self::assertSame('SUCCESS', $result->status);
        self::assertFalse($result->wasCancelled());
    }

    public function test_parses_cancelled_payload(): void
    {
        $result = B2BExpressCheckoutResult::fromArray($this->cancelledPayload());

        self::assertFalse($result->isSuccessful());
        self::assertTrue($result->wasCancelled());
        self::assertSame('User cancelled transaction', $result->resultDescription());
        self::assertSame('MAndbubry3hi', $result->paymentReference);
        self::assertNull($result->transactionId);
    }

    public function test_throws_when_request_id_missing(): void
    {
        $this->expectException(ValidationException::class);

        B2BExpressCheckoutResult::fromArray(['resultCode' => '0']);
    }

    public function test_callback_processor_auto_detects_and_dispatches(): void
    {
        $dispatched = null;

        $processor = new CallbackProcessor();
        $processor->onB2BExpressCheckout(function (B2BExpressCheckoutResult $result) use (&$dispatched) {
            $dispatched = $result;
        });

        $processor->process(json_encode($this->successPayload(), JSON_THROW_ON_ERROR));

        self::assertInstanceOf(B2BExpressCheckoutResult::class, $dispatched);
        self::assertTrue($dispatched->isSuccessful());
    }
}
