<?php

declare(strict_types=1);

namespace Daraja\Tests\Feature;

use Daraja\Exceptions\ValidationException;
use Daraja\Services\BillManager;
use Daraja\Services\TaxRemittance;
use Daraja\Tests\DarajaTestCase;
use Daraja\ValueObjects\Invoice;
use Daraja\ValueObjects\InvoiceItem;
use Daraja\Webhooks\CallbackProcessor;
use Daraja\Webhooks\Payloads\B2BResult;
use Daraja\Webhooks\Payloads\BillManagerReconciliation;

// ============================================================
// TaxRemittance

final class BillManagerTest extends DarajaTestCase
{
    private function optInBody(): array
    {
        return [
            'ResponseCode'        => '0',
            'ResponseDescription' => 'Opt-in was successful',
        ];
    }

    private function invoiceBody(): array
    {
        return [
            'ResponseCode'        => '0',
            'ResponseDescription' => 'Invoice sent successfully',
        ];
    }

    public function test_opt_in_returns_successful_response(): void
    {
        $config = $this->makeConfig();
        $http   = $this->makeHttpClientWithMockedToken($config, [
            ['status' => 200, 'body' => $this->optInBody()],
        ]);

        $svc      = new BillManager($config, $http);
        $response = $svc->optIn(
            email:           'billing@example.co.ke',
            officialContact: '0712345678',
            callbackUrl:     'https://example.com/mpesa/bill/reconciliation',
        );

        self::assertTrue($response->isSuccessful());
    }

    public function test_opt_in_throws_on_invalid_email(): void
    {
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessageMatches('/email/');

        $config = $this->makeConfig();
        $http   = $this->makeHttpClientWithMockedToken($config, []);
        $svc    = new BillManager($config, $http);

        $svc->optIn('not-an-email', '0712345678', 'https://example.com/cb');
    }

    public function test_send_invoice_returns_accepted_response(): void
    {
        $config = $this->makeConfig();
        $http   = $this->makeHttpClientWithMockedToken($config, [
            ['status' => 200, 'body' => $this->invoiceBody()],
        ]);

        $invoice = Invoice::make(
            externalReference: 'INV-2025-001',
            billedFullName:    'John Doe',
            billedPhone:       '0712345678',
            billedPeriod:      'June 2025',
            invoiceName:       'Monthly Service',
            dueDate:           '2025-07-15',
            accountReference:  'ACC-001',
            amount:            5000.00,
        );

        $svc      = new BillManager($config, $http);
        $response = $svc->sendInvoice($invoice);

        self::assertTrue($response->isSuccessful());
    }

    public function test_invoice_with_line_items_serialises_correctly(): void
    {
        $invoice = Invoice::make(
            externalReference: 'INV-001',
            billedFullName:    'Jane Wanjiru',
            billedPhone:       '0722123456',
            billedPeriod:      'July 2025',
            invoiceName:       'Electricity',
            dueDate:           '2025-08-01',
            accountReference:  'METER-001',
            amount:            3000.00,
        )->addItem(new InvoiceItem('Units', 2586.21))
         ->addItem(new InvoiceItem('VAT 16%', 413.79));

        $arr = $invoice->toArray();

        self::assertCount(2, $arr['invoiceItems']);
        self::assertSame('Units', $arr['invoiceItems'][0]['itemName']);
        self::assertSame('2586.21', $arr['invoiceItems'][0]['amount']);
        self::assertSame('254722123456', $arr['billedPhoneNumber']);
    }

    public function test_send_bulk_throws_on_empty_array(): void
    {
        $this->expectException(ValidationException::class);

        $config = $this->makeConfig();
        $http   = $this->makeHttpClientWithMockedToken($config, []);
        $svc    = new BillManager($config, $http);

        $svc->sendBulk([]);
    }

    public function test_send_bulk_throws_on_over_1000_invoices(): void
    {
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessageMatches('/1,000/');

        $config = $this->makeConfig();
        $http   = $this->makeHttpClientWithMockedToken($config, []);
        $svc    = new BillManager($config, $http);

        $invoices = array_fill(0, 1001, Invoice::make(
            externalReference: 'INV-X',
            billedFullName:    'Test',
            billedPhone:       '0712345678',
            billedPeriod:      'July 2025',
            invoiceName:       'Test',
            dueDate:           '2025-08-01',
            accountReference:  'ACC',
            amount:            100.0,
        ));

        $svc->sendBulk($invoices);
    }

    public function test_cancel_invoice_throws_on_empty_reference(): void
    {
        $this->expectException(ValidationException::class);

        $config = $this->makeConfig();
        $http   = $this->makeHttpClientWithMockedToken($config, []);
        $svc    = new BillManager($config, $http);

        $svc->cancelInvoice('');
    }

    public function test_cancel_bulk_returns_accepted_response(): void
    {
        $config = $this->makeConfig();
        $http   = $this->makeHttpClientWithMockedToken($config, [
            ['status' => 200, 'body' => ['ResponseCode' => '0', 'ResponseDescription' => 'Cancelled']],
        ]);

        $svc      = new BillManager($config, $http);
        $response = $svc->cancelBulk(['INV-001', 'INV-002', 'INV-003']);

        self::assertTrue($response->isSuccessful());
    }
}
