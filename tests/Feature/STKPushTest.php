<?php

declare(strict_types=1);

namespace Daraja\Tests\Feature;

use Daraja\Config;
use Daraja\Exceptions\ValidationException;
use Daraja\Services\STKPush;
use Daraja\Tests\DarajaTestCase;
use Daraja\ValueObjects\PhoneNumber;

final class STKPushTest extends DarajaTestCase
{
    // -------------------------------------------------------------------------
    // push()
    // -------------------------------------------------------------------------

    public function test_push_returns_accepted_response(): void
    {
        $config = $this->makeConfig();
        $http   = $this->makeHttpClientWithMockedToken($config, [
            ['status' => 200, 'body' => $this->stkPushAcceptedBody()],
        ]);

        $stk      = new STKPush($config, $http);
        $response = $stk->push(
            phone:            '0712345678',
            amount:           100,
            accountReference: 'INV-001',
            description:      'Test payment',
            callbackUrl:      'https://example.com/callback',
        );

        self::assertTrue($response->isAccepted());
        self::assertSame('ws_CO_191220191020363925', $response->checkoutRequestId());
        self::assertSame('0', $response->getString('ResponseCode'));
    }

    public function test_push_accepts_phone_number_value_object(): void
    {
        $config = $this->makeConfig();
        $http   = $this->makeHttpClientWithMockedToken($config, [
            ['status' => 200, 'body' => $this->stkPushAcceptedBody()],
        ]);

        $stk      = new STKPush($config, $http);
        $response = $stk->push(
            phone:            PhoneNumber::from('+254712345678'),
            amount:           500,
            accountReference: 'ORD-123',
            callbackUrl:      'https://example.com/callback',
        );

        self::assertTrue($response->isSuccessful());
    }

    public function test_push_uses_config_callback_url_when_not_provided(): void
    {
        $config = $this->makeConfig(['callbackUrl' => 'https://example.com/mpesa/callback']);
        $http   = $this->makeHttpClientWithMockedToken($config, [
            ['status' => 200, 'body' => $this->stkPushAcceptedBody()],
        ]);

        $stk = new STKPush($config, $http);
        // No callbackUrl passed — should use config default
        $response = $stk->push(
            phone:            '0712345678',
            amount:           100,
            accountReference: 'INV-001',
        );

        self::assertTrue($response->isAccepted());
    }

    public function test_push_throws_if_amount_is_zero(): void
    {
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessageMatches('/amount/i');

        $config = $this->makeConfig();
        $http   = $this->makeHttpClientWithMockedToken($config, []);
        $stk    = new STKPush($config, $http);

        $stk->push(
            phone:            '0712345678',
            amount:           0,
            accountReference: 'INV-001',
            callbackUrl:      'https://example.com/callback',
        );
    }

    public function test_push_throws_if_callback_url_is_missing(): void
    {
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessageMatches('/Callback URL/i');

        // Config has no callbackUrl set
        $config = Config::make(
            consumerKey:    'key',
            consumerSecret: 'secret',
            shortcode:      '174379',
            passkey:        'passkey',
        );
        $http = $this->makeHttpClientWithMockedToken($config, []);
        $stk  = new STKPush($config, $http);

        $stk->push(
            phone:            '0712345678',
            amount:           100,
            accountReference: 'INV-001',
            // callbackUrl intentionally omitted
        );
    }

    public function test_push_truncates_account_reference_to_12_chars(): void
    {
        // This just verifies no exception is thrown for long references
        $config = $this->makeConfig();
        $http   = $this->makeHttpClientWithMockedToken($config, [
            ['status' => 200, 'body' => $this->stkPushAcceptedBody()],
        ]);

        $stk      = new STKPush($config, $http);
        $response = $stk->push(
            phone:            '0712345678',
            amount:           100,
            accountReference: 'THIS_IS_A_VERY_LONG_ACCOUNT_REFERENCE_STRING',
            callbackUrl:      'https://example.com/callback',
        );

        self::assertTrue($response->isSuccessful());
    }

    // -------------------------------------------------------------------------
    // pushBuyGoods()
    // -------------------------------------------------------------------------

    public function test_push_buy_goods_returns_accepted_response(): void
    {
        $config = $this->makeConfig();
        $http   = $this->makeHttpClientWithMockedToken($config, [
            ['status' => 200, 'body' => $this->stkPushAcceptedBody()],
        ]);

        $stk      = new STKPush($config, $http);
        $response = $stk->pushBuyGoods(
            phone:       '0712345678',
            amount:      250,
            till:        '123456',
            description: 'Buy goods test',
            callbackUrl: 'https://example.com/callback',
        );

        self::assertTrue($response->isAccepted());
    }

    // -------------------------------------------------------------------------
    // query()
    // -------------------------------------------------------------------------

    public function test_query_returns_response_for_valid_checkout_id(): void
    {
        $config = $this->makeConfig();
        $http   = $this->makeHttpClientWithMockedToken($config, [
            [
                'status' => 200,
                'body'   => [
                    'ResponseCode'    => '0',
                    'ResponseDescription' => 'The service request has been accepted successfully.',
                    'MerchantRequestID'   => '29115-34620561-1',
                    'CheckoutRequestID'   => 'ws_CO_191220191020363925',
                    'ResultCode'          => '0',
                    'ResultDesc'          => 'The service request is processed successfully.',
                ],
            ],
        ]);

        $stk      = new STKPush($config, $http);
        $response = $stk->query('ws_CO_191220191020363925');

        self::assertTrue($response->isAccepted());
        self::assertSame(
            'The service request is processed successfully.',
            $response->resultDescription()
        );
    }

    public function test_query_throws_if_checkout_request_id_is_empty(): void
    {
        $this->expectException(ValidationException::class);

        $config = $this->makeConfig();
        $http   = $this->makeHttpClientWithMockedToken($config, []);
        $stk    = new STKPush($config, $http);

        $stk->query('');
    }
}
