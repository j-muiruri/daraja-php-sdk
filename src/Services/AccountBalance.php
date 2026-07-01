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
 * Account Balance Service.
 *
 * Queries the balance of an M-Pesa Business shortcode (Paybill or Till).
 * Result is delivered asynchronously via the Result and Timeout URLs.
 *
 * Endpoint:
 *   POST /mpesa/accountbalance/v1/query
 *
 * Required Config fields:
 *   - $config->initiatorName
 *   - $config->securityCredential
 *   - $config->resultUrl
 *   - $config->timeoutUrl
 */
final class AccountBalance
{
    use HasSecurityCredential;

    private const ENDPOINT = '/mpesa/accountbalance/v1/query';

    public function __construct(
        private readonly Config     $config,
        private readonly HttpClient $http,
    ) {}

    /**
     * Query the balance of the configured business shortcode.
     *
     * @param  IdentifierType $identifierType  Shortcode (4), TillNumber (2), or MSISDN (1)
     * @param  string         $remarks         Optional remarks
     * @param  string         $resultUrl       Override result URL
     * @param  string         $timeoutUrl      Override timeout URL
     * @throws ValidationException
     */
    public function query(
        IdentifierType $identifierType = IdentifierType::Shortcode,
        string         $remarks = 'Account balance query',
        string         $resultUrl = '',
        string         $timeoutUrl = '',
    ): Response {
        $resultUrl  = $resultUrl ?: $this->config->resultUrl;
        $timeoutUrl = $timeoutUrl ?: $this->config->timeoutUrl;

        $this->validateOperatorConfig();
        $this->validateUrls($resultUrl, $timeoutUrl);

        return $this->http->post(self::ENDPOINT, [
            'Initiator'          => $this->config->initiatorName,
            'SecurityCredential' => $this->config->securityCredential,
            'CommandID'          => CommandId::AccountBalance->value,
            'PartyA'             => $this->config->shortcode,
            'IdentifierType'     => $identifierType->value,
            'Remarks'            => substr($remarks, 0, 100),
            'QueueTimeOutURL'    => $timeoutUrl,
            'ResultURL'          => $resultUrl,
        ]);
    }

    /** @throws ValidationException */
    private function validateOperatorConfig(): void
    {
        $errors = [];

        if (empty($this->config->initiatorName)) {
            $errors['initiator_name'] = 'initiatorName is required for Account Balance queries';
        }

        if (empty($this->config->securityCredential)) {
            $errors['security_credential'] = 'securityCredential is required for Account Balance queries';
        }

        if ($errors !== []) {
            throw new ValidationException($errors, 'Operator configuration is incomplete');
        }
    }

    /** @throws ValidationException */
    private function validateUrls(string $resultUrl, string $timeoutUrl): void
    {
        $errors = [];

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
