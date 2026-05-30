<?php

namespace App\Data\Sales;

class SalesInvoiceData
{
    /**
     * @param  array  $lines  Array of line items (e.g., from Filament Repeater)
     */
    public function __construct(
        public int $customer_id,
        public ?int $sales_order_id,
        public string $invoice_date,
        public ?string $due_date,
        public ?string $currency_code,
        public array $lines
    ) {}

    /**
     * Create a DTO instance from an array (typically from Filament's form $data).
     */
    public static function from(array $data): self
    {
        return new self(
            customer_id: (int) $data['customer_id'],
            sales_order_id: isset($data['sales_order_id']) ? (int) $data['sales_order_id'] : null,
            invoice_date: $data['invoice_date'],
            due_date: $data['due_date'] ?? null,
            currency_code: $data['currency_code'] ?? null,
            // Ensure lines are passed as an array, defaulting to empty if missing
            lines: $data['lines'] ?? []
        );
    }
}
