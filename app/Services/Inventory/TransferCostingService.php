<?php

declare(strict_types=1);

namespace App\Services\Inventory;

use App\Models\InventoryPostingSetup;
use App\Models\ItemLedgerEntry;
use App\Models\PostingTransaction;
use RuntimeException;

class TransferCostingService
{
    public function __construct(
        private readonly ValueEntryAccountingOrchestrator $accountingOrchestrator,
    ) {}

    public function assertTransferSetup(ItemLedgerEntry $sourceEntry, ItemLedgerEntry $destinationEntry): void
    {
        foreach ([$sourceEntry, $destinationEntry] as $entry) {
            $setup = InventoryPostingSetup::getFor((int) $entry->inventory_posting_group_id, $entry->location_id);

            if (! $setup?->inventoryAccount) {
                throw new RuntimeException("Inventory account missing for transfer {$entry->document_number}.");
            }

            if (! $setup?->inventoryInTransitAccount) {
                throw new RuntimeException("Inventory in-transit account missing for transfer {$entry->document_number}.");
            }
        }
    }

    /**
     * @return list<PostingTransaction>
     */
    public function postCompleteTransfer(ItemLedgerEntry $sourceEntry, ItemLedgerEntry $destinationEntry): array
    {
        $this->assertTransferSetup($sourceEntry, $destinationEntry);

        return collect([$sourceEntry, $destinationEntry])
            ->map(fn (ItemLedgerEntry $entry): ?PostingTransaction => $this->accountingOrchestrator->postForItemLedgerEntry($entry))
            ->filter()
            ->all();
    }
}
