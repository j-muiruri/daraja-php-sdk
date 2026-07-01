<?php

declare(strict_types=1);

namespace Daraja\Tests\Feature;

use Daraja\DarajaClient;
use Daraja\Enums\Environment;
use Daraja\Services\AccountBalance;
use Daraja\Services\B2BService;
use Daraja\Services\B2CService;
use Daraja\Services\C2BService;
use Daraja\Services\DynamicQR;
use Daraja\Services\Reversal;
use Daraja\Services\STKPush;
use Daraja\Services\TransactionStatus;
use Daraja\Tests\DarajaTestCase;

final class DarajaClientTest extends DarajaTestCase
{
    public function test_make_creates_client_with_correct_config(): void
    {
        $client = DarajaClient::make(
            consumerKey:    'key',
            consumerSecret: 'secret',
            shortcode:      '174379',
            passkey:        'passkey',
            environment:    Environment::Sandbox,
        );

        self::assertTrue($client->config()->isSandbox());
        self::assertSame('174379', $client->config()->shortcode);
    }

    public function test_stk_accessor_returns_stk_push_instance(): void
    {
        $client = $this->buildClient();
        self::assertInstanceOf(STKPush::class, $client->stk());
    }

    public function test_stk_accessor_is_lazy_singleton(): void
    {
        $client = $this->buildClient();
        self::assertSame($client->stk(), $client->stk());
    }

    public function test_c2b_accessor_returns_c2b_service_instance(): void
    {
        $client = $this->buildClient();
        self::assertInstanceOf(C2BService::class, $client->c2b());
    }

    public function test_b2c_accessor_returns_b2c_service_instance(): void
    {
        $client = $this->buildClient();
        self::assertInstanceOf(B2CService::class, $client->b2c());
    }

    public function test_b2b_accessor_returns_b2b_service_instance(): void
    {
        $client = $this->buildClient();
        self::assertInstanceOf(B2BService::class, $client->b2b());
    }

    public function test_transaction_status_accessor(): void
    {
        $client = $this->buildClient();
        self::assertInstanceOf(TransactionStatus::class, $client->transactionStatus());
    }

    public function test_account_balance_accessor(): void
    {
        $client = $this->buildClient();
        self::assertInstanceOf(AccountBalance::class, $client->accountBalance());
    }

    public function test_reversal_accessor(): void
    {
        $client = $this->buildClient();
        self::assertInstanceOf(Reversal::class, $client->reversal());
    }

    public function test_qr_accessor(): void
    {
        $client = $this->buildClient();
        self::assertInstanceOf(DynamicQR::class, $client->qr());
    }

    public function test_from_config_creates_client(): void
    {
        $config = $this->makeConfig();
        $client = DarajaClient::fromConfig($config);

        self::assertSame($config->shortcode, $client->config()->shortcode);
    }

    private function buildClient(): DarajaClient
    {
        return DarajaClient::make(
            consumerKey:    'key',
            consumerSecret: 'secret',
            shortcode:      '174379',
            passkey:        'passkey',
        );
    }
}
