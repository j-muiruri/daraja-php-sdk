<?php

declare(strict_types=1);

namespace Daraja\Tests;

use Daraja\Auth\AccessToken;
use Daraja\Auth\AccessTokenManager;
use Daraja\Config;
use Daraja\Enums\Environment;
use Daraja\Http\HttpClient;
use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response as GuzzleResponse;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

abstract class DarajaTestCase extends TestCase
{
    /**
     * Build a sandbox Config with safe test values.
     */
    protected function makeConfig(array $overrides = []): Config
    {
        return Config::make(
            consumerKey:        $overrides['consumerKey']        ?? 'test_consumer_key',
            consumerSecret:     $overrides['consumerSecret']     ?? 'test_consumer_secret',
            shortcode:          $overrides['shortcode']          ?? '174379',
            passkey:            $overrides['passkey']            ?? 'bfb279f9aa9bdbcf158e97dd71a467cd2e0c893059b10f78e6b72ada1ed2c919',
            environment:        $overrides['environment']        ?? Environment::Sandbox,
            securityCredential: $overrides['securityCredential'] ?? 'test_security_credential',
            initiatorName:      $overrides['initiatorName']      ?? 'testapi',
            callbackUrl:        $overrides['callbackUrl']        ?? 'https://example.com/mpesa/callback',
            resultUrl:          $overrides['resultUrl']          ?? 'https://example.com/mpesa/result',
            timeoutUrl:         $overrides['timeoutUrl']         ?? 'https://example.com/mpesa/timeout',
        );
    }

    /**
     * Build a Guzzle MockHandler pre-loaded with JSON responses.
     *
     * @param  array<array<string,mixed>> $responses  Each item: ['status' => 200, 'body' => [...]]
     */
    protected function mockGuzzle(array $responses): GuzzleClient
    {
        $queue = array_map(
            fn(array $r) => new GuzzleResponse(
                $r['status'] ?? 200,
                ['Content-Type' => 'application/json'],
                json_encode($r['body'] ?? [], JSON_THROW_ON_ERROR),
            ),
            $responses
        );

        $handler = new MockHandler($queue);
        $stack   = HandlerStack::create($handler);

        return new GuzzleClient(['handler' => $stack]);
    }

    /**
     * Build an HttpClient backed by a mock Guzzle with pre-set token.
     *
     * @param  array<array<string,mixed>> $apiResponses  Responses after the auth call
     */
    protected function makeHttpClient(Config $config, array $apiResponses): HttpClient
    {
        $tokenResponse = [
            'status' => 200,
            'body'   => ['access_token' => 'fake_test_token', 'expires_in' => 3600],
        ];

        $guzzle      = $this->mockGuzzle([$tokenResponse, ...$apiResponses]);
        $tokenMgr    = new AccessTokenManager($config, $guzzle);

        return new HttpClient($config, $tokenMgr, $guzzle);
    }

    /**
     * Build an HttpClient with a pre-seeded (no-fetch) token manager mock.
     *
     * @param  array<array<string,mixed>> $apiResponses
     */
    protected function makeHttpClientWithMockedToken(Config $config, array $apiResponses): HttpClient
    {
        $guzzle = $this->mockGuzzle($apiResponses);

        /** @var AccessTokenManager&MockObject $tokenMgr */
        $tokenMgr = $this->createMock(AccessTokenManager::class);
        $tokenMgr->method('get')->willReturn(new AccessToken('fake_test_token', 3600));

        return new HttpClient($config, $tokenMgr, $guzzle);
    }

    /**
     * Standard STK Push accepted response body.
     *
     * @return array<string, mixed>
     */
    protected function stkPushAcceptedBody(): array
    {
        return [
            'MerchantRequestID'   => '29115-34620561-1',
            'CheckoutRequestID'   => 'ws_CO_191220191020363925',
            'ResponseCode'        => '0',
            'ResponseDescription' => 'Success. Request accepted for processing',
            'CustomerMessage'     => 'Success. Request accepted for processing',
        ];
    }

    /**
     * Standard async-accepted response (B2C, B2B, Reversal, Balance, TxStatus).
     *
     * @return array<string, mixed>
     */
    protected function asyncAcceptedBody(): array
    {
        return [
            'ConversationID'         => 'AG_20191219_00005797af5d7d75f652',
            'OriginatorConversationID' => '25001-34030562-1',
            'ResponseCode'           => '0',
            'ResponseDescription'    => 'Accept the service request successfully.',
        ];
    }
}
