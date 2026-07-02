<?php

declare(strict_types=1);

namespace Daraja\Services;

use Daraja\Concerns\HasSecurityCredential;
use Daraja\Config;
use Daraja\Enums\CommandId;
use Daraja\Enums\IdentifierType;
use Daraja\Exceptions\ValidationException;
use Daraja\Http\HttpClient;
use Daraja\Http\Response;

/**
 * Tax Remittance Service.
 *
 * Enables businesses to remit taxes directly to the Kenya Revenue Authority (KRA)
 * via M-Pesa. Uses the B2B API under the hood with CommandID PayTaxToKRA
 * and KRA's shortcode 572572.
 *
 * Endpoint:
 *   POST /mpesa/b2b/v1/remittax
 *
 * Required Config fields:
 *   - $config->initiatorName
 *   - $config->securityCredential
 *   - $config->resultUrl
 *   - $config->timeoutUrl
 *
 * @see https://developer.safaricom.co.ke/Documentation#tax-remittance
 */
final class TaxRemittance
{
    use HasSecurityCredential;

    private const ENDPOINT     = '/mpesa/b2b/v1/remittax';
    private const KRA_SHORTCODE = '572572';

    public function __construct(
        private readonly Config     $config,
        private readonly HttpClient $http,
    ) {}

    /**
     * Remit tax to KRA.
     *
     * @param  int    $amount          Tax amount in KES
     * @param  string $accountReference Your PRN (Payment Registration Number) from iTax
     * @param  string $remarks          Optional remarks (max 100 chars)
     * @param  string $resultUrl        Override result URL
     * @param  string $timeoutUrl       Override timeout URL
     * @throws ValidationException
     * @return Response
     */
    public function remit(
        int    $amount,
        string $accountReference,
        string $remarks = 'Tax remittance',
        string $resultUrl = '',
        string $timeoutUrl = '',
    ): Response {
        $resultUrl  = $resultUrl  ?: $this->config->resultUrl;
        $timeoutUrl = $timeoutUrl ?: $this->config->timeoutUrl;

        $this->validateOperatorConfig();
        $this->validateParams($amount, $accountReference, $resultUrl, $timeoutUrl);

        return $this->http->post(self::ENDPOINT, [
            'Initiator'              => $this->config->initiatorName,
            'SecurityCredential'     => $this->config->securityCredential,
            'CommandID'              => CommandId::PayTaxToKRA->value,
            'SenderIdentifierType'   => IdentifierType::Shortcode->value,
            'ReceiverIdentifierType' => IdentifierType::Shortcode->value,
            'Amount'                 => $amount,
            'PartyA'                 => $this->config->shortcode,
            'PartyB'                 => self::KRA_SHORTCODE,
            'AccountReference'       => substr($accountReference, 0, 20),
            'Remarks'                => substr($remarks, 0, 100),
            'QueueTimeOutURL'        => $timeoutUrl,
            'ResultURL'              => $resultUrl,
        ]);
    }

    /** @throws ValidationException */
    private function validateOperatorConfig(): void
    {
        $errors = [];

        if (empty($this->config->initiatorName)) {
            $errors['initiator_name'] = 'initiatorName is required for Tax Remittance';
        }

        if (empty($this->config->securityCredential)) {
            $errors['security_credential'] = 'securityCredential is required for Tax Remittance';
        }

        if ($errors !== []) {
            throw new ValidationException($errors, 'Operator configuration is incomplete');
        }
    }

    /** @throws ValidationException */
    private function validateParams(
        int    $amount,
        string $accountReference,
        string $resultUrl,
        string $timeoutUrl,
    ): void {
        $errors = [];

        if ($amount < 1) {
            $errors['amount'] = 'Tax amount must be at least 1 KES';
        }

        if (empty($accountReference)) {
            $errors['account_reference'] = 'PRN (Payment Registration Number) is required';
        }

        if (empty($resultUrl) || !filter_var($resultUrl, FILTER_VALIDATE_URL)) {
            $errors['result_url'] = 'A valid HTTPS result URL is required';
        }

        if (empty($timeoutUrl) || !filter_var($timeoutUrl, FILTER_VALIDATE_URL)) {
            $errors['timeout_url'] = 'A valid HTTPS timeout URL is required';
        }

        if ($errors !== []) {
            throw new ValidationException($errors);
        }
    }
}
