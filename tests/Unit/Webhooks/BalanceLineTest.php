<?php

declare(strict_types=1);

namespace Daraja\Tests\Unit\Webhooks;

use Daraja\Webhooks\Results\BalanceLine;
use PHPUnit\Framework\TestCase;

final class BalanceLineTest extends TestCase
{
    public function test_parses_single_balance_line(): void
    {
        $line = BalanceLine::fromString('Working Account|KES|46713.00|46713.00|0.00|0.00');

        self::assertSame('Working Account', $line->accountName);
        self::assertSame('KES', $line->currency);
        self::assertSame(46713.0, $line->currentBalance);
        self::assertSame(46713.0, $line->availableBalance);
        self::assertSame(0.0, $line->reservedBalance);
        self::assertSame(0.0, $line->unclearedBalance);
    }

    public function test_parses_all_lines_from_ampersand_string(): void
    {
        $raw = 'Working Account|KES|46713.00|46713.00|0.00|0.00'
             . '&Float Account|KES|0.00|0.00|0.00|0.00'
             . '&Utility Account|KES|49217.00|49217.00|0.00|0.00';

        $lines = BalanceLine::parseAll($raw);

        self::assertCount(3, $lines);
        self::assertSame('Working Account', $lines[0]->accountName);
        self::assertSame('Float Account', $lines[1]->accountName);
        self::assertSame('Utility Account', $lines[2]->accountName);
    }

    public function test_throws_on_malformed_line(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/6 pipe-delimited segments/');

        BalanceLine::fromString('only|four|segments|here');
    }

    public function test_trims_whitespace_from_account_name(): void
    {
        $line = BalanceLine::fromString('  Working Account  |KES|100.00|100.00|0.00|0.00');

        self::assertSame('Working Account', $line->accountName);
    }
}
