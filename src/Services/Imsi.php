<?php

declare(strict_types=1);

namespace Daraja\Services;

use Daraja\Exceptions\ValidationException;
use Daraja\Http\HttpClient;
use Daraja\Http\Response;
use Daraja\ValueObjects\PhoneNumber;

/**
 * IMSI Service.
 *
 * A fraud/risk-check API returning three signals for a Safaricom number:
 * a hashed IMSI, the network registration date, and the last SIM swap
 * date. Useful for due diligence on mobile/internet banking transactions
 * and for onboarding checks.
 *
 * ⚠️ This is a **commercial API**: onboarding requires a signed commercial
 * agreement with Safaricom (email apisupport@safaricom.co.ke or your
 * account manager). Pricing: KES 20 per call.
 *
 * Endpoint:
 *   POST /imsi/v1/checkATI
 *
 * @see https://developer.safaricom.co.ke/apis/IMSI
 */
final class Imsi
{
    private const ENDPOINT = '/imsi/v1/checkATI';

    public function __construct(
        private readonly HttpClient $http,
    ) {}

    /**
     * Query the hashed IMSI, registration date, and last swap date for a customer number.
     *
     * @param  string|PhoneNumber $customerNumber  Customer's M-Pesa registered phone number.
     * @throws ValidationException
     * @return Response  ->getString('imsi'), ->getString('lastSwapDate'), ->getString('msisdnRegistrationDate')
     */
    public function check(string|PhoneNumber $customerNumber): Response
    {
        $customerNumber = $customerNumber instanceof PhoneNumber
            ? $customerNumber
            : PhoneNumber::from((string) $customerNumber);

        return $this->http->post(self::ENDPOINT, [
            'customerNumber' => $customerNumber->value(),
        ]);
    }
}
