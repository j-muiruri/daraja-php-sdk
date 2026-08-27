<?php

declare(strict_types=1);

namespace Daraja\Tests\Feature;

use Daraja\Exceptions\ValidationException;
use Daraja\Services\PullTransaction;
use Daraja\Tests\DarajaTestCase;

final class PullTransactionServiceTest extends DarajaTestCase
{
    public function test_register_returns_accepted_response(): void
    {
        $config = $this->makeConfig();
        $http   = $this->makeHttpClientWithMockedToken($config, [
            ['status' => 200, 'body' => [
                'ResponseRefID'       => 'feb5e3f2-fbc-4745-844c-ee37b546f627',
                'ResponseStatus'      => '1000',
                'ShortCode'           => '600000',
                'ResponseDescription' => 'Shortcode Registered Successfully',
            ]],
        ]);

        $service  = new PullTransaction($config, $http);
        $response = $service->register(
            shortCode:       '600000',
            nominatedNumber: '254722000000',
        );

        self::assertSame('1000', $response->getString('ResponseStatus'));
        self::assertSame('Shortcode Registered Successfully', $response->getString('ResponseDescription'));
    }

    public function test_register_falls_back_to_config_shortcode_and_callback_url(): void
    {
        $config = $this->makeConfig(['shortcode' => '600123', 'callbackUrl' => 'https://example.com/pull/callback']);
        $http   = $this->makeHttpClientWithMockedToken($config, [
            ['status' => 200, 'body' => ['ResponseStatus' => '1000']],
        ]);

        $service  = new PullTransaction($config, $http);
        $response = $service->register(shortCode: '', nominatedNumber: '254722000000');

        self::assertSame('1000', $response->getString('ResponseStatus'));
    }

    public function test_register_throws_when_nominated_number_missing(): void
    {
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessageMatches('/nominated_number|Nominated/');

        $config  = $this->makeConfig();
        $http    = $this->makeHttpClientWithMockedToken($config, []);
        $service = new PullTransaction($config, $http);

        $service->register('600000', '');
    }

    public function test_query_returns_transaction_list(): void
    {
        $config = $this->makeConfig();
        $http   = $this->makeHttpClientWithMockedToken($config, [
            ['status' => 200, 'body' => [
                'ResponseRefID' => '92713e02-970c-47ac-93f8-409058f2c58d',
                'ResponseCode'  => '1000',
                'ResponseMessage' => 'Success',
                'Transaction'   => [
                    ['transactionId' => 'LHG31AA5TX', 'amount' => 233.8],
                ],
            ]],
        ]);

        $service  = new PullTransaction($config, $http);
        $response = $service->query(
            startDate: new \DateTimeImmutable('2026-08-04 08:36:00'),
            endDate:   new \DateTimeImmutable('2026-08-16 10:10:00'),
            shortCode: '600000',
        );

        self::assertSame('1000', $response->getString('ResponseCode'));
        self::assertNotEmpty($response->get('Transaction'));
    }

    public function test_query_throws_when_end_date_before_start_date(): void
    {
        $this->expectException(ValidationException::class);

        $config  = $this->makeConfig();
        $http    = $this->makeHttpClientWithMockedToken($config, []);
        $service = new PullTransaction($config, $http);

        $service->query(
            startDate: new \DateTimeImmutable('2026-08-16'),
            endDate:   new \DateTimeImmutable('2026-08-04'),
            shortCode: '600000',
        );
    }

    public function test_query_throws_when_offset_negative(): void
    {
        $this->expectException(ValidationException::class);

        $config  = $this->makeConfig();
        $http    = $this->makeHttpClientWithMockedToken($config, []);
        $service = new PullTransaction($config, $http);

        $service->query(
            startDate: new \DateTimeImmutable('2026-08-04'),
            endDate:   new \DateTimeImmutable('2026-08-16'),
            shortCode: '600000',
            offset:    -1,
        );
    }

    public function test_query_falls_back_to_config_shortcode_when_omitted(): void
    {
        $config = $this->makeConfig(['shortcode' => '600321']);
        $http   = $this->makeHttpClientWithMockedToken($config, [
            ['status' => 200, 'body' => ['ResponseCode' => '1000', 'Transaction' => []]],
        ]);

        $service  = new PullTransaction($config, $http);
        $response = $service->query(
            startDate: new \DateTimeImmutable('2026-08-04'),
            endDate:   new \DateTimeImmutable('2026-08-16'),
        );

        self::assertSame('1000', $response->getString('ResponseCode'));
    }
}
