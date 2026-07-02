<?php

declare(strict_types=1);

namespace Daraja\Services;

use Daraja\Config;
use Daraja\Enums\QRCodeType;
use Daraja\Exceptions\ValidationException;
use Daraja\Http\HttpClient;
use Daraja\Http\Response;

/**
 * Dynamic QR Code Service.
 *
 * Generates M-Pesa QR codes that customers scan using the M-Pesa app
 * or any QR scanner to trigger a payment.
 *
 * The response contains a Base64-encoded PNG image of the QR code.
 *
 * Endpoint:
 *   POST /mpesa/qrcode/v1/generate
 */
final class DynamicQR
{
    private const ENDPOINT = '/mpesa/qrcode/v1/generate';

    public function __construct(
        private readonly Config     $config,
        private readonly HttpClient $http,
    ) {}

    /**
     * Generate a dynamic QR code for a payment.
     *
     * @param  string      $merchantName   Business name shown in the QR (max 20 chars)
     * @param  string      $refNo          Transaction/reference number (max 12 chars)
     * @param  int         $amount         Amount in KES (0 for open amount)
     * @param  QRCodeType  $type           QR type (DynamicMerchant, StaticMerchant, etc.)
     * @param  int         $size           QR image size in pixels (300–1000, default 400)
     * @param  string|null $creditPartyId  Till number or Paybill shortcode (default: config shortcode)
     * @throws ValidationException
     * @return Response
     */
    public function generate(
        string     $merchantName,
        string     $refNo,
        int        $amount = 0,
        QRCodeType $type = QRCodeType::DynamicMerchant,
        int        $size = 400,
        ?string    $creditPartyId = null,
    ): Response {
        $creditPartyId = $creditPartyId ?? $this->config->shortcode;

        $this->validateParams($merchantName, $refNo, $amount, $size, $creditPartyId);

        return $this->http->post(self::ENDPOINT, [
            'MerchantName'  => substr($merchantName, 0, 20),
            'RefNo'         => substr($refNo, 0, 12),
            'Amount'        => $amount,
            'TrxCode'       => $type->value,
            'CreditPartyIdentifier' => $creditPartyId,
            'Size'          => (string) $size,
        ]);
    }

    /**
     * Extract the Base64-encoded QR image string from the response.
     *@param \Daraja\Http\Response $response
     * Usage:
     *   $response = $client->qr()->generate(...);
     *   $base64   = $client->qr()->extractImage($response);
     *   echo '<img src="data:image/png;base64,' . $base64 . '">';
     */
    public function extractImage(Response $response): string
    {
        return $response->getString('QRCode');
    }

    /**
     * Decode and save the QR image to a file.
     *
     * @param  \Daraja\Http\Response $response     The response from generate()
     * @param  string   $filepath     Absolute path to save the PNG file
     * @throws QrImageException
     */
    public function saveImage(\Daraja\Http\Response $response, string $filepath): void
    {
        $base64 = $this->extractImage($response);

        if (empty($base64)) {
            throw new QrImageException('No QR image data in response');
        }

        $decoded = base64_decode($base64, strict: true);

        if ($decoded === false) {
            throw new QrImageException('Failed to decode Base64 QR image data');
        }

        if (file_put_contents($filepath, $decoded) === false) {
            throw new QrImageException("Failed to write QR image to: {$filepath}");
        }
    }

    /** @throws ValidationException */
    private function validateParams(
        string $merchantName,
        string $refNo,
        int    $amount,
        int    $size,
        string $creditPartyId,
    ): void {
        $errors = [];

        if (empty($merchantName)) {
            $errors['merchant_name'] = 'Merchant name is required';
        }

        if (empty($refNo)) {
            $errors['ref_no'] = 'Reference number is required';
        }

        if ($amount < 0) {
            $errors['amount'] = 'Amount cannot be negative';
        }

        if ($size < 300 || $size > 1000) {
            $errors['size'] = 'QR size must be between 300 and 1000 pixels';
        }

        if (empty($creditPartyId)) {
            $errors['credit_party_id'] = 'CreditPartyIdentifier (till or shortcode) is required';
        }

        if ($errors !== []) {
            throw new ValidationException($errors);
        }
    }
}

final class QrImageException extends \RuntimeException
{
}
