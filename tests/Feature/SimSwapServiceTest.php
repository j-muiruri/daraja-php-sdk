<?php

declare(strict_types=1);

namespace Daraja\Tests\Feature;

use Daraja\Exceptions\ValidationException;
use Daraja\Services\SimSwap;
use Daraja\Tests\DarajaTestCase;
use Daraja\ValueObjects\PhoneNumber;

final class SimSwapServiceTest extends DarajaTestCase
{
    public function test_check_last_swap_date_returns_default_date_when_never_swapped(): void
    {
        $config = $this->makeConfig();
        $http   = $this->makeHttpClientWithMockedToken($config, [
            ['status' => 200, 'body' => [
                'requestRefID' => '4277-415525-1',
                'responseCode' => '200',
                'responseDesc' => 'Success',
                'lastSwapDate' => '01-01-1900 00:00',
            ]],
        ]);

        $service  = new SimSwap($http);
        $response = $service->checkLastSwapDate('254722000000');

        self::assertSame('200', $response->getString('responseCode'));
        self::assertSame('01-01-1900 00:00', $response->getString('lastSwapDate'));
    }

    public function test_check_last_swap_date_accepts_local_format_number(): void
    {
        $config = $this->makeConfig();
        $http   = $this->makeHttpClientWithMockedToken($config, [
            ['status' => 200, 'body' => [
                'requestRefID' => '4277-415526-1',
                'responseCode' => '200',
                'responseDesc' => 'Success',
                'lastSwapDate' => '15-06-2025 09:12',
            ]],
        ]);

        $service  = new SimSwap($http);
        $response = $service->checkLastSwapDate('0722000000');

        self::assertSame('15-06-2025 09:12', $response->getString('lastSwapDate'));
    }

    public function test_check_last_swap_date_accepts_a_phone_number_value_object(): void
    {
        $config = $this->makeConfig();
        $http   = $this->makeHttpClientWithMockedToken($config, [
            ['status' => 200, 'body' => ['responseCode' => '200', 'lastSwapDate' => '01-01-1900 00:00']],
        ]);

        $service  = new SimSwap($http);
        $response = $service->checkLastSwapDate(PhoneNumber::from('0722000000'));

        self::assertSame('200', $response->getString('responseCode'));
    }

    public function test_check_last_swap_date_throws_for_invalid_number(): void
    {
        $this->expectException(ValidationException::class);

        $config  = $this->makeConfig();
        $http    = $this->makeHttpClientWithMockedToken($config, []);
        $service = new SimSwap($http);

        $service->checkLastSwapDate('12345');
    }
}
