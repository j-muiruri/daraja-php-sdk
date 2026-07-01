<?php

declare(strict_types=1);

namespace Daraja\Tests\Unit\ValueObjects;

use Daraja\Exceptions\ValidationException;
use Daraja\ValueObjects\Invoice;
use Daraja\ValueObjects\InvoiceItem;
use PHPUnit\Framework\TestCase;

final class InvoiceTest extends TestCase
{
    private function validInvoice(): Invoice
    {
        return Invoice::make(
            externalReference: 'INV-2025-001',
            billedFullName:    'John Doe',
            billedPhone:       '0712345678',
            billedPeriod:      'June 2025',
            invoiceName:       'Monthly Service',
            dueDate:           '2025-07-15',
            accountReference:  'ACC-001',
            amount:            5000.00,
        );
    }

    public function test_creates_valid_invoice(): void
    {
        $invoice = $this->validInvoice();

        self::assertSame('INV-2025-001', $invoice->externalReference);
        self::assertSame(5000.00, $invoice->amount);
    }

    public function test_serialises_phone_to_e164_format(): void
    {
        $arr = $this->validInvoice()->toArray();

        self::assertSame('254712345678', $arr['billedPhoneNumber']);
    }

    public function test_serialises_amount_with_two_decimal_places(): void
    {
        $arr = $this->validInvoice()->toArray();

        self::assertSame('5000.00', $arr['amount']);
    }

    public function test_add_item_is_immutable(): void
    {
        $invoice = $this->validInvoice();
        $with    = $invoice->addItem(new InvoiceItem('VAT', 500.0));

        self::assertCount(0, $invoice->toArray()['invoiceItems']);
        self::assertCount(1, $with->toArray()['invoiceItems']);
    }

    public function test_multiple_items_accumulate(): void
    {
        $invoice = $this->validInvoice()
            ->addItem(new InvoiceItem('Base', 4310.34))
            ->addItem(new InvoiceItem('VAT', 689.66));

        self::assertCount(2, $invoice->toArray()['invoiceItems']);
    }

    public function test_throws_on_invalid_due_date_format(): void
    {
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessageMatches('/YYYY-MM-DD format/');

        Invoice::make(
            externalReference: 'INV-001',
            billedFullName:    'John',
            billedPhone:       '0712345678',
            billedPeriod:      'June 2025',
            invoiceName:       'Test',
            dueDate:           '15-07-2025', // wrong format
            accountReference:  'ACC',
            amount:            100.0,
        );
    }

    public function test_throws_on_zero_amount(): void
    {
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessageMatches('/amount/');

        Invoice::make(
            externalReference: 'INV-001',
            billedFullName:    'John',
            billedPhone:       '0712345678',
            billedPeriod:      'June 2025',
            invoiceName:       'Test',
            dueDate:           '2025-07-15',
            accountReference:  'ACC',
            amount:            0.0,
        );
    }

    public function test_throws_on_empty_external_reference(): void
    {
        $this->expectException(ValidationException::class);

        Invoice::make(
            externalReference: '',
            billedFullName:    'John',
            billedPhone:       '0712345678',
            billedPeriod:      'June 2025',
            invoiceName:       'Test',
            dueDate:           '2025-07-15',
            accountReference:  'ACC',
            amount:            100.0,
        );
    }
}
