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

final class AccountBalanceTest extends DarajaTestCase
{
    public function test_query_returns_accepted_response(): void
    {
        $config = $this->makeConfig();
        $http   = $this->makeHttpClientWithMockedToken($config, [
            ['status' => 200, 'body' => $this->asyncAcceptedBody()],
        ]);

        $svc      = new AccountBalance($config, $http);
        $response = $svc->query();

        self::assertTrue($response->isAccepted());
    }

    public function test_throws_if_result_url_missing(): void
    {
        $this->expectException(ValidationException::class);

        $config = $this->makeConfig(['resultUrl' => '']);
        $http   = $this->makeHttpClientWithMockedToken($config, []);
        $svc    = new AccountBalance($config, $http);

        $svc->query();
    }
}
