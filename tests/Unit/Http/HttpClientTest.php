<?php

declare(strict_types=1);

namespace Daraja\Tests\Unit\Http;

use Daraja\Auth\AccessToken;
use Daraja\Auth\AccessTokenManager;
use Daraja\Exceptions\ApiException;
use Daraja\Http\HttpClient;
use Daraja\Tests\DarajaTestCase;
use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Request;

final class HttpClientTest extends DarajaTestCase
{
    public function test_server_error_response_preserves_status_and_api_error_code(): void
    {
        $config = $this->makeConfig();
        $http   = $this->makeHttpClientWithMockedToken($config, [
            [
                'status' => 500,
                'body'   => [
                    'errorCode'    => 'SERVER_ERROR',
                    'errorMessage' => 'Daraja is unavailable',
                ],
            ],
        ]);

        try {
            $http->post('/mpesa/test/v1/request', []);
            self::fail('Expected ApiException to be thrown.');
        } catch (ApiException $e) {
            self::assertSame(500, $e->statusCode());
            self::assertSame('SERVER_ERROR', $e->errorCode());
            self::assertStringContainsString('Daraja is unavailable', $e->getMessage());
        }
    }

    public function test_request_exception_without_a_response_is_a_network_error(): void
    {
        // Guzzle 8 has no getResponse() on a bare RequestException; this must not fatal.
        $config = $this->makeConfig();
        $guzzle = new GuzzleClient(['handler' => HandlerStack::create(new MockHandler([
            new RequestException('Transfer failed', new Request('POST', 'https://sandbox.safaricom.co.ke/x')),
        ]))]);
        $tokenMgr = $this->createMock(AccessTokenManager::class);
        $tokenMgr->method('get')->willReturn(new AccessToken('fake_test_token', 3600));
        $http = new HttpClient($config, $tokenMgr, $guzzle);

        try {
            $http->post('/mpesa/test/v1/request', ['a' => 1]);
            self::fail('Expected ApiException to be thrown.');
        } catch (ApiException $e) {
            self::assertSame(0, $e->statusCode());
            self::assertSame('NETWORK_ERROR', $e->errorCode());
        }
    }

    public function test_client_error_keeps_daraja_error_message(): void
    {
        $config = $this->makeConfig();
        $http   = $this->makeHttpClientWithMockedToken($config, [
            ['status' => 400, 'body' => ['errorCode' => '400.002.02', 'errorMessage' => 'Bad Request - Invalid PartyB']],
        ]);

        try {
            $http->post('/mpesa/b2b/v1/paymentrequest', ['a' => 1]);
            self::fail('Expected ApiException to be thrown.');
        } catch (ApiException $e) {
            self::assertSame(400, $e->statusCode());
            self::assertSame('400.002.02', $e->errorCode());
            self::assertStringContainsString('Invalid PartyB', $e->getMessage());
        }
    }
}
