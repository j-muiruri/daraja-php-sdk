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
 * Transaction Status Service.
 *
 * Queries the status of any M-Pesa transaction using the originator
 * conversation ID or the M-Pesa receipt number (transaction ID).
 *
 * Useful for reconciliation when callbacks were missed or delayed.
 *
 * Endpoint:
 *   POST /mpesa/transactionstatus/v1/query
 *
 * Required Config fields:
 *   - $config->initiatorName
 *   - $config->securityCredential
 *   - $config->resultUrl
 *   - $config->timeoutUrl
 */
final class TransactionStatus
{
    use HasSecurityCredential;

    private const ENDPOINT = '/mpesa/transactionstatus/v1/query';

    public function __construct(
        private readonly Config     $config,
        private readonly HttpClient $http,
    ) {}

    /**
     * Query the status of a transaction by M-Pesa receipt number.
     *
     * @param  string          $transactionId    M-Pesa receipt number (e.g. QHT3XXXXXXXXXXX)
     * @param  IdentifierType  $identifierType   Type of the Party (Shortcode, MSISDN, TillNumber)
     * @param  string          $remarks          Optional remarks (max 100 chars)
     * @param  string          $occasion         Optional occasion (max 100 chars)
     * @param  string          $resultUrl        Override result URL
     * @param  string          $timeoutUrl       Override timeout URL
     * @throws ValidationException
     * @return Response
     */
    public function query(
        string         $transactionId,
        IdentifierType $identifierType = IdentifierType::Shortcode,
        string         $remarks = 'Transaction status query',
        string         $occasion = '',
        string         $resultUrl = '',
        string         $timeoutUrl = '',
    ): Response {
        $resultUrl  = $resultUrl ?: $this->config->resultUrl;
        $timeoutUrl = $timeoutUrl ?: $this->config->timeoutUrl;

        $this->validateOperatorConfig();
        $this->validateQueryParams($transactionId, $resultUrl, $timeoutUrl);

        return $this->http->post(self::ENDPOINT, [
            'Initiator'          => $this->config->initiatorName,
            'SecurityCredential' => $this->config->securityCredential,
            'CommandID'          => CommandId::TransactionStatusQuery->value,
            'TransactionID'      => $transactionId,
            'PartyA'             => $this->config->shortcode,
            'IdentifierType'     => $identifierType->value,
            'ResultURL'          => $resultUrl,
            'QueueTimeOutURL'    => $timeoutUrl,
            'Remarks'            => substr($remarks, 0, 100),
            'Occasion'           => substr($occasion, 0, 100),
        ]);
    }

    /** @throws ValidationException */
    private function validateOperatorConfig(): void
    {
        $errors = [];

        if (empty($this->config->initiatorName)) {
            $errors['initiator_name'] = 'initiatorName is required for Transaction Status queries';
        }

        if (empty($this->config->securityCredential)) {
            $errors['security_credential'] = 'securityCredential is required for Transaction Status queries';
        }

        if ($errors !== []) {
            throw new ValidationException($errors, 'Operator configuration is incomplete');
        }
    }

    /** @throws ValidationException */
    private function validateQueryParams(
        string $transactionId,
        string $resultUrl,
        string $timeoutUrl,
    ): void {
        $errors = [];

        if (empty($transactionId)) {
            $errors['transaction_id'] = 'Transaction ID (M-Pesa receipt number) is required';
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
