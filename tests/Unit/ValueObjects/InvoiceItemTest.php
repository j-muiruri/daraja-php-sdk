<?php

declare(strict_types=1);

namespace Daraja\Tests\Unit\ValueObjects;

use Daraja\Exceptions\ValidationException;
use Daraja\ValueObjects\Invoice;
use Daraja\ValueObjects\InvoiceItem;
use PHPUnit\Framework\TestCase;

final class InvoiceItemTest extends TestCase
{
    public function test_creates_valid_invoice_item(): void
    {
        $item = new InvoiceItem('Service Fee', 4310.34, 'Monthly subscription');

        self::assertSame('Service Fee', $item->itemName);
        self::assertSame(4310.34, $item->amount);
    }

    public function test_serialises_to_array_with_formatted_amount(): void
    {
        $item = new InvoiceItem('VAT', 689.66);
        $arr  = $item->toArray();

        self::assertSame('VAT', $arr['itemName']);
        self::assertSame('689.66', $arr['amount']);
    }

    public function test_throws_on_empty_item_name(): void
    {
        $this->expectException(ValidationException::class);

        new InvoiceItem('', 100.0);
    }

    public function test_throws_on_negative_amount(): void
    {
        $this->expectException(ValidationException::class);

        new InvoiceItem('Discount', -50.0);
    }

    public function test_zero_amount_is_valid(): void
    {
        $item = new InvoiceItem('Free Item', 0.0);
        self::assertSame(0.0, $item->amount);
    }
}
