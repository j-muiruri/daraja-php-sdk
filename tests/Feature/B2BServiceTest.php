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

final class B2BServiceTest extends DarajaTestCase
{
    public function test_pay_bill_returns_accepted_response(): void
    {
        $config = $this->makeConfig();
        $http   = $this->makeHttpClientWithMockedToken($config, [
            ['status' => 200, 'body' => $this->asyncAcceptedBody()],
        ]);

        $b2b      = new B2BService($config, $http);
        $response = $b2b->payBill(
            receiverShortcode: '000001',
            amount:            10000,
            accountReference:  'ACC-001',
        );

        self::assertTrue($response->isAccepted());
    }

    public function test_buy_goods_returns_accepted_response(): void
    {
        $config = $this->makeConfig();
        $http   = $this->makeHttpClientWithMockedToken($config, [
            ['status' => 200, 'body' => $this->asyncAcceptedBody()],
        ]);

        $b2b      = new B2BService($config, $http);
        $response = $b2b->buyGoods('654321', 2000, 'Supplier Payment');

        self::assertTrue($response->isAccepted());
    }

    public function test_account_reference_is_sent_unchanged_up_to_the_limit(): void
    {
        $config  = $this->makeConfig();
        $history = [];
        $stack   = \GuzzleHttp\HandlerStack::create(new \GuzzleHttp\Handler\MockHandler([
            new \GuzzleHttp\Psr7\Response(200, [], json_encode($this->asyncAcceptedBody(), JSON_THROW_ON_ERROR)),
        ]));
        $stack->push(\GuzzleHttp\Middleware::history($history));
        $tokenMgr = $this->createMock(\Daraja\Auth\AccessTokenManager::class);
        $tokenMgr->method('get')->willReturn(new \Daraja\Auth\AccessToken('t', 3600));
        $http = new \Daraja\Http\HttpClient($config, $tokenMgr, new \GuzzleHttp\Client(['handler' => $stack]));

        (new B2BService($config, $http))->payBill('247247', 500, '012345678901');

        $sent = json_decode((string) $history[0]['request']->getBody(), true, 512, JSON_THROW_ON_ERROR);
        self::assertSame('012345678901', $sent['AccountReference']);
    }

    public function test_over_long_account_reference_is_rejected_not_truncated(): void
    {
        $this->expectException(ValidationException::class);

        $config = $this->makeConfig();
        $http   = $this->makeHttpClientWithMockedToken($config, []);
        (new B2BService($config, $http))->payBill('247247', 500, '0123456789012');
    }

    public function test_b2b_throws_if_receiver_shortcode_empty(): void
    {
        $this->expectException(ValidationException::class);

        $config = $this->makeConfig();
        $http   = $this->makeHttpClientWithMockedToken($config, []);
        $b2b    = new B2BService($config, $http);

        $b2b->payBill(
            receiverShortcode: '',
            amount:            1000,
            accountReference:  'ACC',
        );
    }
}
