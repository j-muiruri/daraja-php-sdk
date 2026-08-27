<?php

declare(strict_types=1);

namespace Daraja\Tests\Feature;

use Daraja\Auth\AccessToken;
use Daraja\Auth\AccessTokenManager;
use Daraja\Exceptions\ValidationException;
use Daraja\Http\HttpClient;
use Daraja\Services\B2BExpressCheckout;
use Daraja\Tests\DarajaTestCase;
use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response as GuzzleResponse;

final class B2BExpressCheckoutServiceTest extends DarajaTestCase
{
    public function test_push_returns_ussd_initiated_response(): void
    {
        $config = $this->makeConfig();
        $http   = $this->makeHttpClientWithMockedToken($config, [
            ['status' => 200, 'body' => ['code' => '0', 'status' => 'USSD Initiated Successfully']],
        ]);

        $service  = new B2BExpressCheckout($config, $http);
        $response = $service->push(
            primaryShortCode: '000001',
            receiverShortCode: '000002',
            amount: 100,
            paymentRef: 'INV-001',
            partnerName: 'Vendor Ltd',
            callbackUrl: 'https://example.com/callback',
        );

        self::assertTrue($response->isSuccessful());
        self::assertSame('USSD Initiated Successfully', $response->getString('status'));
    }

    public function test_push_generates_a_uuid_v4_request_ref_id_when_omitted(): void
    {
        $config = $this->makeConfig();

        $history = [];
        $handler = new MockHandler([
            new GuzzleResponse(200, ['Content-Type' => 'application/json'], json_encode(
                ['code' => '0', 'status' => 'USSD Initiated Successfully'],
                JSON_THROW_ON_ERROR,
            )),
        ]);
        $stack = HandlerStack::create($handler);
        $stack->push(Middleware::history($history));
        $guzzle = new GuzzleClient(['handler' => $stack]);

        /** @var AccessTokenManager&\PHPUnit\Framework\MockObject\MockObject $tokenMgr */
        $tokenMgr = $this->createMock(AccessTokenManager::class);
        $tokenMgr->method('get')->willReturn(new AccessToken('fake_test_token', 3600));

        $http    = new HttpClient($config, $tokenMgr, $guzzle);
        $service = new B2BExpressCheckout($config, $http);

        $service->push(
            primaryShortCode: '000001',
            receiverShortCode: '000002',
            amount: 50,
            paymentRef: 'INV-002',
            partnerName: 'Vendor Ltd',
            callbackUrl: 'https://example.com/callback',
        );

        $sentBody = json_decode((string) $history[0]['request']->getBody(), true, 512, JSON_THROW_ON_ERROR);

        self::assertMatchesRegularExpression(
            '/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/',
            $sentBody['RequestRefID'],
        );
    }

    public function test_push_throws_when_primary_short_code_missing(): void
    {
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessageMatches('/primaryShortCode/');

        $config  = $this->makeConfig();
        $http    = $this->makeHttpClientWithMockedToken($config, []);
        $service = new B2BExpressCheckout($config, $http);

        $service->push(
            primaryShortCode: '',
            receiverShortCode: '000002',
            amount: 100,
            paymentRef: 'INV-001',
            partnerName: 'Vendor Ltd',
            callbackUrl: 'https://example.com/callback',
        );
    }

    public function test_push_throws_when_amount_is_zero(): void
    {
        $this->expectException(ValidationException::class);

        $config  = $this->makeConfig();
        $http    = $this->makeHttpClientWithMockedToken($config, []);
        $service = new B2BExpressCheckout($config, $http);

        $service->push(
            primaryShortCode: '000001',
            receiverShortCode: '000002',
            amount: 0,
            paymentRef: 'INV-001',
            partnerName: 'Vendor Ltd',
            callbackUrl: 'https://example.com/callback',
        );
    }

    public function test_push_throws_when_callback_url_missing_and_no_config_default(): void
    {
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessageMatches('/callback/');

        $config  = $this->makeConfig(['callbackUrl' => '']);
        $http    = $this->makeHttpClientWithMockedToken($config, []);
        $service = new B2BExpressCheckout($config, $http);

        $service->push(
            primaryShortCode: '000001',
            receiverShortCode: '000002',
            amount: 100,
            paymentRef: 'INV-001',
            partnerName: 'Vendor Ltd',
        );
    }

    public function test_push_falls_back_to_config_callback_url(): void
    {
        $config = $this->makeConfig(['callbackUrl' => 'https://example.com/mpesa/callback']);
        $http   = $this->makeHttpClientWithMockedToken($config, [
            ['status' => 200, 'body' => ['code' => '0', 'status' => 'USSD Initiated Successfully']],
        ]);

        $service  = new B2BExpressCheckout($config, $http);
        $response = $service->push(
            primaryShortCode: '000001',
            receiverShortCode: '000002',
            amount: 100,
            paymentRef: 'INV-001',
            partnerName: 'Vendor Ltd',
        );

        self::assertTrue($response->isSuccessful());
    }
}
