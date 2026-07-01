<?php

declare(strict_types=1);

namespace Daraja\Webhooks\Payloads;

use Daraja\Exceptions\ValidationException;

/**
 * Represents a C2B payment confirmation POSTed by Daraja to your ConfirmationURL.
 *
 * This fires after a customer successfully pays via Paybill or Buy Goods.
 * Your endpoint must return HTTP 200; the transaction has already been completed.
 *
 * Raw payload shape:
 * {
 *   "TransactionType":    "Pay Bill",
 *   "TransID":            "LGR019G3J4",
 *   "TransTime":          "20170816190243",
 *   "TransAmount":        "200.00",
 *   "BusinessShortCode":  "600610",
 *   "BillRefNumber":      "account001",
 *   "InvoiceNumber":      "",
 *   "OrgAccountBalance":  "49197.00",
 *   "ThirdPartyTransID":  "",
 *   "MSISDN":             "254708374149",
 *   "FirstName":          "John",
 *   "MiddleName":         "",
 *   "LastName":           "Doe"
 * }
 */
final class C2BConfirmation extends AbstractCallback
{
    public readonly string              $transactionType;
    public readonly string              $transactionId;
    public readonly string              $businessShortCode;
    public readonly string              $billRefNumber;
    public readonly string              $invoiceNumber;
    public readonly float               $amount;
    public readonly float               $orgAccountBalance;
    public readonly string              $msisdn;
    public readonly string              $firstName;
    public readonly string              $middleName;
    public readonly string              $lastName;
    public readonly string              $thirdPartyTransId;
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
                ['payload' => 'Missing TransID in C2B Confirmation payload'],
            );
        }

        $this->transactionType   = (string) ($raw['TransactionType']   ?? '');
        $this->transactionId     = (string) ($raw['TransID']           ?? '');
        $this->businessShortCode = (string) ($raw['BusinessShortCode'] ?? '');
        $this->billRefNumber     = (string) ($raw['BillRefNumber']     ?? '');
        $this->invoiceNumber     = (string) ($raw['InvoiceNumber']     ?? '');
        $this->amount            = (float)  ($raw['TransAmount']       ?? 0);
        $this->orgAccountBalance = (float)  ($raw['OrgAccountBalance'] ?? 0);
        $this->msisdn            = (string) ($raw['MSISDN']            ?? '');
        $this->firstName         = (string) ($raw['FirstName']         ?? '');
        $this->middleName        = (string) ($raw['MiddleName']        ?? '');
        $this->lastName          = (string) ($raw['LastName']          ?? '');
        $this->thirdPartyTransId = (string) ($raw['ThirdPartyTransID'] ?? '');
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

    /** C2B confirmations are always successful — payment has been processed. */
    public function resultCode(): string
    {
        return '0';
    }

    public function resultDescription(): string
    {
        return 'Payment confirmed';
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

    public function isPayBill(): bool
    {
        return str_contains(strtolower($this->transactionType), 'pay bill');
    }

    public function isBuyGoods(): bool
    {
        return str_contains(strtolower($this->transactionType), 'buy goods');
    }
}
