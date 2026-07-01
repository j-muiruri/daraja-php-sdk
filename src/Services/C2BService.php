<?php

declare(strict_types=1);

namespace Daraja\Services;

use Daraja\Config;
use Daraja\Exceptions\ValidationException;
use Daraja\Http\HttpClient;
use Daraja\Http\Response;
use Daraja\ValueObjects\PhoneNumber;

/**
 * Customer to Business (C2B) Service.
 *
 * Allows businesses to receive payments from M-Pesa customers.
 * The customer initiates the payment from their phone (Paybill / Buy Goods).
 *
 * Workflow:
 *  1. registerUrls()  — Tell Daraja where to send validation/confirmation callbacks
 *  2. simulate()      — (Sandbox only) Trigger a test payment against registered URLs
 *
 * Endpoints:
 *   POST /mpesa/c2b/v2/registerurl  — Register validation & confirmation URLs
 *   POST /mpesa/c2b/v2/simulate     — Simulate a C2B payment (sandbox only)
 */
final class C2BService
{
    private const REGISTER_URL_ENDPOINT = '/mpesa/c2b/v2/registerurl';
    private const SIMULATE_ENDPOINT     = '/mpesa/c2b/v2/simulate';

    public function __construct(
        private readonly Config     $config,
        private readonly HttpClient $http,
    ) {}

    /**
     * Register validation and confirmation URLs for a shortcode.
     *
     * M-Pesa will POST to these URLs whenever a payment is received:
     *  - Validation URL: Called first; you can accept (200) or reject (non-200) the payment.
     *  - Confirmation URL: Called after a successful payment with full transaction details.
     *
     * @param  string $confirmationUrl HTTPS URL for payment confirmations
     * @param  string $validationUrl   HTTPS URL for payment validation (optional)
     * @param  string $responseType    'Completed' (skip validation) or 'Cancelled' (reject if no validation response)
     * @param  string|null $shortcode  Override shortcode (default: config shortcode)
     * @throws ValidationException
     */
    public function registerUrls(
        string  $confirmationUrl,
        string  $validationUrl = '',
        string  $responseType = 'Completed',
        ?string $shortcode = null,
    ): Response {
        $shortcode = $shortcode ?? $this->config->shortcode;

        $this->validateUrls($confirmationUrl, $validationUrl);
        $this->validateResponseType($responseType);

        $payload = [
            'ShortCode'       => $shortcode,
            'ResponseType'    => $responseType,
            'ConfirmationURL' => $confirmationUrl,
            'ValidationURL'   => $validationUrl ?: $confirmationUrl,
        ];

        return $this->http->post(self::REGISTER_URL_ENDPOINT, $payload);
    }

    /**
     * Simulate a C2B payment. Sandbox only.
     *
     * Triggers a mock payment to your registered Confirmation URL.
     * Uses the C2B v2 simulate endpoint.
     *
     * @param  string|PhoneNumber $phone         Simulated customer phone
     * @param  int                $amount        Amount in KES
     * @param  string             $billRefNumber Account reference / bill reference number
     * @param  string             $commandId     'CustomerPayBillOnline' or 'CustomerBuyGoodsOnline'
     * @param  string|null        $shortcode     Override shortcode
     * @throws ValidationException
     */
    public function simulate(
        string|PhoneNumber $phone,
        int                $amount,
        string             $billRefNumber = 'TestPayment',
        string             $commandId = 'CustomerPayBillOnline',
        ?string            $shortcode = null,
    ): Response {
        if (!$this->config->isSandbox()) {
            throw new ValidationException(
                ['environment' => 'C2B simulate is only available in the sandbox environment']
            );
        }

        $phone     = $phone instanceof PhoneNumber ? $phone : PhoneNumber::from((string) $phone);
        $shortcode = $shortcode ?? $this->config->shortcode;

        $this->validateSimulateParams($amount, $commandId);

        return $this->http->post(self::SIMULATE_ENDPOINT, [
            'ShortCode'     => $shortcode,
            'CommandID'     => $commandId,
            'Amount'        => $amount,
            'Msisdn'        => $phone->value(),
            'BillRefNumber' => $billRefNumber,
        ]);
    }

    /** @throws ValidationException */
    private function validateUrls(string $confirmationUrl, string $validationUrl): void
    {
        $errors = [];

        if (empty($confirmationUrl) || !filter_var($confirmationUrl, FILTER_VALIDATE_URL)) {
            $errors['confirmation_url'] = 'A valid HTTPS confirmation URL is required';
        }

        if (!empty($validationUrl) && !filter_var($validationUrl, FILTER_VALIDATE_URL)) {
            $errors['validation_url'] = 'Validation URL must be a valid HTTPS URL';
        }

        if ($errors !== []) {
            throw new ValidationException($errors);
        }
    }

    /** @throws ValidationException */
    private function validateResponseType(string $responseType): void
    {
        $allowed = ['Completed', 'Cancelled'];

        if (!in_array($responseType, $allowed, true)) {
            throw new ValidationException([
                'response_type' => 'ResponseType must be one of: ' . implode(', ', $allowed),
            ]);
        }
    }

    /** @throws ValidationException */
    private function validateSimulateParams(int $amount, string $commandId): void
    {
        $errors = [];

        if ($amount < 1) {
            $errors['amount'] = 'Amount must be at least 1 KES';
        }

        $allowedCommands = ['CustomerPayBillOnline', 'CustomerBuyGoodsOnline'];

        if (!in_array($commandId, $allowedCommands, true)) {
            $errors['command_id'] = 'CommandID must be one of: ' . implode(', ', $allowedCommands);
        }

        if ($errors !== []) {
            throw new ValidationException($errors);
        }
    }
}
