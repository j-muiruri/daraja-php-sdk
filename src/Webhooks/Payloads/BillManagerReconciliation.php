<?php

declare(strict_types=1);

namespace Daraja\Webhooks\Payloads;

use Daraja\Exceptions\ValidationException;

/**
 * Represents a Bill Manager payment reconciliation callback POSTed by Daraja.
 *
 * Fired when a customer pays an invoice sent via the Bill Manager API.
 *
 * Raw payload shape (similar to C2B Confirmation, with invoice fields):
 * {
 *   "transactiontype":  "Pay Bill",
 *   "transID":          "OEL6XXXXXXX",
 *   "transTime":        "20211204120000",
 *   "transAmount":      "200.00",
 *   "businessShortCode":"600XXX",
 *   "billRefNumber":    "ACC001",
 *   "invoiceNumber":    "INV-2025-001",
 *   "OrgAccountBalance":"",
 *   "ThirdPartyTransID":"",
 *   "MSISDN":           "2547XXXXXXXX",
 *   "FirstName":        "John",
 *   "MiddleName":       "",
 *   "LastName":         "Doe"
 * }
 *
 * Note: Daraja uses lowercase keys for Bill Manager callbacks (transID vs TransID).
 */
final class BillManagerReconciliation extends AbstractCallback
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

        // Bill Manager uses lowercase keys - normalise by checking both cases
        $transId = $raw['transID'] ?? $raw['TransID'] ?? null;

        if (empty($transId)) {
            throw new ValidationException(
                ['payload' => 'Missing transID in Bill Manager reconciliation payload'],
            );
        }

        $this->transactionType   = (string) ($raw['transactiontype']  ?? $raw['TransactionType']  ?? '');
        $this->transactionId     = (string) $transId;
        $this->businessShortCode = (string) ($raw['businessShortCode'] ?? $raw['BusinessShortCode'] ?? '');
        $this->billRefNumber     = (string) ($raw['billRefNumber']     ?? $raw['BillRefNumber']     ?? '');
        $this->invoiceNumber     = (string) ($raw['invoiceNumber']     ?? $raw['InvoiceNumber']     ?? '');
        $this->amount            = (float)  ($raw['transAmount']       ?? $raw['TransAmount']       ?? 0);
        $this->orgAccountBalance = (float)  ($raw['OrgAccountBalance'] ?? 0);
        $this->msisdn            = (string) ($raw['MSISDN']            ?? '');
        $this->firstName         = (string) ($raw['FirstName']         ?? '');
        $this->middleName        = (string) ($raw['MiddleName']        ?? '');
        $this->lastName          = (string) ($raw['LastName']          ?? '');
        $this->thirdPartyTransId = (string) ($raw['ThirdPartyTransID'] ?? '');
        $this->transactionTime   = $this->parseTimestamp(
            $raw['transTime'] ?? $raw['TransTime'] ?? null
        );
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
        return 'Invoice payment confirmed';
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
}
