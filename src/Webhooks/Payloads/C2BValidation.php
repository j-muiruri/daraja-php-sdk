<?php

declare(strict_types=1);

namespace Daraja\Webhooks\Payloads;

use Daraja\Exceptions\ValidationException;

/**
 * Represents a C2B payment validation request POSTed by Daraja to your ValidationURL.
 *
 * This fires BEFORE the transaction completes. You have a short window to
 * accept or reject the payment. Your response determines whether M-Pesa proceeds.
 *
 * Raw payload shape (same structure as C2BConfirmation):
 * {
 *   "TransactionType":   "Pay Bill",
 *   "TransID":           "LGR019G3J4",
 *   "TransTime":         "20170816190243",
 *   "TransAmount":       "200.00",
 *   "BusinessShortCode": "600610",
 *   "BillRefNumber":     "account001",
 *   "MSISDN":            "254708374149",
 *   "FirstName":         "John",
 *   "MiddleName":        "",
 *   "LastName":          "Doe"
 * }
 *
 * Your endpoint MUST return one of these JSON bodies:
 *   Accept:  {"ResultCode": "0",        "ResultDesc": "Accepted"}
 *   Reject:  {"ResultCode": "C2B00011", "ResultDesc": "Rejected"}
 */
final class C2BValidation extends AbstractCallback
{
    // M-Pesa rejection codes
    public const REJECT_CODE_GENERAL             = 'C2B00011';
    public const REJECT_CODE_INVALID_MSISDN      = 'C2B00012';
    public const REJECT_CODE_INVALID_ACCOUNT     = 'C2B00013';
    public const REJECT_CODE_INVALID_AMOUNT      = 'C2B00014';
    public const REJECT_CODE_INVALID_KYC         = 'C2B00015';
    public const REJECT_CODE_INVALID_SHORTCODE   = 'C2B00016';
    public const REJECT_CODE_UNABLE_TO_COMPLETE  = 'C2B00017';

    public readonly string              $transactionType;
    public readonly string              $transactionId;
    public readonly string              $businessShortCode;
    public readonly string              $billRefNumber;
    public readonly float               $amount;
    public readonly string              $msisdn;
    public readonly string              $firstName;
    public readonly string              $middleName;
    public readonly string              $lastName;
    public readonly ?\DateTimeImmutable $transactionTime;

    /**
     * @param  array<string, mixed> $raw
     * @throws ValidationException
     */
    public function __construct(array $raw)
    {
        parent::__construct($raw);

        if (empty($raw['TransID'])) {
            throw new ValidationException(
                ['payload' => 'Missing TransID in C2B Validation payload'],
            );
        }

        $this->transactionType   = (string) ($raw['TransactionType']   ?? '');
        $this->transactionId     = (string) ($raw['TransID']        );
        $this->businessShortCode = (string) ($raw['BusinessShortCode'] ?? '');
        $this->billRefNumber     = (string) ($raw['BillRefNumber']     ?? '');
        $this->amount            = (float)  ($raw['TransAmount']       ?? 0);
        $this->msisdn            = (string) ($raw['MSISDN']            ?? '');
        $this->firstName         = (string) ($raw['FirstName']         ?? '');
        $this->middleName        = (string) ($raw['MiddleName']        ?? '');
        $this->lastName          = (string) ($raw['LastName']          ?? '');
        $this->transactionTime   = $this->parseTimestamp($raw['TransTime'] ?? null);
    }

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        return new self($data);
    }

    /** @throws \JsonException */
    public static function fromJson(string $json): self
    {
        /** @var array<string, mixed> $data */
        $data = json_decode($json, true, 512, JSON_THROW_ON_ERROR);

        return new self($data);
    }

    public function resultCode(): string
    {
        return '0';
    }

    public function resultDescription(): string
    {
        return 'Validation received';
    }

    public function isSuccessful(): bool
    {
        return true;
    }

    public function customerFullName(): string
    {
        return trim(implode(' ', array_filter([
            $this->firstName,
            $this->middleName,
            $this->lastName,
        ])));
    }

    // -------------------------------------------------------------------------
    // Response builders — return these from your validation endpoint
    // -------------------------------------------------------------------------

    /**
     * Build the "Accept" response body to return to Daraja.
     *
     * @return array{ResultCode: string, ResultDesc: string}
     */
    public static function accept(): array
    {
        return ['ResultCode' => '0', 'ResultDesc' => 'Accepted'];
    }

    /**
     * Build the "Reject" response body to return to Daraja.
     *
     * @param  string $code   One of the REJECT_CODE_* constants, or your own
     * @param  string $reason Human-readable rejection reason
     * @return array{ResultCode: string, ResultDesc: string}
     */
    public static function reject(
        string $code   = self::REJECT_CODE_GENERAL,
        string $reason = 'Rejected',
    ): array {
        return ['ResultCode' => $code, 'ResultDesc' => $reason];
    }
}
