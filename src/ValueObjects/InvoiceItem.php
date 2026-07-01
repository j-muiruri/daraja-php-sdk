<?php

declare(strict_types=1);

namespace Daraja\ValueObjects;

use Daraja\Exceptions\ValidationException;

/**
 * Represents a single line item on a Bill Manager invoice.
 */
final class InvoiceItem
{
    public function __construct(
        public readonly string $itemName,
        public readonly float  $amount,
        public readonly string $itemDescription = '',
    ) {
        if (empty($this->itemName)) {
            throw new ValidationException(['item_name' => 'Invoice item name is required']);
        }

        if ($this->amount < 0) {
            throw new ValidationException(['amount' => 'Invoice item amount cannot be negative']);
        }
    }

    /**
     * @return array{itemName: string, amount: string, itemDescription: string}
     */
    public function toArray(): array
    {
        return [
            'itemName'        => $this->itemName,
            'amount'          => number_format($this->amount, 2, '.', ''),
            'itemDescription' => $this->itemDescription,
        ];
    }
}
