<?php

declare(strict_types=1);

namespace Daraja\Services;

use Daraja\Config;
use Daraja\Exceptions\ValidationException;
use Daraja\Http\HttpClient;
use Daraja\Http\Response;

/**
 * B2B Express Checkout (USSD Push to Till) Service.
 *
 * Enables a merchant to initiate a USSD Push to a fellow merchant, prompting
 * them to pay from their own till number into the vendor's paybill. Unlike
 * the other B2B/B2C flows, this API does not use InitiatorName /
 * SecurityCredential — authorization happens entirely at the Daraja
 * (Apigee) layer via the app's consumer key/secret.
 *
 * Flow:
 *   1. The vendor (you) initiates the USSD push via this service.
 *   2. Daraja sends an STK-style prompt to the merchant's phone.
 *   3. The merchant enters their Operator ID and M-Pesa PIN.
 *   4. M-Pesa debits the merchant and credits the vendor.
 *   5. Daraja POSTs the final result to your callback URL.
 *
 * Endpoint:
 *   POST /v1/ussdpush/get-msisdn
 *
 * Note: this endpoint sits directly under the environment host (no /mpesa
 * prefix), unlike most other Daraja APIs.
 *
 * @see https://developer.safaricom.co.ke/apis/B2BExpressCheckout
 */
final class B2BExpressCheckout
{
    private const ENDPOINT = '/v1/ussdpush/get-msisdn';

    public function __construct(
        private readonly Config     $config,
        private readonly HttpClient $http,
    ) {}

    /**
     * Initiate a USSD Push to Till request.
     *
     * @param  string $primaryShortCode   The merchant's till (debit party) shortcode.
     * @param  string $receiverShortCode  The vendor's paybill (credit party) shortcode.
     * @param  int    $amount             Amount to be sent to the vendor, in KES.
     * @param  string $paymentRef         Reference shown to the merchant in the USSD prompt.
     * @param  string $partnerName        Vendor's organization friendly name, shown to the merchant.
     * @param  string $requestRefId       Unique identifier for this request. A UUID v4 is generated when omitted.
     * @param  string $callbackUrl        HTTPS endpoint that receives the final confirmation. Falls back to Config::$callbackUrl.
     * @throws ValidationException
     * @return Response
     */
    public function push(
        string $primaryShortCode,
        string $receiverShortCode,
        int    $amount,
        string $paymentRef,
        string $partnerName,
        string $requestRefId = '',
        string $callbackUrl = '',
    ): Response {
        $requestRefId = $requestRefId !== '' ? $requestRefId : $this->generateRequestRefId();
        $callbackUrl  = $callbackUrl !== '' ? $callbackUrl : $this->config->callbackUrl;

        $this->validate(
            primaryShortCode: $primaryShortCode,
            receiverShortCode: $receiverShortCode,
            amount: $amount,
            paymentRef: $paymentRef,
            partnerName: $partnerName,
            callbackUrl: $callbackUrl,
        );

        return $this->http->post(self::ENDPOINT, [
            'primaryShortCode'  => $primaryShortCode,
            'receiverShortCode' => $receiverShortCode,
            'amount'            => (string) $amount,
            'paymentRef'        => $paymentRef,
            'callbackUrl'       => $callbackUrl,
            'partnerName'       => $partnerName,
            'RequestRefID'      => $requestRefId,
        ]);
    }

    /** @throws ValidationException */
    private function validate(
        string $primaryShortCode,
        string $receiverShortCode,
        int    $amount,
        string $paymentRef,
        string $partnerName,
        string $callbackUrl,
    ): void {
        $errors = [];

        if (empty($primaryShortCode)) {
            $errors['primary_short_code'] = 'primaryShortCode (merchant till) is required';
        }

        if (empty($receiverShortCode)) {
            $errors['receiver_short_code'] = 'receiverShortCode (vendor paybill) is required';
        }

        if ($amount < 1) {
            $errors['amount'] = 'Amount must be at least 1 KES';
        }

        if (empty($paymentRef)) {
            $errors['payment_ref'] = 'paymentRef is required';
        }

        if (empty($partnerName)) {
            $errors['partner_name'] = 'partnerName is required';
        }

        if (empty($callbackUrl) || !filter_var($callbackUrl, FILTER_VALIDATE_URL)) {
            $errors['callback_url'] = 'A valid HTTPS callback URL is required';
        }

        if ($errors !== []) {
            throw new ValidationException($errors);
        }
    }

    private function generateRequestRefId(): string
    {
        $data    = random_bytes(16);
        $data[6] = chr(ord($data[6]) & 0x0f | 0x40);
        $data[8] = chr(ord($data[8]) & 0x3f | 0x80);

        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
    }
}
