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
 * Business to Business (B2B) Service.
 *
 * Enables a business to send funds to another business.
 * Common use cases: paying suppliers, inter-company transfers, MMF sweeps.
 *
 * Results delivered asynchronously to Result and Timeout URLs.
 *
 * Endpoint:
 *   POST /mpesa/b2b/v1/paymentrequest
 *
 * Required Config fields:
 *   - $config->initiatorName
 *   - $config->securityCredential
 *   - $config->resultUrl
 *   - $config->timeoutUrl
 */
final class B2BService
{
    use HasSecurityCredential;

    private const ENDPOINT = '/mpesa/b2b/v1/paymentrequest';

    public function __construct(
        private readonly Config     $config,
        private readonly HttpClient $http,
    ) {}

    /**
     * Pay a supplier via their Paybill number.
     *
     * @param  string $receiverShortcode   Supplier's paybill shortcode
     * @param  int    $amount              Amount in KES
     * @param  string $accountReference    Account number at the supplier's paybill
     * @param  string $remarks             Transaction remarks
     * @throws ValidationException
     * @return Response
     */
    public function payBill(
        string $receiverShortcode,
        int    $amount,
        string $accountReference,
        string $remarks = 'B2B PayBill',
        string $resultUrl = '',
        string $timeoutUrl = '',
    ): Response {
        return $this->pay(
            receiverShortcode:    $receiverShortcode,
            amount:               $amount,
            commandId:            CommandId::BusinessPayBill,
            receiverIdentifier:   IdentifierType::Shortcode,
            accountReference:     $accountReference,
            remarks:              $remarks,
            resultUrl:            $resultUrl,
            timeoutUrl:           $timeoutUrl,
        );
    }

    /**
     * Buy goods from a merchant till number.
     *
     * @param  string $tillNumber  Merchant's Buy Goods till number
     * @param  int    $amount      Amount in KES
     * @param  string $remarks     Transaction remarks
     * @throws ValidationException
     * @return Response
     */
    public function buyGoods(
        string $tillNumber,
        int    $amount,
        string $remarks = 'B2B Buy Goods',
        string $resultUrl = '',
        string $timeoutUrl = '',
    ): Response {
        return $this->pay(
            receiverShortcode:    $tillNumber,
            amount:               $amount,
            commandId:            CommandId::BusinessBuyGoods,
            receiverIdentifier:   IdentifierType::TillNumber,
            accountReference:     $tillNumber,
            remarks:              $remarks,
            resultUrl:            $resultUrl,
            timeoutUrl:           $timeoutUrl,
        );
    }

    /**
     * Low-level B2B payment.
     *
     * @throws ValidationException
     * @return Response
     */
    public function pay(
        string         $receiverShortcode,
        int            $amount,
        CommandId      $commandId,
        IdentifierType $receiverIdentifier = IdentifierType::Shortcode,
        string         $accountReference = '',
        string         $remarks = 'B2B Payment',
        string         $resultUrl = '',
        string         $timeoutUrl = '',
    ): Response {
        $resultUrl  = $resultUrl ?: $this->config->resultUrl;
        $timeoutUrl = $timeoutUrl ?: $this->config->timeoutUrl;

        $this->validateOperatorConfig();
        $this->validatePayParams($amount, $receiverShortcode, $resultUrl, $timeoutUrl);

        return $this->http->post(self::ENDPOINT, [
            'Initiator'              => $this->config->initiatorName,
            'SecurityCredential'     => $this->config->securityCredential,
            'CommandID'              => $commandId->value,
            'SenderIdentifierType'   => IdentifierType::Shortcode->value,
            'RecieverIdentifierType' => $receiverIdentifier->value,
            'Amount'                 => $amount,
            'PartyA'                 => $this->config->shortcode,
            'PartyB'                 => $receiverShortcode,
            'AccountReference'       => substr($accountReference, 0, 12),
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
            $errors['initiator_name'] = 'initiatorName is required in Config for B2B payments';
        }

        if (empty($this->config->securityCredential)) {
            $errors['security_credential'] = 'securityCredential is required in Config for B2B payments';
        }

        if ($errors !== []) {
            throw new ValidationException($errors, 'B2B operator configuration is incomplete');
        }
    }

    /** @throws ValidationException */
    private function validatePayParams(
        int    $amount,
        string $receiverShortcode,
        string $resultUrl,
        string $timeoutUrl,
    ): void {
        $errors = [];

        if ($amount < 1) {
            $errors['amount'] = 'Amount must be at least 1 KES';
        }

        if (empty($receiverShortcode)) {
            $errors['receiver_shortcode'] = 'Receiver shortcode is required';
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
