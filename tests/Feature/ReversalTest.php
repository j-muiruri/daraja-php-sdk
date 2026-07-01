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

final class ReversalTest extends DarajaTestCase
{
    public function test_reverse_returns_accepted_response(): void
    {
        $config = $this->makeConfig();
        $http   = $this->makeHttpClientWithMockedToken($config, [
            ['status' => 200, 'body' => $this->asyncAcceptedBody()],
        ]);

        $svc      = new Reversal($config, $http);
        $response = $svc->reverse(
            transactionId: 'QHT3XXXXXXXXXXX',
            amount:        100,
            remarks:       'Customer request refund',
        );

        self::assertTrue($response->isAccepted());
    }

    public function test_throws_if_transaction_id_is_empty(): void
    {
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessageMatches('/Transaction ID/');

        $config = $this->makeConfig();
        $http   = $this->makeHttpClientWithMockedToken($config, []);
        $svc    = new Reversal($config, $http);

        $svc->reverse('', 100);
    }

    public function test_throws_if_amount_is_negative(): void
    {
        $this->expectException(ValidationException::class);

        $config = $this->makeConfig();
        $http   = $this->makeHttpClientWithMockedToken($config, []);
        $svc    = new Reversal($config, $http);

        $svc->reverse('QHT3XXXXXXXXXXX', -50);
    }
}
