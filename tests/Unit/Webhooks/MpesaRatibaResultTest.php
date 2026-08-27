<?php

declare(strict_types=1);

namespace Daraja\Tests\Unit\Webhooks;

use Daraja\Exceptions\ValidationException;
use Daraja\Webhooks\CallbackProcessor;
use Daraja\Webhooks\Payloads\MpesaRatibaResult;
use PHPUnit\Framework\TestCase;

final class MpesaRatibaResultTest extends TestCase
{
    private function successPayload(): array
    {
        return [
            'responseHeader' => [
                'responseRefID' => '0acc0239-20fa-4a52-8b9d-9bd64c0465c3',
                'requestRefID'  => '0acc0239-20fa-4a52-8b9d-9bd64c0465c3',
                'responseCode'  => '0',
                'responseDescription' => 'The service request is processed successfully',
            ],
            'responseBody' => [
                'responseData' => [
                    ['name' => 'TransactionID', 'value' => 'SC8F2IQMH5'],
                    ['name' => 'responseCode', 'value' => '0'],
                    ['name' => 'Status', 'value' => 'OKAY'],
                    ['name' => 'Msisdn', 'value' => '254******867'],
                ],
            ],
        ];
    }

    private function failurePayload(): array
    {
        return [
            'ResponseHeader' => [
                'responseRefID' => '4dd9b5d9-d738-42ba-9326-2cc99e966000',
                'requestRefID'  => 'c8c2bb31-3b3a-402e-84fc-21ef35161e48',
                'responseCode'  => '1037',
                'responseDescription' => 'Error',
            ],
            'ResponseBody' => [
                'ResponseData' => [
                    ['Name' => 'TransactionID', 'Value' => '0000000000'],
                    ['Name' => 'responseCode', 'Value' => '1037'],
                    ['Name' => 'Status', 'Value' => 'ERROR'],
                ],
            ],
        ];
    }

    public function test_parses_successful_payload(): void
    {
        $result = MpesaRatibaResult::fromArray($this->successPayload());

        self::assertTrue($result->isSuccessful());
        self::assertSame('0', $result->resultCode());
        self::assertSame('SC8F2IQMH5', $result->transactionId);
        self::assertSame('OKAY', $result->status);
        self::assertSame('254******867', $result->maskedMsisdn);
    }

    public function test_parses_failure_payload_with_pascal_case_keys(): void
    {
        $result = MpesaRatibaResult::fromArray($this->failurePayload());

        self::assertFalse($result->isSuccessful());
        self::assertSame('1037', $result->resultCode());
        self::assertSame('ERROR', $result->status);
    }

    public function test_throws_when_response_header_missing(): void
    {
        $this->expectException(ValidationException::class);

        MpesaRatibaResult::fromArray(['foo' => 'bar']);
    }

    public function test_callback_processor_auto_detects_and_dispatches(): void
    {
        $dispatched = null;

        $processor = new CallbackProcessor();
        $processor->onMpesaRatiba(function (MpesaRatibaResult $result) use (&$dispatched) {
            $dispatched = $result;
        });

        $processor->process(json_encode($this->successPayload(), JSON_THROW_ON_ERROR));

        self::assertInstanceOf(MpesaRatibaResult::class, $dispatched);
        self::assertTrue($dispatched->isSuccessful());
    }
}
