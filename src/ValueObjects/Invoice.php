<?php

declare(strict_types=1);

namespace Daraja\ValueObjects;

use Daraja\Exceptions\ValidationException;

/**
 * Represents a Bill Manager invoice to be sent to a customer.
 *
 * Usage:
 *   $invoice = Invoice::make(
 *       externalReference: 'INV-2025-001',
 *       billedFullName:    'John Doe',
 *       billedPhone:       '0712345678',
 *       billedPeriod:      'March 2025',
 *       invoiceName:       'Monthly Service Fee',
 *       dueDate:           '2025-04-01',
 *       accountReference:  'ACC-001',
 *       amount:            5000.00,
 *   )->addItem(new InvoiceItem('Service Fee', 4310.34))
 *    ->addItem(new InvoiceItem('VAT (16%)', 689.66));
 */
final class Invoice
{
    /** @var list<InvoiceItem> */
    private array $items = [];

    public function __construct(
        public readonly string $externalReference,
        public readonly string $billedFullName,
        public readonly string $billedPhone,
        public readonly string $billedPeriod,
        public readonly string $invoiceName,
        public readonly string $dueDate,
        public readonly string $accountReference,
        public readonly float  $amount,
    ) {
        $this->validate();
    }

    public static function make(
        string $externalReference,
        string $billedFullName,
        string $billedPhone,
        string $billedPeriod,
        string $invoiceName,
        string $dueDate,
        string $accountReference,
        float  $amount,
    ): self {
        return new self(
            externalReference: $externalReference,
            billedFullName: $billedFullName,
            billedPhone: $billedPhone,
            billedPeriod: $billedPeriod,
            invoiceName: $invoiceName,
            dueDate: $dueDate,
            accountReference: $accountReference,
            amount: $amount,
        );
    }

    public function addItem(InvoiceItem $item): self
    {
        $clone        = clone $this;
        $clone->items = [...$this->items, $item];

        return $clone;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $phone = PhoneNumber::from($this->billedPhone)->value();

        return [
            'externalReference' => $this->externalReference,
            'billedFullName'    => $this->billedFullName,
            'billedPhoneNumber' => $phone,
            'billedPeriod'      => $this->billedPeriod,
            'invoiceName'       => $this->invoiceName,
            'dueDate'           => $this->dueDate,
            'accountReference'  => $this->accountReference,
            'amount'            => number_format($this->amount, 2, '.', ''),
            'invoiceItems'      => array_map(fn(InvoiceItem $i) => $i->toArray(), $this->items),
        ];
    }

    private function validate(): void
    {
        $errors = [];

        if (empty($this->externalReference)) {
            $errors['external_reference'] = 'External reference is required';
        }

        if (empty($this->billedFullName)) {
            $errors['billed_full_name'] = 'Customer full name is required';
        }

        if (empty($this->billedPhone)) {
            $errors['billed_phone'] = 'Customer phone number is required';
        }

        if (empty($this->invoiceName)) {
            $errors['invoice_name'] = 'Invoice name is required';
        }

        if (empty($this->dueDate)) {
            $errors['due_date'] = 'Due date is required';
        } elseif (!\DateTime::createFromFormat('Y-m-d', $this->dueDate)) {
            $errors['due_date'] = 'Due date must be in YYYY-MM-DD format';
        }

        if ($this->amount <= 0) {
            $errors['amount'] = 'Invoice amount must be greater than 0';
        }

        if ($errors !== []) {
            throw new ValidationException($errors);
        }
    }
}
