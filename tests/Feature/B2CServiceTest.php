<?php

declare(strict_types=1);

namespace Daraja\Tests\Feature;

use Daraja\Enums\Environment;
use Daraja\Enums\QRCodeType;
use Daraja\Exceptions\ValidationException;
use Daraja\Services\AccountBalance;
use Daraja\Services\B2BService;
use Daraja\Services\B2CService;
use Daraja\Services\C2BService;
use Daraja\Services\DynamicQR;
use Daraja\Services\Reversal;
use Daraja\Services\TransactionStatus;
use Daraja\Tests\DarajaTestCase;

// ============================================================
// C2B

final class B2CServiceTest extends DarajaTestCase
{
    public function test_send_salary_returns_accepted_response(): void
    {
        $config = $this->makeConfig();
        $http   = $this->makeHttpClientWithMockedToken($config, [
            ['status' => 200, 'body' => $this->asyncAcceptedBody()],
        ]);

        $b2c      = new B2CService($config, $http);
        $response = $b2c->sendSalary(
            phone:   '0712345678',
            amount:  5000,
            remarks: 'March Salary',
        );

        self::assertTrue($response->isAccepted());
        self::assertNotEmpty($response->conversationId());
    }

    public function test_send_business_payment_returns_accepted_response(): void
    {
        $config = $this->makeConfig();
        $http   = $this->makeHttpClientWithMockedToken($config, [
            ['status' => 200, 'body' => $this->asyncAcceptedBody()],
        ]);

        $b2c      = new B2CService($config, $http);
        $response = $b2c->sendBusinessPayment('0722123456', 1000, 'Refund');

        self::assertTrue($response->isAccepted());
    }

    public function test_b2c_throws_if_initiator_name_missing(): void
    {
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessageMatches('/initiatorName/');

        $config = $this->makeConfig(['initiatorName' => '']);
        $http   = $this->makeHttpClientWithMockedToken($config, []);
        $b2c    = new B2CService($config, $http);

        $b2c->sendSalary('0712345678', 5000);
    }

    public function test_b2c_throws_if_security_credential_missing(): void
    {
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessageMatches('/securityCredential/');

        $config = $this->makeConfig(['securityCredential' => '']);
        $http   = $this->makeHttpClientWithMockedToken($config, []);
        $b2c    = new B2CService($config, $http);

        $b2c->sendSalary('0712345678', 5000);
    }

    public function test_b2c_throws_if_amount_is_zero(): void
    {
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessageMatches('/Amount/');

        $config = $this->makeConfig();
        $http   = $this->makeHttpClientWithMockedToken($config, []);
        $b2c    = new B2CService($config, $http);

        $b2c->sendSalary('0712345678', 0);
    }
}
