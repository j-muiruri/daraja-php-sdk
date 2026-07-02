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
 * Business to Customer (B2C) Service.
 *
 * Used for bulk payouts: salaries, promotions, withdrawals, insurance payouts, etc.
 *
 * Results are delivered asynchronously to your Result and Timeout URLs.
 *
 * Endpoint:
 *   POST /mpesa/b2c/v3/paymentrequest
 *
 * Required Config fields:
 *   - $config->initiatorName       — API operator username from Daraja portal
 *   - $config->securityCredential  — Encrypted initiator password (see HasSecurityCredential)
 *   - $config->resultUrl           — HTTPS URL for successful result callbacks
 *   - $config->timeoutUrl          — HTTPS URL for timed-out request callbacks
 */
final class B2CService
{
    use HasSecurityCredential;

    private const ENDPOINT = '/mpesa/b2c/v3/paymentrequest';

    public function __construct(
        private readonly Config     $config,
        private readonly HttpClient $http,
    ) {}

    /**
     * Send a salary payment to an employee.
     *
     * @param  string|PhoneNumber $phone       Recipient phone number
     * @param  int                $amount      Amount in KES
     * @param  string             $remarks     Free text remarks (max 100 chars)
     * @param  string             $occasion    Optional occasion note (max 100 chars)
     * @throws ValidationException
     * @return Response
     */
    public function sendSalary(
        string|PhoneNumber $phone,
        int                $amount,
        string             $remarks = 'Salary Payment',
        string             $occasion = '',
        string             $resultUrl = '',
        string             $timeoutUrl = '',
    ): Response {
        return $this->pay(
            phone:      $phone,
            amount:     $amount,
            commandId:  CommandId::SalaryPayment,
            remarks:    $remarks,
            occasion:   $occasion,
            resultUrl:  $resultUrl,
            timeoutUrl: $timeoutUrl,
        );
    }

    /**
     * Send a general business payment (e.g. bank-to-mobile transfer).
     *
     * @throws ValidationException
     * @return Response
     */
    public function sendBusinessPayment(
        string|PhoneNumber $phone,
        int                $amount,
        string             $remarks = 'Business Payment',
        string             $occasion = '',
        string             $resultUrl = '',
        string             $timeoutUrl = '',
    ): Response {
        return $this->pay(
            phone:      $phone,
            amount:     $amount,
            commandId:  CommandId::BusinessPayment,
            remarks:    $remarks,
            occasion:   $occasion,
            resultUrl:  $resultUrl,
            timeoutUrl: $timeoutUrl,
        );
    }

    /**
     * Send a promotion payment (e.g. betting winnings, cashback).
     *
     * @throws ValidationException
     * @return Response
     */
    public function sendPromotion(
        string|PhoneNumber $phone,
        int                $amount,
        string             $remarks = 'Promotion Payment',
        string             $occasion = '',
        string             $resultUrl = '',
        string             $timeoutUrl = '',
    ): Response {
        return $this->pay(
            phone:      $phone,
            amount:     $amount,
            commandId:  CommandId::PromotionPayment,
            remarks:    $remarks,
            occasion:   $occasion,
            resultUrl:  $resultUrl,
            timeoutUrl: $timeoutUrl,
        );
    }

    /**
     * Low-level B2C payment. Use the specific helpers above when possible.
     *
     * @throws ValidationException
     * @return Response
     */
    public function pay(
        string|PhoneNumber $phone,
        int                $amount,
        CommandId          $commandId,
        string             $remarks = 'Payment',
        string             $occasion = '',
        string             $resultUrl = '',
        string             $timeoutUrl = '',
    ): Response {
        $phone      = $phone instanceof PhoneNumber ? $phone : PhoneNumber::from((string) $phone);
        $resultUrl  = $resultUrl ?: $this->config->resultUrl;
        $timeoutUrl = $timeoutUrl ?: $this->config->timeoutUrl;

        $this->validateOperatorConfig();
        $this->validatePayParams($amount, $commandId, $remarks, $resultUrl, $timeoutUrl);

        return $this->http->post(self::ENDPOINT, [
            'InitiatorName'      => $this->config->initiatorName,
            'SecurityCredential' => $this->config->securityCredential,
            'CommandID'          => $commandId->value,
            'Amount'             => $amount,
            'PartyA'             => $this->config->shortcode,
            'PartyB'             => $phone->value(),
            'Remarks'            => substr($remarks, 0, 100),
            'QueueTimeOutURL'    => $timeoutUrl,
            'ResultURL'          => $resultUrl,
            'Occasion'           => substr($occasion, 0, 100),
        ]);
    }

    /** @throws ValidationException */
    private function validateOperatorConfig(): void
    {
        $errors = [];

        if (empty($this->config->initiatorName)) {
            $errors['initiator_name'] = 'initiatorName is required in Config for B2C payments';
        }

        if (empty($this->config->securityCredential)) {
            $errors['security_credential'] = 'securityCredential is required in Config for B2C payments';
        }

        if ($errors !== []) {
            throw new ValidationException($errors, 'B2C operator configuration is incomplete');
        }
    }

    /** @throws ValidationException */
    private function validatePayParams(
        int       $amount,
        CommandId $commandId,
        string    $remarks,
        string    $resultUrl,
        string    $timeoutUrl,
    ): void {
        $errors = [];

        if ($amount < 1) {
            $errors['amount'] = 'Amount must be at least 1 KES';
        }

        $b2cCommands = [
            CommandId::SalaryPayment,
            CommandId::BusinessPayment,
            CommandId::PromotionPayment,
        ];

        if (!in_array($commandId, $b2cCommands, true)) {
            $errors['command_id'] = 'Invalid CommandID for B2C. Use SalaryPayment, BusinessPayment, or PromotionPayment';
        }

        if (empty($remarks)) {
            $errors['remarks'] = 'Remarks are required';
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
