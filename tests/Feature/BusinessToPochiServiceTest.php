<?php

declare(strict_types=1);

namespace Daraja\Tests\Feature;

use Daraja\Exceptions\ValidationException;
use Daraja\Services\BusinessToPochi;
use Daraja\Tests\DarajaTestCase;

final class BusinessToPochiServiceTest extends DarajaTestCase
{
    public function test_pay_returns_accepted_response(): void
    {
        $config = $this->makeConfig();
        $http   = $this->makeHttpClientWithMockedToken($config, [
            ['status' => 200, 'body' => $this->asyncAcceptedBody()],
        ]);

        $service  = new BusinessToPochi($config, $http);
        $response = $service->pay(
            phone:  '0705912645',
            amount: 10,
        );

        self::assertTrue($response->isAccepted());
    }

    public function test_pay_throws_if_initiator_name_missing(): void
    {
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessageMatches('/initiatorName/');

        $config  = $this->makeConfig(['initiatorName' => '']);
        $http    = $this->makeHttpClientWithMockedToken($config, []);
        $service = new BusinessToPochi($config, $http);

        $service->pay('0705912645', 10);
    }

    public function test_pay_throws_if_amount_below_minimum(): void
    {
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessageMatches('/at least 10/');

        $config  = $this->makeConfig();
        $http    = $this->makeHttpClientWithMockedToken($config, []);
        $service = new BusinessToPochi($config, $http);

        $service->pay('0705912645', 5);
    }

    public function test_pay_throws_if_amount_above_maximum(): void
    {
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessageMatches('/250,000/');

        $config  = $this->makeConfig();
        $http    = $this->makeHttpClientWithMockedToken($config, []);
        $service = new BusinessToPochi($config, $http);

        $service->pay('0705912645', 250001);
    }

    public function test_pay_throws_if_result_url_missing(): void
    {
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessageMatches('/result/');

        $config  = $this->makeConfig(['resultUrl' => '']);
        $http    = $this->makeHttpClientWithMockedToken($config, []);
        $service = new BusinessToPochi($config, $http);

        $service->pay('0705912645', 10);
    }

    public function test_pay_accepts_a_phone_number_value_object(): void
    {
        $config = $this->makeConfig();
        $http   = $this->makeHttpClientWithMockedToken($config, [
            ['status' => 200, 'body' => $this->asyncAcceptedBody()],
        ]);

        $service  = new BusinessToPochi($config, $http);
        $response = $service->pay(
            phone:  \Daraja\ValueObjects\PhoneNumber::from('0705912645'),
            amount: 10,
        );

        self::assertTrue($response->isAccepted());
    }
}
