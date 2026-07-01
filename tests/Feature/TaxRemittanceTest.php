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

final class TaxRemittanceTest extends DarajaTestCase
{
    public function test_remit_returns_accepted_response(): void
    {
        $config = $this->makeConfig();
        $http   = $this->makeHttpClientWithMockedToken($config, [
            ['status' => 200, 'body' => $this->asyncAcceptedBody()],
        ]);

        $svc      = new TaxRemittance($config, $http);
        $response = $svc->remit(
            amount:           125000,
            accountReference: 'PRN20250630001',
            remarks:          'VAT June 2025',
        );

        self::assertTrue($response->isAccepted());
        self::assertNotEmpty($response->conversationId());
    }

    public function test_throws_if_amount_is_zero(): void
    {
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessageMatches('/amount/');

        $config = $this->makeConfig();
        $http   = $this->makeHttpClientWithMockedToken($config, []);
        $svc    = new TaxRemittance($config, $http);

        $svc->remit(0, 'PRN001');
    }

    public function test_throws_if_prn_is_empty(): void
    {
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessageMatches('/PRN/');

        $config = $this->makeConfig();
        $http   = $this->makeHttpClientWithMockedToken($config, []);
        $svc    = new TaxRemittance($config, $http);

        $svc->remit(10000, '');
    }

    public function test_throws_if_initiator_name_missing(): void
    {
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessageMatches('/initiatorName/');

        $config = $this->makeConfig(['initiatorName' => '']);
        $http   = $this->makeHttpClientWithMockedToken($config, []);
        $svc    = new TaxRemittance($config, $http);

        $svc->remit(10000, 'PRN001');
    }
}
