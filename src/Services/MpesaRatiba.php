<?php

declare(strict_types=1);

namespace Daraja\Services;

use Daraja\Config;
use Daraja\Enums\Frequency;
use Daraja\Enums\IdentifierType;
use Daraja\Exceptions\ValidationException;
use Daraja\Http\HttpClient;
use Daraja\Http\Response;
use Daraja\Support\Url;
use Daraja\ValueObjects\PhoneNumber;

/**
 * M-Pesa Ratiba (Standing Order) Service.
 *
 * Creates a recurring standing order on a customer's M-Pesa profile: an
 * NI-Push (STK-style) PIN prompt is sent once for consent, then M-Pesa
 * auto-executes the payment on the configured schedule without further
 * customer interaction.
 *
 * This is a **commercial API** — sandbox testing is self-serve, but going
 * live requires emailing apisupport@safaricom.co.ke for a commercial
 * agreement before the product is attached to your shortcode.
 *
 * Note on response shape: unlike every other Daraja API in this SDK, the
 * Ratiba response body is nested under a `ResponseHeader`/`ResponseBody`
 * envelope rather than flat top-level fields. Use
 * {@see MpesaRatiba::isAccepted()} and {@see MpesaRatiba::responseDescription()}
 * instead of `Response::isAccepted()`, which only inspects top-level keys.
 *
 * Endpoint:
 *   POST /standingorder/v1/createStandingOrderExternal
 *
 * @see https://developer.safaricom.co.ke/apis/MpesaRatiba
 */
final class MpesaRatiba
{
    private const ENDPOINT = '/standingorder/v1/createStandingOrderExternal';

    public function __construct(
        private readonly Config     $config,
        private readonly HttpClient $http,
    ) {}

    /**
     * Create a standing order against a Paybill.
     *
     * @param  string             $standingOrderName  Unique name for this standing order per customer (max 90 chars).
     * @param  \DateTimeInterface $startDate           Date the standing order starts executing.
     * @param  \DateTimeInterface $endDate             Date the standing order stops executing.
     * @param  int                $amount              Amount deducted from the customer on each execution, in KES.
     * @param  string|PhoneNumber $payerPhone          Customer's M-Pesa registered phone number (PartyA).
     * @param  string             $accountReference    Account number shown at the paybill (max 12 chars).
     * @param  Frequency          $frequency            How often the order executes.
     * @param  string             $transactionDesc     Free text description (max 13 chars).
     * @param  string             $callbackUrl         HTTPS endpoint for the result callback. Falls back to Config::$callbackUrl.
     * @throws ValidationException
     * @return Response
     */
    public function createForPayBill(
        string             $standingOrderName,
        \DateTimeInterface $startDate,
        \DateTimeInterface $endDate,
        int                $amount,
        string|PhoneNumber $payerPhone,
        string             $accountReference,
        Frequency          $frequency,
        string             $transactionDesc = 'StandingOrder',
        string             $callbackUrl = '',
    ): Response {
        return $this->create(
            standingOrderName: $standingOrderName,
            startDate: $startDate,
            endDate: $endDate,
            businessShortCode: $this->config->shortcode,
            transactionType: 'StandingOrder',
            receiverIdentifierType: IdentifierType::Shortcode,
            amount: $amount,
            payerPhone: $payerPhone,
            accountReference: $accountReference,
            frequency: $frequency,
            transactionDesc: $transactionDesc,
            callbackUrl: $callbackUrl,
        );
    }

    /**
     * Create a standing order against a Buy Goods till number.
     *
     * @see self::createForPayBill() for parameter descriptions.
     * @throws ValidationException
     */
    public function createForBuyGoods(
        string             $standingOrderName,
        \DateTimeInterface $startDate,
        \DateTimeInterface $endDate,
        int                $amount,
        string|PhoneNumber $payerPhone,
        string             $accountReference,
        Frequency          $frequency,
        string             $transactionDesc = 'StandingOrder',
        string             $callbackUrl = '',
    ): Response {
        return $this->create(
            standingOrderName: $standingOrderName,
            startDate: $startDate,
            endDate: $endDate,
            businessShortCode: $this->config->shortcode,
            transactionType: 'Standing Order Customer Pay Marchant',
            receiverIdentifierType: IdentifierType::TillNumber,
            amount: $amount,
            payerPhone: $payerPhone,
            accountReference: $accountReference,
            frequency: $frequency,
            transactionDesc: $transactionDesc,
            callbackUrl: $callbackUrl,
        );
    }

    /**
     * Low-level standing order creation. Prefer the PayBill/BuyGoods helpers above.
     *
     * @throws ValidationException
     */
    public function create(
        string             $standingOrderName,
        \DateTimeInterface $startDate,
        \DateTimeInterface $endDate,
        string             $businessShortCode,
        string             $transactionType,
        IdentifierType     $receiverIdentifierType,
        int                $amount,
        string|PhoneNumber $payerPhone,
        string             $accountReference,
        Frequency          $frequency,
        string             $transactionDesc = 'StandingOrder',
        string             $callbackUrl = '',
    ): Response {
        $payerPhone  = $payerPhone instanceof PhoneNumber ? $payerPhone : PhoneNumber::from((string) $payerPhone);
        $callbackUrl = $callbackUrl !== '' ? $callbackUrl : $this->config->callbackUrl;

        $this->validate(
            standingOrderName: $standingOrderName,
            startDate: $startDate,
            endDate: $endDate,
            businessShortCode: $businessShortCode,
            amount: $amount,
            accountReference: $accountReference,
            transactionDesc: $transactionDesc,
            callbackUrl: $callbackUrl,
        );

        return $this->http->post(self::ENDPOINT, [
            'StandingOrderName'          => $standingOrderName,
            'StartDate'                  => $startDate->format('Ymd'),
            'EndDate'                    => $endDate->format('Ymd'),
            'BusinessShortCode'          => $businessShortCode,
            'TransactionType'            => $transactionType,
            'ReceiverPartyIdentifierType' => (string) $receiverIdentifierType->value,
            'Amount'                     => (string) $amount,
            'PartyA'                     => $payerPhone->value(),
            'CallBackURL'                => $callbackUrl,
            'AccountReference'           => substr($accountReference, 0, 12),
            'TransactionDesc'            => substr($transactionDesc, 0, 13),
            'Frequency'                  => (string) $frequency->value,
        ]);
    }

    /**
     * True when Daraja accepted the standing order request for processing.
     *
     * Ratiba nests its status under `ResponseHeader.responseCode`, unlike the
     * flat top-level `ResponseCode` used elsewhere in the SDK.
     */
    public function isAccepted(Response $response): bool
    {
        return $this->header($response, 'responseCode') === '200';
    }

    /** Human-readable status message from the nested ResponseHeader. */
    public function responseDescription(Response $response): string
    {
        return $this->header($response, 'ResultDesc')
            ?: $this->header($response, 'responseDescription');
    }

    private function header(Response $response, string $key): string
    {
        $header = $response->data()['ResponseHeader'] ?? [];

        return is_array($header) ? (string) ($header[$key] ?? '') : '';
    }

    /** @throws ValidationException */
    private function validate(
        string             $standingOrderName,
        \DateTimeInterface $startDate,
        \DateTimeInterface $endDate,
        string             $businessShortCode,
        int                $amount,
        string             $accountReference,
        string             $transactionDesc,
        string             $callbackUrl,
    ): void {
        $errors = [];

        if (empty($standingOrderName)) {
            $errors['standing_order_name'] = 'Standing order name is required and must be unique per customer';
        }

        if ($endDate < $startDate) {
            $errors['end_date'] = 'End date must not be before the start date';
        }

        if (empty($businessShortCode)) {
            $errors['business_short_code'] = 'Business shortcode is required';
        }

        if ($amount < 1) {
            $errors['amount'] = 'Amount must be at least 1 KES (whole numbers only)';
        }

        if (empty($accountReference)) {
            $errors['account_reference'] = 'Account reference is required';
        } elseif (strlen($accountReference) > 12) {
            $errors['account_reference'] = 'Account reference must not exceed 12 characters';
        }

        if (strlen($transactionDesc) > 13) {
            $errors['transaction_desc'] = 'Transaction description must not exceed 13 characters';
        }

        if (empty($callbackUrl) || !Url::isHttps($callbackUrl)) {
            $errors['callback_url'] = 'A valid HTTPS callback URL is required';
        }

        if ($errors !== []) {
            throw new ValidationException($errors);
        }
    }
}
