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

final class TransactionStatusTest extends DarajaTestCase
{
    public function test_query_returns_accepted_response(): void
    {
        $config = $this->makeConfig();
        $http   = $this->makeHttpClientWithMockedToken($config, [
            ['status' => 200, 'body' => $this->asyncAcceptedBody()],
        ]);

        $svc      = new TransactionStatus($config, $http);
        $response = $svc->query('QHT3XXXXXXXXXXX');

        self::assertTrue($response->isAccepted());
        self::assertNotEmpty($response->originatorConversationId());
    }

    public function test_throws_if_transaction_id_empty(): void
    {
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessageMatches('/Transaction ID/');

        $config = $this->makeConfig();
        $http   = $this->makeHttpClientWithMockedToken($config, []);
        $svc    = new TransactionStatus($config, $http);

        $svc->query('');
    }
}
