<?php

declare(strict_types=1);

namespace App\Accounting;

final readonly class PostingIntentLine
{
    /**
     * @param  array<string, mixed>  $dimensions
     */
    public function __construct(
        public int $accountId,
        public string $debitAmount,
        public string $creditAmount,
        public ?string $description = null,
        public array $dimensions = [],
        public ?string $postingGroupSource = null,
        public ?string $costComponent = null,
        public ?int $itemId = null,
        public ?int $locationId = null,
        public ?int $workCenterId = null,
        public ?int $machineCenterId = null,
        public ?int $itemLedgerEntryId = null,
        public ?int $customerLedgerEntryId = null,
        public ?int $vendorLedgerEntryId = null,
        public ?string $sourceType = null,
        public ?string $sourceNumber = null,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            accountId: (int) $data['account_id'],
            debitAmount: (string) ($data['debit_amount'] ?? $data['debit'] ?? '0'),
            creditAmount: (string) ($data['credit_amount'] ?? $data['credit'] ?? '0'),
            description: $data['description'] ?? null,
            dimensions: $data['dimensions'] ?? [],
            postingGroupSource: $data['posting_group_source'] ?? null,
            costComponent: $data['cost_component'] ?? null,
            itemId: isset($data['item_id']) ? (int) $data['item_id'] : null,
            locationId: isset($data['location_id']) ? (int) $data['location_id'] : null,
            workCenterId: isset($data['work_center_id']) ? (int) $data['work_center_id'] : null,
            machineCenterId: isset($data['machine_center_id']) ? (int) $data['machine_center_id'] : null,
            itemLedgerEntryId: isset($data['item_ledger_entry_id']) ? (int) $data['item_ledger_entry_id'] : null,
            customerLedgerEntryId: isset($data['customer_ledger_entry_id']) ? (int) $data['customer_ledger_entry_id'] : null,
            vendorLedgerEntryId: isset($data['vendor_ledger_entry_id']) ? (int) $data['vendor_ledger_entry_id'] : null,
            sourceType: $data['source_type'] ?? null,
            sourceNumber: $data['source_number'] ?? null,
        );
    }
}
