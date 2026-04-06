<?php

namespace App\Data\Sales;

class SalesInvoiceData
{
    /**
     * @param int $customer_id
     * @param string $invoice_date
     * @param string|null $due_date
     * @param array $lines Array of line items (e.g., from Filament Repeater)
     */
    public function __construct(
        public int $customer_id,
        public string $invoice_date,
        public ?string $due_date,
        public array $lines
    ) {}

    /**
     * Create a DTO instance from an array (typically from Filament's form $data).
     *
     * @param array $data
     * @return self
     */
    public static function from(array $data): self
    {
        return new self(
            customer_id: (int) $data['customer_id'],
            invoice_date: $data['invoice_date'],
            due_date: $data['due_date'] ?? null,
            // Ensure lines are passed as an array, defaulting to empty if missing
            lines: $data['lines'] ?? []
        );
    }
}
