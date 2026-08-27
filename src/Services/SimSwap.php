<?php

declare(strict_types=1);

namespace Daraja\Services;

use Daraja\Exceptions\ValidationException;
use Daraja\Http\HttpClient;
use Daraja\Http\Response;
use Daraja\ValueObjects\PhoneNumber;

/**
 * Swap (SIM Swap) Service.
 *
 * Queries the last date a customer's SIM card was swapped — a fraud/risk
 * signal for mobile and internet banking due diligence (e.g. before
 * clearing a cheque, or before a high-risk transfer). If the SIM was
 * swapped more than 3 months ago, the API returns a default date of
 * "01-01-1900 00:00".
 *
 * ⚠️ This is a **commercial API**: onboarding requires a signed commercial
 * agreement with Safaricom (email apisupport@safaricom.co.ke or your
 * account manager). It will not work against a plain sandbox app without
 * that agreement. Pricing: KES 50,000 connection fee, first 200,000
 * requests free, then KES 1/request.
 *
 * Endpoint:
 *   POST /imsi/v2/checkATI
 *
 * @see https://developer.safaricom.co.ke/apis/Swap
 */
final class SimSwap
{
    private const ENDPOINT = '/imsi/v2/checkATI';

    public function __construct(
        private readonly HttpClient $http,
    ) {}

    /**
     * Query the last SIM swap date for a customer number.
     *
     * @param  string|PhoneNumber $customerNumber  Customer's M-Pesa registered phone number.
     * @throws ValidationException
     * @return Response  ->getString('lastSwapDate') e.g. "01-01-1900 00:00"; ->getString('responseCode') "200" on success.
     */
    public function checkLastSwapDate(string|PhoneNumber $customerNumber): Response
    {
        $customerNumber = $customerNumber instanceof PhoneNumber
            ? $customerNumber
            : PhoneNumber::from((string) $customerNumber);

        return $this->http->post(self::ENDPOINT, [
            'customerNumber' => $customerNumber->value(),
        ]);
    }
}
