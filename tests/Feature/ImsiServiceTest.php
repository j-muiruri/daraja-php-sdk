<?php

declare(strict_types=1);

namespace Daraja\Tests\Feature;

use Daraja\Exceptions\ValidationException;
use Daraja\Services\Imsi;
use Daraja\Tests\DarajaTestCase;
use Daraja\ValueObjects\PhoneNumber;

final class ImsiServiceTest extends DarajaTestCase
{
    public function test_check_returns_imsi_and_registration_details(): void
    {
        $config = $this->makeConfig();
        $http   = $this->makeHttpClientWithMockedToken($config, [
            ['status' => 200, 'body' => [
                'requestRefID'           => '971f-4359-b611-6845a1be45ef280786',
                'responseCode'           => '200',
                'responseDesc'           => 'Success',
                'imsi'                   => '9233817055099406',
                'lastSwapDate'           => '01-05-2022',
                'msisdnRegistrationDate' => '01-03-2022',
                'customerNumber'         => '254722000000',
            ]],
        ]);

        $service  = new Imsi($http);
        $response = $service->check('254722000000');

        self::assertSame('200', $response->getString('responseCode'));
        self::assertSame('9233817055099406', $response->getString('imsi'));
        self::assertSame('01-03-2022', $response->getString('msisdnRegistrationDate'));
    }

    public function test_check_accepts_a_phone_number_value_object(): void
    {
        $config = $this->makeConfig();
        $http   = $this->makeHttpClientWithMockedToken($config, [
            ['status' => 200, 'body' => ['responseCode' => '200', 'imsi' => '9233817055099406']],
        ]);

        $service  = new Imsi($http);
        $response = $service->check(PhoneNumber::from('0722000000'));

        self::assertSame('9233817055099406', $response->getString('imsi'));
    }

    public function test_check_throws_for_invalid_number(): void
    {
        $this->expectException(ValidationException::class);

        $config  = $this->makeConfig();
        $http    = $this->makeHttpClientWithMockedToken($config, []);
        $service = new Imsi($http);

        $service->check('not-a-number');
    }
}
