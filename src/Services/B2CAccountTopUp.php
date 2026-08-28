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
use Daraja\Support\Url;

/**
 * B2C Account Top Up Service.
 *
 * Moves funds from your paybill's MMF/Working account into a B2C shortcode's
 * Utility account, so that account has enough balance to disburse via B2C.
 * This is a specialised B2B transfer: same endpoint as {@see B2BService},
 * but restricted to CommandID "BusinessPayToBulk" and identifier type 4
 * (shortcode) on both ends.
 *
 * Results delivered asynchronously to Result and Timeout URLs.
 *
 * Endpoint:
 *   POST /mpesa/b2b/v1/paymentrequest
 *
 * Required Config fields:
 *   - $config->initiatorName       — must have the Org Business Pay to Bulk API initiator role
 *   - $config->securityCredential
 *   - $config->resultUrl
 *   - $config->timeoutUrl
 *
 * @see https://developer.safaricom.co.ke/apis/B2CAccountTopUp
 */
final class B2CAccountTopUp
{
    use HasSecurityCredential;

    private const ENDPOINT = '/mpesa/b2b/v1/paymentrequest';

    public function __construct(
        private readonly Config     $config,
        private readonly HttpClient $http,
    ) {}

    /**
     * Top up a B2C shortcode's Utility account from your Working account.
     *
     * @param  string $b2cShortcode      Recipient B2C shortcode whose Utility account is funded.
     * @param  int    $amount            Amount to move, in KES.
     * @param  string $accountReference  Reference for the transfer (max 12 chars).
     * @param  string $requester         Optional MSISDN of the consumer being paid on behalf of.
     * @param  string $remarks           Free text remarks (max 100 chars).
     * @throws ValidationException
     * @return Response
     */
    public function topUp(
        string $b2cShortcode,
        int    $amount,
        string $accountReference,
        string $requester = '',
        string $remarks = 'B2C Account Top Up',
        string $resultUrl = '',
        string $timeoutUrl = '',
    ): Response {
        $resultUrl  = $resultUrl ?: $this->config->resultUrl;
        $timeoutUrl = $timeoutUrl ?: $this->config->timeoutUrl;

        $this->validateOperatorConfig();
        $this->validateParams($b2cShortcode, $amount, $accountReference, $resultUrl, $timeoutUrl);

        $payload = [
            'Initiator'              => $this->config->initiatorName,
            'SecurityCredential'     => $this->config->securityCredential,
            'CommandID'              => CommandId::BusinessPayToBulk->value,
            'SenderIdentifierType'   => IdentifierType::Shortcode->value,
            'RecieverIdentifierType' => IdentifierType::Shortcode->value,
            'Amount'                 => $amount,
            'PartyA'                 => $this->config->shortcode,
            'PartyB'                 => $b2cShortcode,
            'AccountReference'       => substr($accountReference, 0, 12),
            'Remarks'                => substr($remarks, 0, 100),
            'QueueTimeOutURL'        => $timeoutUrl,
            'ResultURL'              => $resultUrl,
        ];

        if ($requester !== '') {
            $payload['Requester'] = $requester;
        }

        return $this->http->post(self::ENDPOINT, $payload);
    }

    /** @throws ValidationException */
    private function validateOperatorConfig(): void
    {
        $errors = [];

        if (empty($this->config->initiatorName)) {
            $errors['initiator_name'] = 'initiatorName is required in Config for B2C Account Top Up';
        }

        if (empty($this->config->securityCredential)) {
            $errors['security_credential'] = 'securityCredential is required in Config for B2C Account Top Up';
        }

        if ($errors !== []) {
            throw new ValidationException($errors, 'B2C Account Top Up operator configuration is incomplete');
        }
    }

    /** @throws ValidationException */
    private function validateParams(
        string $b2cShortcode,
        int    $amount,
        string $accountReference,
        string $resultUrl,
        string $timeoutUrl,
    ): void {
        $errors = [];

        if (empty($b2cShortcode)) {
            $errors['b2c_shortcode'] = 'Recipient B2C shortcode is required';
        }

        if ($amount < 1) {
            $errors['amount'] = 'Amount must be at least 1 KES';
        }

        if (empty($accountReference)) {
            $errors['account_reference'] = 'Account reference is required';
        }

        if (empty($resultUrl) || !Url::isHttps($resultUrl)) {
            $errors['result_url'] = 'A valid HTTPS result URL is required';
        }

        if (empty($timeoutUrl) || !Url::isHttps($timeoutUrl)) {
            $errors['timeout_url'] = 'A valid HTTPS timeout URL is required';
        }

        if ($errors !== []) {
            throw new ValidationException($errors);
        }
    }
}
