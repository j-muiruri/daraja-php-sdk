<?php

declare(strict_types=1);

namespace Daraja\Tests\Unit\Webhooks;

use Daraja\Exceptions\ValidationException;
use Daraja\Webhooks\CallbackProcessor;
use Daraja\Webhooks\Payloads\AccountBalanceResult;
use Daraja\Webhooks\Payloads\B2CResult;
use Daraja\Webhooks\Payloads\C2BConfirmation;
use Daraja\Webhooks\Payloads\C2BValidation;
use Daraja\Webhooks\Payloads\ReversalResult;
use Daraja\Webhooks\Payloads\STKCallback;
use Daraja\Webhooks\Payloads\TransactionStatusResult;
use PHPUnit\Framework\TestCase;

// ============================================================
// STKCallback

final class AccountBalanceResultTest extends TestCase
{
    private function payload(): array
    {
        return [
            'Result' => [
                'ResultCode'              => 0,
                'ResultDesc'              => 'The service request is processed successfully.',
                'OriginatorConversationID' => '16470-170099139-1',
                'ConversationID'          => 'AG_20191219_00006c6fddb2a71e5699',
                'TransactionID'           => 'QHS9XXXXXXXXXXX',
                'ResultParameters'        => [
                    'ResultParameter' => [
                        [
                            'Key'   => 'AccountBalance',
                            'Value' => 'Working Account|KES|46713.00|46713.00|0.00|0.00&Float Account|KES|0.00|0.00|0.00|0.00&Utility Account|KES|49217.00|49217.00|0.00|0.00',
                        ],
                    ],
                ],
            ],
        ];
    }

    public function test_parses_balance_result(): void
    {
        $cb = AccountBalanceResult::fromArray($this->payload());

        self::assertTrue($cb->isSuccessful());
        self::assertCount(3, $cb->balances);
    }

    public function test_working_account_balance(): void
    {
        $cb = AccountBalanceResult::fromArray($this->payload());

        self::assertSame(46713.0, $cb->workingAccountBalance());
    }

    public function test_utility_account_balance(): void
    {
        $cb = AccountBalanceResult::fromArray($this->payload());

        self::assertSame(49217.0, $cb->utilityAccountBalance());
    }

    public function test_find_balance_by_name(): void
    {
        $cb   = AccountBalanceResult::fromArray($this->payload());
        $line = $cb->findBalance('Float');

        self::assertNotNull($line);
        self::assertSame('Float Account', $line->accountName);
        self::assertSame('KES', $line->currency);
        self::assertSame(0.0, $line->availableBalance);
    }

    public function test_find_balance_returns_null_for_unknown_account(): void
    {
        $cb = AccountBalanceResult::fromArray($this->payload());

        self::assertNull($cb->findBalance('NonExistentAccount'));
    }
}
