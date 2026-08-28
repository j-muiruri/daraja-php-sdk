<?php

declare(strict_types=1);

namespace Daraja\Services;

use Daraja\Config;
use Daraja\Exceptions\ValidationException;
use Daraja\Http\HttpClient;
use Daraja\Http\Response;
use Daraja\Support\Url;

/**
 * Pull Transactions Service.
 *
 * A reconciliation tool for recovering C2B transactions that never reached
 * your registered callback URLs (e.g. due to downtime). Two-step flow:
 *
 *   1. {@see self::register()} — one-time registration of your shortcode
 *      for pulling (production shortcodes only).
 *   2. {@see self::query()}    — fetch C2B transactions for a time window
 *      (max 48 hours of history), paginated via OffSetValue.
 *
 * Endpoints:
 *   POST /pulltransactions/v1/register
 *   POST /pulltransactions/v1/query
 *
 * ⚠️ Assumption flagged for review: Safaricom's own documentation is
 * internally inconsistent about the query step's HTTP method — prose says
 * "Method is ... GET for Pull transaction", but the same page's request
 * example shows a JSON request body, which a plain GET cannot carry. This
 * SDK implements query() as POST with a JSON body (consistent with every
 * other Daraja endpoint and with the documented sample body). Verify
 * against your sandbox app before relying on this in production, and open
 * an issue if Safaricom confirms GET is required.
 *
 * @see https://developer.safaricom.co.ke/apis/PullTransaction
 */
final class PullTransaction
{
    private const REGISTER_ENDPOINT = '/pulltransactions/v1/register';
    private const QUERY_ENDPOINT    = '/pulltransactions/v1/query';

    public function __construct(
        private readonly Config     $config,
        private readonly HttpClient $http,
    ) {}

    /**
     * One-time registration of a shortcode for transaction pulling.
     *
     * @param  string $shortCode        Organization shortcode used during Go-Live.
     * @param  string $nominatedNumber  Safaricom MSISDN associated with the organization account.
     * @param  string $callbackUrl      HTTPS endpoint for the registration confirmation. Falls back to Config::$callbackUrl.
     * @throws ValidationException
     * @return Response
     */
    public function register(
        string $shortCode,
        string $nominatedNumber,
        string $callbackUrl = '',
    ): Response {
        $shortCode   = $shortCode !== '' ? $shortCode : $this->config->shortcode;
        $callbackUrl = $callbackUrl !== '' ? $callbackUrl : $this->config->callbackUrl;

        $this->validateRegister($shortCode, $nominatedNumber, $callbackUrl);

        return $this->http->post(self::REGISTER_ENDPOINT, [
            'ShortCode'       => $shortCode,
            'RequestType'     => 'Pull',
            'NominatedNumber' => $nominatedNumber,
            'CallBackURL'     => $callbackUrl,
        ]);
    }

    /**
     * Query C2B transactions for a shortcode within a time window.
     *
     * @param  \DateTimeInterface $startDate   Start of the reconciliation window (max 48h in the past).
     * @param  \DateTimeInterface $endDate     End of the reconciliation window.
     * @param  string             $shortCode   Paybill/Till number to query. Defaults to Config::$shortcode.
     * @param  int                $offset      Pagination offset (0-indexed).
     * @throws ValidationException
     * @return Response
     */
    public function query(
        \DateTimeInterface $startDate,
        \DateTimeInterface $endDate,
        string             $shortCode = '',
        int                $offset = 0,
    ): Response {
        $shortCode = $shortCode !== '' ? $shortCode : $this->config->shortcode;

        $this->validateQuery($shortCode, $startDate, $endDate, $offset);

        return $this->http->post(self::QUERY_ENDPOINT, [
            'ShortCode'  => $shortCode,
            'StartDate'  => $startDate->format('Y-m-d H:i:s'),
            'EndDate'    => $endDate->format('Y-m-d H:i:s'),
            'OffSetValue' => (string) $offset,
        ]);
    }

    /** @throws ValidationException */
    private function validateRegister(string $shortCode, string $nominatedNumber, string $callbackUrl): void
    {
        $errors = [];

        if (empty($shortCode)) {
            $errors['short_code'] = 'Shortcode is required';
        }

        if (empty($nominatedNumber)) {
            $errors['nominated_number'] = 'Nominated number (organization MSISDN) is required';
        }

        if (empty($callbackUrl) || !Url::isHttps($callbackUrl)) {
            $errors['callback_url'] = 'A valid HTTPS callback URL is required';
        }

        if ($errors !== []) {
            throw new ValidationException($errors);
        }
    }

    /** @throws ValidationException */
    private function validateQuery(
        string             $shortCode,
        \DateTimeInterface $startDate,
        \DateTimeInterface $endDate,
        int                $offset,
    ): void {
        $errors = [];

        if (empty($shortCode)) {
            $errors['short_code'] = 'Shortcode is required';
        }

        if ($endDate < $startDate) {
            $errors['end_date'] = 'End date must not be before the start date';
        }

        if ($offset < 0) {
            $errors['offset'] = 'Offset must not be negative';
        }

        if ($errors !== []) {
            throw new ValidationException($errors);
        }
    }
}
