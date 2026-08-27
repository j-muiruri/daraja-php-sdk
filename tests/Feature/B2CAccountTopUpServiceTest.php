<?php

declare(strict_types=1);

namespace Daraja\Tests\Feature;

use Daraja\Exceptions\ValidationException;
use Daraja\Services\B2CAccountTopUp;
use Daraja\Tests\DarajaTestCase;

final class B2CAccountTopUpServiceTest extends DarajaTestCase
{
    public function test_top_up_returns_accepted_response(): void
    {
        $config = $this->makeConfig();
        $http   = $this->makeHttpClientWithMockedToken($config, [
            ['status' => 200, 'body' => $this->asyncAcceptedBody()],
        ]);

        $service  = new B2CAccountTopUp($config, $http);
        $response = $service->topUp(
            b2cShortcode:     '600000',
            amount:           239,
            accountReference: '353353',
        );

        self::assertTrue($response->isAccepted());
        self::assertNotEmpty($response->conversationId());
    }

    public function test_top_up_throws_if_initiator_name_missing(): void
    {
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessageMatches('/initiatorName/');

        $config  = $this->makeConfig(['initiatorName' => '']);
        $http    = $this->makeHttpClientWithMockedToken($config, []);
        $service = new B2CAccountTopUp($config, $http);

        $service->topUp('600000', 239, '353353');
    }

    public function test_top_up_throws_if_security_credential_missing(): void
    {
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessageMatches('/securityCredential/');

        $config  = $this->makeConfig(['securityCredential' => '']);
        $http    = $this->makeHttpClientWithMockedToken($config, []);
        $service = new B2CAccountTopUp($config, $http);

        $service->topUp('600000', 239, '353353');
    }

    public function test_top_up_throws_if_b2c_shortcode_missing(): void
    {
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessageMatches('/b2c_shortcode|B2C shortcode/');

        $config  = $this->makeConfig();
        $http    = $this->makeHttpClientWithMockedToken($config, []);
        $service = new B2CAccountTopUp($config, $http);

        $service->topUp('', 239, '353353');
    }

    public function test_top_up_throws_if_amount_below_minimum(): void
    {
        $this->expectException(ValidationException::class);

        $config  = $this->makeConfig();
        $http    = $this->makeHttpClientWithMockedToken($config, []);
        $service = new B2CAccountTopUp($config, $http);

        $service->topUp('600000', 0, '353353');
    }

    public function test_top_up_throws_if_account_reference_missing(): void
    {
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessageMatches('/account_reference|reference/');

        $config  = $this->makeConfig();
        $http    = $this->makeHttpClientWithMockedToken($config, []);
        $service = new B2CAccountTopUp($config, $http);

        $service->topUp('600000', 239, '');
    }

    public function test_top_up_accepts_optional_requester_msisdn(): void
    {
        $config = $this->makeConfig();
        $http   = $this->makeHttpClientWithMockedToken($config, [
            ['status' => 200, 'body' => $this->asyncAcceptedBody()],
        ]);

        $service  = new B2CAccountTopUp($config, $http);
        $response = $service->topUp(
            b2cShortcode:     '600000',
            amount:           239,
            accountReference: '353353',
            requester:        '254708374149',
        );

        self::assertTrue($response->isAccepted());
    }
}
