<?php

declare(strict_types=1);

namespace Daraja\Services;

use Daraja\Concerns\HasSecurityCredential;
use Daraja\Config;
use Daraja\Enums\CommandId;
use Daraja\Exceptions\ValidationException;
use Daraja\Http\HttpClient;
use Daraja\Http\Response;
use Daraja\ValueObjects\PhoneNumber;

/**
 * Business To Pochi (B2Pochi) Service.
 *
 * A specialised Business-to-Customer payment that pays into a customer's
 * "Pochi La Biashara" business wallet (a micro-SME M-Pesa wallet) rather
 * than their personal M-Pesa account. Requires a Bulk Disbursement Account
 * or a "one account" (paybill/till that can both receive and disburse).
 *
 * Results delivered asynchronously to the ResultURL.
 *
 * Endpoint:
 *   POST /mpesa/b2pochi/v1/paymentrequest
 *
 * Required Config fields:
 *   - $config->initiatorName       — must have the ORG B2C API initiator role
 *   - $config->securityCredential
 *   - $config->resultUrl
 *   - $config->timeoutUrl
 *
 * Callback payload: identical shape to the standard B2C result — parse it
 * with {@see \Daraja\Webhooks\Payloads\B2CResult}.
 *
 * @see https://developer.safaricom.co.ke/apis/BusinessToPochi
 */
final class BusinessToPochi
{
    use HasSecurityCredential;

    private const ENDPOINT = '/mpesa/b2pochi/v1/paymentrequest';

    public function __construct(
        private readonly Config     $config,
        private readonly HttpClient $http,
    ) {}

    /**
     * Pay a customer's Pochi La Biashara wallet.
     *
     * @param  string|PhoneNumber $phone                    Recipient's M-Pesa registered phone number.
     * @param  int                $amount                   Amount in KES (min 10, max 250,000 per transaction).
     * @param  string             $remarks                  Free text remarks (2–100 chars).
     * @param  string             $occasion                 Optional occasion note (1–100 chars).
     * @param  string             $originatorConversationId Unique idempotency key for this request. A UUID v4 is generated when omitted.
     * @throws ValidationException
     * @return Response
     */
    public function pay(
        string|PhoneNumber $phone,
        int                $amount,
        string             $remarks = 'Pochi Payment',
        string             $occasion = '',
        string             $originatorConversationId = '',
        string             $resultUrl = '',
        string             $timeoutUrl = '',
    ): Response {
        $phone      = $phone instanceof PhoneNumber ? $phone : PhoneNumber::from((string) $phone);
        $resultUrl  = $resultUrl ?: $this->config->resultUrl;
        $timeoutUrl = $timeoutUrl ?: $this->config->timeoutUrl;
        $originatorConversationId = $originatorConversationId !== ''
            ? $originatorConversationId
            : $this->generateOriginatorConversationId();

        $this->validateOperatorConfig();
        $this->validateParams($amount, $remarks, $resultUrl, $timeoutUrl);

        return $this->http->post(self::ENDPOINT, [
            'OriginatorConversationID' => $originatorConversationId,
            'InitiatorName'            => $this->config->initiatorName,
            'SecurityCredential'       => $this->config->securityCredential,
            'CommandID'                => CommandId::BusinessPayToPochi->value,
            'Amount'                   => $amount,
            'PartyA'                   => $this->config->shortcode,
            'PartyB'                   => $phone->value(),
            'Remarks'                  => substr($remarks, 0, 100),
            'QueueTimeOutURL'          => $timeoutUrl,
            'ResultURL'                => $resultUrl,
            'Occassion'                => substr($occasion, 0, 100),
        ]);
    }

    /** @throws ValidationException */
    private function validateOperatorConfig(): void
    {
        $errors = [];

        if (empty($this->config->initiatorName)) {
            $errors['initiator_name'] = 'initiatorName is required in Config for Business To Pochi payments';
        }

        if (empty($this->config->securityCredential)) {
            $errors['security_credential'] = 'securityCredential is required in Config for Business To Pochi payments';
        }

        if ($errors !== []) {
            throw new ValidationException($errors, 'Business To Pochi operator configuration is incomplete');
        }
    }

    /** @throws ValidationException */
    private function validateParams(
        int    $amount,
        string $remarks,
        string $resultUrl,
        string $timeoutUrl,
    ): void {
        $errors = [];

        if ($amount < 10) {
            $errors['amount'] = 'Amount must be at least 10 KES (Pochi minimum)';
        }

        if ($amount > 250000) {
            $errors['amount'] = 'Amount must not exceed 250,000 KES per transaction (Pochi maximum)';
        }

        if (strlen($remarks) < 2) {
            $errors['remarks'] = 'Remarks must be at least 2 characters';
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

    private function generateOriginatorConversationId(): string
    {
        $data    = random_bytes(16);
        $data[6] = chr(ord($data[6]) & 0x0f | 0x40);
        $data[8] = chr(ord($data[8]) & 0x3f | 0x80);

        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
    }
}
