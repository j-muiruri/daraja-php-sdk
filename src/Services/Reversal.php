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
 * Transaction Reversal Service.
 *
 * Reverses a completed M-Pesa transaction.
 * Only the organisation that received the funds can initiate a reversal.
 * Reversals are asynchronous — results are delivered via Result/Timeout URLs.
 *
 * Endpoint:
 *   POST /mpesa/reversal/v1/request
 *
 * Required Config fields:
 *   - $config->initiatorName
 *   - $config->securityCredential
 *   - $config->resultUrl
 *   - $config->timeoutUrl
 */
final class Reversal
{
    use HasSecurityCredential;

    private const ENDPOINT = '/mpesa/reversal/v1/request';

    public function __construct(
        private readonly Config     $config,
        private readonly HttpClient $http,
    ) {}

    /**
     * Reverse a completed M-Pesa transaction.
     *
     * @param  string          $transactionId    M-Pesa receipt number to reverse (e.g. QHT3XXXXXXXXXXX)
     * @param  int             $amount           Original transaction amount in KES
     * @param  string          $receiverShortcode Shortcode that received the funds (usually your own)
     * @param  IdentifierType  $receiverIdentifier  Identifier type of receiver (default: Shortcode)
     * @param  string          $remarks          Reason for reversal (max 100 chars)
     * @param  string          $occasion         Optional occasion note (max 100 chars)
     * @param  string          $resultUrl        Override result URL
     * @param  string          $timeoutUrl       Override timeout URL
     * @throws ValidationException
     * @return Response
     */
    public function reverse(
        string         $transactionId,
        int            $amount,
        string         $receiverShortcode = '',
        IdentifierType $receiverIdentifier = IdentifierType::Shortcode,
        string         $remarks = 'Reversal',
        string         $occasion = '',
        string         $resultUrl = '',
        string         $timeoutUrl = '',
    ): Response {
        $receiverShortcode = $receiverShortcode ?: $this->config->shortcode;
        $resultUrl         = $resultUrl ?: $this->config->resultUrl;
        $timeoutUrl        = $timeoutUrl ?: $this->config->timeoutUrl;

        $this->validateOperatorConfig();
        $this->validateParams($transactionId, $amount, $resultUrl, $timeoutUrl);

        return $this->http->post(self::ENDPOINT, [
            'Initiator'              => $this->config->initiatorName,
            'SecurityCredential'     => $this->config->securityCredential,
            'CommandID'              => CommandId::TransactionReversal->value,
            'TransactionID'          => $transactionId,
            'Amount'                 => $amount,
            'ReceiverParty'          => $receiverShortcode,
            'ReceiverIdentifierType' => $receiverIdentifier->value,
            'QueueTimeOutURL'        => $timeoutUrl,
            'ResultURL'              => $resultUrl,
            'Remarks'                => substr($remarks, 0, 100),
            'Occasion'               => substr($occasion, 0, 100),
        ]);
    }

    /** @throws ValidationException */
    private function validateOperatorConfig(): void
    {
        $errors = [];

        if (empty($this->config->initiatorName)) {
            $errors['initiator_name'] = 'initiatorName is required for Reversals';
        }

        if (empty($this->config->securityCredential)) {
            $errors['security_credential'] = 'securityCredential is required for Reversals';
        }

        if ($errors !== []) {
            throw new ValidationException($errors, 'Operator configuration is incomplete');
        }
    }

    /** @throws ValidationException */
    private function validateParams(
        string $transactionId,
        int    $amount,
        string $resultUrl,
        string $timeoutUrl,
    ): void {
        $errors = [];

        if (empty($transactionId)) {
            $errors['transaction_id'] = 'Transaction ID (M-Pesa receipt number) is required';
        }

        if ($amount < 1) {
            $errors['amount'] = 'Amount must be at least 1 KES';
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
