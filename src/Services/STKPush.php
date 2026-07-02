<?php

declare(strict_types=1);

namespace Daraja\Services;

use Daraja\Config;
use Daraja\Exceptions\ValidationException;
use Daraja\Http\HttpClient;
use Daraja\Http\Response;
use Daraja\ValueObjects\PhoneNumber;

/**
 * Lipa na M-Pesa Online (STK Push) Service.
 *
 * Allows a business to initiate a payment request on behalf of a customer.
 * The customer receives an STK Push prompt on their phone and enters their PIN.
 *
 * Endpoints:
 *   POST /mpesa/stkpush/v1/processrequest  — Initiate STK Push
 *   POST /mpesa/stkpush/v1/query           — Query STK Push status
 */
final class STKPush
{
    private const PUSH_ENDPOINT  = '/mpesa/stkpush/v1/processrequest';
    private const QUERY_ENDPOINT = '/mpesa/stkpush/v1/query';

    public function __construct(
        private readonly Config     $config,
        private readonly HttpClient $http,
    ) {}

    /**
     * Initiate an STK Push payment request.
     *
     * @param  string|PhoneNumber $phone           Customer phone number (any Kenyan format)
     * @param  int                $amount          Amount in KES (whole shillings, minimum 1)
     * @param  string             $accountReference Shown to customer in STK prompt (max 12 chars)
     * @param  string             $description     Transaction description (max 13 chars)
     * @param  string             $callbackUrl     HTTPS URL to receive the result callback
     * @param  string|null        $shortcode       Override business shortcode (default: config shortcode)
     * @throws ValidationException
     * @return Response
     */
    public function push(
        string|PhoneNumber $phone,
        int                $amount,
        string             $accountReference,
        string             $description = 'Payment',
        string             $callbackUrl = '',
        ?string            $shortcode = null,
    ): Response {
        $phone       = $phone instanceof PhoneNumber ? $phone : PhoneNumber::from((string) $phone);
        $shortcode   = $shortcode ?? $this->config->shortcode;
        $callbackUrl = $callbackUrl ?: $this->config->callbackUrl;

        $this->validatePushParams($amount, $accountReference, $description, $callbackUrl);

        [$timestamp, $password] = $this->buildTimestampAndPassword($shortcode);

        return $this->http->post(self::PUSH_ENDPOINT, [
            'BusinessShortCode' => $shortcode,
            'Password'          => $password,
            'Timestamp'         => $timestamp,
            'TransactionType'   => 'CustomerPayBillOnline',
            'Amount'            => $amount,
            'PartyA'            => $phone->value(),
            'PartyB'            => $shortcode,
            'PhoneNumber'       => $phone->value(),
            'CallBackURL'       => $callbackUrl,
            'AccountReference'  => substr($accountReference, 0, 12),
            'TransactionDesc'   => substr($description, 0, 13),
        ]);
    }

    /**
     * Initiate an STK Push for Buy Goods (Till number).
     *
     * Uses `CustomerBuyGoodsOnline` transaction type with PartyB set to the till number.
     *
     * @param  string|PhoneNumber $phone    Customer phone number
     * @param  int                $amount   Amount in KES
     * @param  string             $till     Buy Goods till number
     * @param  string             $callbackUrl HTTPS callback URL
     * @throws ValidationException
     * @return Response
     */
    public function pushBuyGoods(
        string|PhoneNumber $phone,
        int                $amount,
        string             $till,
        string             $description = 'Payment',
        string             $callbackUrl = '',
    ): Response {
        $phone       = $phone instanceof PhoneNumber ? $phone : PhoneNumber::from((string) $phone);
        $shortcode   = $this->config->shortcode;
        $callbackUrl = $callbackUrl ?: $this->config->callbackUrl;

        $this->validatePushParams($amount, $till, $description, $callbackUrl);

        [$timestamp, $password] = $this->buildTimestampAndPassword($shortcode);

        return $this->http->post(self::PUSH_ENDPOINT, [
            'BusinessShortCode' => $shortcode,
            'Password'          => $password,
            'Timestamp'         => $timestamp,
            'TransactionType'   => 'CustomerBuyGoodsOnline',
            'Amount'            => $amount,
            'PartyA'            => $phone->value(),
            'PartyB'            => $till,
            'PhoneNumber'       => $phone->value(),
            'CallBackURL'       => $callbackUrl,
            'AccountReference'  => substr($till, 0, 12),
            'TransactionDesc'   => substr($description, 0, 13),
        ]);
    }

    /**
     * Query the status of an STK Push transaction.
     *
     * Use this to poll for the result when the callback hasn't arrived,
     * or when M-Pesa couldn't deliver the callback.
     *
     * @param  string      $checkoutRequestId The CheckoutRequestID from the push() response
     * @param  string|null $shortcode         Override shortcode
     * @return Response
     */
    public function query(string $checkoutRequestId, ?string $shortcode = null): Response
    {
        $shortcode = $shortcode ?? $this->config->shortcode;

        if (empty($checkoutRequestId)) {
            throw new ValidationException(['checkout_request_id' => 'CheckoutRequestID is required']);
        }

        [$timestamp, $password] = $this->buildTimestampAndPassword($shortcode);

        return $this->http->post(self::QUERY_ENDPOINT, [
            'BusinessShortCode' => $shortcode,
            'Password'          => $password,
            'Timestamp'         => $timestamp,
            'CheckoutRequestID' => $checkoutRequestId,
        ]);
    }

    /**
     * Generate the Daraja timestamp and Base64-encoded password.
     *
     * Password = Base64(Shortcode + Passkey + Timestamp)
     *
     * @return array{string, string} [$timestamp, $password]
     */
    private function buildTimestampAndPassword(string $shortcode): array
    {
        $timestamp = (new \DateTimeImmutable('now', new \DateTimeZone('Africa/Nairobi')))
            ->format('YmdHis');

        $password = base64_encode($shortcode . $this->config->passkey . $timestamp);

        return [$timestamp, $password];
    }

    /** @throws ValidationException */
    private function validatePushParams(
        int    $amount,
        string $accountReference,
        string $description,
        string $callbackUrl,
    ): void {
        $errors = [];

        if ($amount < 1) {
            $errors['amount'] = 'Amount must be at least 1 KES';
        }

        if (empty($accountReference)) {
            $errors['account_reference'] = 'Account reference is required';
        }

        if (empty($description)) {
            $errors['description'] = 'Transaction description is required';
        }

        if (empty($callbackUrl)) {
            $errors['callback_url'] = 'Callback URL is required (set in Config or pass per request)';
        } elseif (!filter_var($callbackUrl, FILTER_VALIDATE_URL)) {
            $errors['callback_url'] = 'Callback URL must be a valid HTTPS URL';
        }

        if ($errors !== []) {
            throw new ValidationException($errors);
        }
    }
}
