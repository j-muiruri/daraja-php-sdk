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

final class C2BServiceTest extends DarajaTestCase
{
    public function test_register_urls_returns_accepted_response(): void
    {
        $config = $this->makeConfig();
        $http   = $this->makeHttpClientWithMockedToken($config, [
            [
                'status' => 200,
                'body'   => [
                    'OriginatorCoversationID' => '123',
                    'ResponseCode'            => '0',
                    'ResponseDescription'     => 'Success',
                ],
            ],
        ]);

        $c2b      = new C2BService($config, $http);
        $response = $c2b->registerUrls(
            confirmationUrl: 'https://example.com/mpesa/confirm',
            validationUrl:   'https://example.com/mpesa/validate',
        );

        self::assertTrue($response->isSuccessful());
        self::assertSame('0', $response->getString('ResponseCode'));
    }

    public function test_register_urls_throws_on_invalid_confirmation_url(): void
    {
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessageMatches('/confirmation URL/');

        $config = $this->makeConfig();
        $http   = $this->makeHttpClientWithMockedToken($config, []);
        $c2b    = new C2BService($config, $http);

        $c2b->registerUrls(confirmationUrl: 'not-a-url');
    }

    public function test_register_urls_throws_on_invalid_response_type(): void
    {
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessageMatches('/ResponseType/');

        $config = $this->makeConfig();
        $http   = $this->makeHttpClientWithMockedToken($config, []);
        $c2b    = new C2BService($config, $http);

        $c2b->registerUrls(
            confirmationUrl: 'https://example.com/confirm',
            responseType:    'InvalidType',
        );
    }

    public function test_simulate_returns_accepted_response_in_sandbox(): void
    {
        $config = $this->makeConfig(['environment' => Environment::Sandbox]);
        $http   = $this->makeHttpClientWithMockedToken($config, [
            [
                'status' => 200,
                'body'   => [
                    'ConversationID'          => 'AG_20230101_000012345',
                    'OriginatorConversationID' => '10571-7910404-1',
                    'ResponseCode'            => '0',
                    'ResponseDescription'     => 'Accept the service request successfully.',
                ],
            ],
        ]);

        $c2b      = new C2BService($config, $http);
        $response = $c2b->simulate(
            phone:         '0712345678',
            amount:        100,
            billRefNumber: 'INV-001',
        );

        self::assertTrue($response->isAccepted());
    }

    public function test_simulate_throws_in_production(): void
    {
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessageMatches('/environment/');

        $config = $this->makeConfig(['environment' => Environment::Production]);
        $http   = $this->makeHttpClientWithMockedToken($config, []);
        $c2b    = new C2BService($config, $http);

        $c2b->simulate('0712345678', 100);
    }

    public function test_simulate_throws_on_invalid_command_id(): void
    {
        $this->expectException(ValidationException::class);

        $config = $this->makeConfig();
        $http   = $this->makeHttpClientWithMockedToken($config, []);
        $c2b    = new C2BService($config, $http);

        $c2b->simulate('0712345678', 100, 'ref', 'InvalidCommand');
    }
}
