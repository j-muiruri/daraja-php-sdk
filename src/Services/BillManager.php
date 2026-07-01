<?php

declare(strict_types=1);

namespace Daraja\Services;

use Daraja\Config;
use Daraja\Exceptions\ValidationException;
use Daraja\Http\HttpClient;
use Daraja\Http\Response;
use Daraja\ValueObjects\Invoice;
use Daraja\ValueObjects\PhoneNumber;

/**
 * Bill Manager Service (eBill).
 *
 * Enables businesses to send M-Pesa payment invoices directly to customer phones.
 * Customers can pay directly from the notification.
 *
 * Workflow:
 *  1. optIn()           — Register your business on Bill Manager (one-time setup)
 *  2. sendInvoice()     — Send a single invoice to a customer
 *  3. sendBulk()        — Send multiple invoices in one call
 *  4. cancelInvoice()   — Cancel a single unpaid invoice
 *  5. cancelBulk()      — Cancel multiple invoices at once
 *
 * Reconciliation results arrive at your callbackUrl as Daraja posts confirmations.
 *
 * Endpoints:
 *   POST /v1/billmanager-invoice/optin
 *   POST /v1/billmanager-invoice/single-invoicing
 *   POST /v1/billmanager-invoice/bulk-invoicing
 *   POST /v1/billmanager-invoice/cancel-single-invoice
 *   POST /v1/billmanager-invoice/cancel-bulk-invoice
 *
 * @see https://developer.safaricom.co.ke/Documentation#bill-manager
 */
final class BillManager
{
    private const OPTIN_ENDPOINT          = '/v1/billmanager-invoice/optin';
    private const SINGLE_INVOICE_ENDPOINT = '/v1/billmanager-invoice/single-invoicing';
    private const BULK_INVOICE_ENDPOINT   = '/v1/billmanager-invoice/bulk-invoicing';
    private const CANCEL_SINGLE_ENDPOINT  = '/v1/billmanager-invoice/cancel-single-invoice';
    private const CANCEL_BULK_ENDPOINT    = '/v1/billmanager-invoice/cancel-bulk-invoice';

    public function __construct(
        private readonly Config     $config,
        private readonly HttpClient $http,
    ) {}

    /**
     * Opt in to Bill Manager (one-time setup per shortcode).
     *
     * Register your business details and the reconciliation callback URL.
     * After opt-in, your customers will see your logo and business name
     * in invoice notifications.
     *
     * @param  string $email           Business email address
     * @param  string $officialContact Business contact phone (Kenyan format)
     * @param  string $callbackUrl     HTTPS URL for payment reconciliation callbacks
     * @param  bool   $sendReminders   Send payment reminder SMS to customers before due date
     * @param  string $logo            Base64-encoded PNG/JPG logo image (optional)
     * @param  string|null $shortcode  Override shortcode
     * @throws ValidationException
     */
    public function optIn(
        string  $email,
        string  $officialContact,
        string  $callbackUrl = '',
        bool    $sendReminders = true,
        string  $logo = '',
        ?string $shortcode = null,
    ): Response {
        $shortcode   = $shortcode ?? $this->config->shortcode;
        $callbackUrl = $callbackUrl ?: $this->config->callbackUrl;
        $phone       = PhoneNumber::from($officialContact)->value();

        $this->validateOptIn($email, $callbackUrl);

        $payload = [
            'shortcode'       => $shortcode,
            'email'           => $email,
            'officialContact' => $phone,
            'sendReminders'   => $sendReminders ? '1' : '0',
            'callbackurl'     => $callbackUrl,
        ];

        if (!empty($logo)) {
            $payload['logo'] = $logo;
        }

        return $this->http->post(self::OPTIN_ENDPOINT, $payload);
    }

    /**
     * Send a single invoice to a customer.
     *
     * The customer receives an SMS notification and can pay directly from it.
     *
     * @throws ValidationException
     */
    public function sendInvoice(Invoice $invoice): Response
    {
        return $this->http->post(self::SINGLE_INVOICE_ENDPOINT, $invoice->toArray());
    }

    /**
     * Send multiple invoices in a single API call (up to 1,000 per batch).
     *
     * @param  list<Invoice> $invoices
     * @throws ValidationException
     */
    public function sendBulk(array $invoices): Response
    {
        if (empty($invoices)) {
            throw new ValidationException(['invoices' => 'At least one invoice is required for bulk send']);
        }

        if (count($invoices) > 1000) {
            throw new ValidationException(['invoices' => 'Maximum 1,000 invoices per bulk request']);
        }

        return $this->http->post(self::BULK_INVOICE_ENDPOINT, [
            'invoices' => array_map(fn(Invoice $inv) => $inv->toArray(), $invoices),
        ]);
    }

    /**
     * Cancel a single unpaid invoice.
     *
     * @param  string $externalReference  The externalReference used when sending the invoice
     * @throws ValidationException
     */
    public function cancelInvoice(string $externalReference): Response
    {
        if (empty($externalReference)) {
            throw new ValidationException(['external_reference' => 'External reference is required']);
        }

        return $this->http->post(self::CANCEL_SINGLE_ENDPOINT, [
            'externalReference' => $externalReference,
        ]);
    }

    /**
     * Cancel multiple invoices at once.
     *
     * @param  list<string> $externalReferences  Array of externalReference values
     * @throws ValidationException
     */
    public function cancelBulk(array $externalReferences): Response
    {
        $externalReferences = array_values(array_filter($externalReferences));

        if (empty($externalReferences)) {
            throw new ValidationException(['external_references' => 'At least one external reference is required']);
        }

        return $this->http->post(self::CANCEL_BULK_ENDPOINT, [
            'invoices' => array_map(
                fn(string $ref) => ['externalReference' => $ref],
                $externalReferences,
            ),
        ]);
    }

    /** @throws ValidationException */
    private function validateOptIn(string $email, string $callbackUrl): void
    {
        $errors = [];

        if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors['email'] = 'A valid email address is required';
        }

        if (empty($callbackUrl) || !filter_var($callbackUrl, FILTER_VALIDATE_URL)) {
            $errors['callback_url'] = 'A valid HTTPS callback URL is required';
        }

        if ($errors !== []) {
            throw new ValidationException($errors);
        }
    }
}
