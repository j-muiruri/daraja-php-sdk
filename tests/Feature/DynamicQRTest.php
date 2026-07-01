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

final class DynamicQRTest extends DarajaTestCase
{
    private function fakeQRBody(): array
    {
        return [
            'ResponseCode'        => '00',
            'ResponseDescription' => 'The service request is processed successfully.',
            'QRCode'              => base64_encode('fake-png-bytes'),
        ];
    }

    public function test_generate_returns_response_with_qr_code(): void
    {
        $config = $this->makeConfig();
        $http   = $this->makeHttpClientWithMockedToken($config, [
            ['status' => 200, 'body' => $this->fakeQRBody()],
        ]);

        $svc      = new DynamicQR($config, $http);
        $response = $svc->generate('My Shop', 'INV-001', 500);

        self::assertNotEmpty($svc->extractImage($response));
    }

    public function test_generate_with_buy_goods_type(): void
    {
        $config = $this->makeConfig();
        $http   = $this->makeHttpClientWithMockedToken($config, [
            ['status' => 200, 'body' => $this->fakeQRBody()],
        ]);

        $svc      = new DynamicQR($config, $http);
        $response = $svc->generate(
            merchantName:  'Coffee Shop',
            refNo:         'ORD-999',
            amount:        150,
            type:          QRCodeType::DynamicMerchant,
            size:          500,
        );

        self::assertNotEmpty($response->getString('QRCode'));
    }

    public function test_throws_if_size_out_of_range(): void
    {
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessageMatches('/size/');

        $config = $this->makeConfig();
        $http   = $this->makeHttpClientWithMockedToken($config, []);
        $svc    = new DynamicQR($config, $http);

        $svc->generate('Shop', 'ref', 100, QRCodeType::DynamicMerchant, 100);
    }

    public function test_throws_if_amount_is_negative(): void
    {
        $this->expectException(ValidationException::class);

        $config = $this->makeConfig();
        $http   = $this->makeHttpClientWithMockedToken($config, []);
        $svc    = new DynamicQR($config, $http);

        $svc->generate('Shop', 'ref', -1);
    }

    public function test_save_image_writes_decoded_bytes_to_file(): void
    {
        $config = $this->makeConfig();
        $http   = $this->makeHttpClientWithMockedToken($config, [
            ['status' => 200, 'body' => $this->fakeQRBody()],
        ]);

        $svc      = new DynamicQR($config, $http);
        $response = $svc->generate('Shop', 'ref', 100);
        $path     = sys_get_temp_dir() . '/test_qr_' . uniqid() . '.png';

        $svc->saveImage($response, $path);

        self::assertFileExists($path);
        self::assertSame('fake-png-bytes', file_get_contents($path));

        unlink($path);
    }
}
