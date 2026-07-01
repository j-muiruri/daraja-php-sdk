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
