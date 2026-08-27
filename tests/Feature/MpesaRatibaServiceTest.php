<?php

declare(strict_types=1);

namespace Daraja\Tests\Feature;

use Daraja\Enums\Frequency;
use Daraja\Exceptions\ValidationException;
use Daraja\Services\MpesaRatiba;
use Daraja\Tests\DarajaTestCase;

final class MpesaRatibaServiceTest extends DarajaTestCase
{
    private function ratibaAcceptedBody(): array
    {
        return [
            'ResponseHeader' => [
                'responseRefID' => '4dd9b5d9-d738-42ba-9326-2cc99e966000',
                'responseCode'  => '200',
                'responseDescription' => 'Request accepted for processing',
                'ResultDesc'    => 'The service request is processed successfully.',
            ],
            'ResponseBody' => [
                'responseDescription' => 'Request accepted for processing',
                'responseCode'        => '200',
            ],
        ];
    }

    public function test_create_for_pay_bill_is_accepted(): void
    {
        $config = $this->makeConfig();
        $http   = $this->makeHttpClientWithMockedToken($config, [
            ['status' => 200, 'body' => $this->ratibaAcceptedBody()],
        ]);

        $service  = new MpesaRatiba($config, $http);
        $response = $service->createForPayBill(
            standingOrderName: 'Phone Lipa Mdogo Mdogo',
            startDate:         new \DateTimeImmutable('2026-09-05'),
            endDate:            new \DateTimeImmutable('2027-09-05'),
            amount:             4500,
            payerPhone:         '0708374149',
            accountReference:   'Test',
            frequency:          Frequency::Monthly,
        );

        self::assertTrue($service->isAccepted($response));
        self::assertSame(
            'The service request is processed successfully.',
            $service->responseDescription($response),
        );
    }

    public function test_create_for_buy_goods_is_accepted(): void
    {
        $config = $this->makeConfig();
        $http   = $this->makeHttpClientWithMockedToken($config, [
            ['status' => 200, 'body' => $this->ratibaAcceptedBody()],
        ]);

        $service  = new MpesaRatiba($config, $http);
        $response = $service->createForBuyGoods(
            standingOrderName: 'Weekly Milk Run',
            startDate:         new \DateTimeImmutable('2026-09-05'),
            endDate:            new \DateTimeImmutable('2027-09-05'),
            amount:             500,
            payerPhone:         '0708374149',
            accountReference:   'Milk',
            frequency:          Frequency::Weekly,
        );

        self::assertTrue($service->isAccepted($response));
    }

    public function test_throws_when_standing_order_name_missing(): void
    {
        $this->expectException(ValidationException::class);

        $config  = $this->makeConfig();
        $http    = $this->makeHttpClientWithMockedToken($config, []);
        $service = new MpesaRatiba($config, $http);

        $service->createForPayBill(
            standingOrderName: '',
            startDate:         new \DateTimeImmutable('2026-09-05'),
            endDate:            new \DateTimeImmutable('2027-09-05'),
            amount:             4500,
            payerPhone:         '0708374149',
            accountReference:   'Test',
            frequency:          Frequency::Monthly,
        );
    }

    public function test_throws_when_end_date_before_start_date(): void
    {
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessageMatches('/end_date|End date/');

        $config  = $this->makeConfig();
        $http    = $this->makeHttpClientWithMockedToken($config, []);
        $service = new MpesaRatiba($config, $http);

        $service->createForPayBill(
            standingOrderName: 'Test',
            startDate:         new \DateTimeImmutable('2027-09-05'),
            endDate:            new \DateTimeImmutable('2026-09-05'),
            amount:             4500,
            payerPhone:         '0708374149',
            accountReference:   'Test',
            frequency:          Frequency::Monthly,
        );
    }

    public function test_throws_when_account_reference_exceeds_12_chars(): void
    {
        $this->expectException(ValidationException::class);

        $config  = $this->makeConfig();
        $http    = $this->makeHttpClientWithMockedToken($config, []);
        $service = new MpesaRatiba($config, $http);

        $service->createForPayBill(
            standingOrderName: 'Test',
            startDate:         new \DateTimeImmutable('2026-09-05'),
            endDate:            new \DateTimeImmutable('2027-09-05'),
            amount:             4500,
            payerPhone:         '0708374149',
            accountReference:   'ThisReferenceIsWayTooLong',
            frequency:          Frequency::Monthly,
        );
    }

    public function test_throws_when_callback_url_missing_and_no_config_default(): void
    {
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessageMatches('/callback/');

        $config  = $this->makeConfig(['callbackUrl' => '']);
        $http    = $this->makeHttpClientWithMockedToken($config, []);
        $service = new MpesaRatiba($config, $http);

        $service->createForPayBill(
            standingOrderName: 'Test',
            startDate:         new \DateTimeImmutable('2026-09-05'),
            endDate:            new \DateTimeImmutable('2027-09-05'),
            amount:             4500,
            payerPhone:         '0708374149',
            accountReference:   'Test',
            frequency:          Frequency::Monthly,
        );
    }

    public function test_isAccepted_returns_false_for_error_response(): void
    {
        $config = $this->makeConfig();
        $http   = $this->makeHttpClientWithMockedToken($config, [
            ['status' => 200, 'body' => [
                'ResponseHeader' => [
                    'responseCode' => '1037',
                    'ResultDesc'   => 'Error',
                ],
            ]],
        ]);

        $service  = new MpesaRatiba($config, $http);
        $response = $service->createForPayBill(
            standingOrderName: 'Test',
            startDate:         new \DateTimeImmutable('2026-09-05'),
            endDate:            new \DateTimeImmutable('2027-09-05'),
            amount:             4500,
            payerPhone:         '0708374149',
            accountReference:   'Test',
            frequency:          Frequency::Monthly,
        );

        self::assertFalse($service->isAccepted($response));
    }
}
