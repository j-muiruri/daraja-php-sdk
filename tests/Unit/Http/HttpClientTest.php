<?php

declare(strict_types=1);

namespace Daraja\Tests\Unit\Http;

use Daraja\Exceptions\ApiException;
use Daraja\Tests\DarajaTestCase;

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
}
