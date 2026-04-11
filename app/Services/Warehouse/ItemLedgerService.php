<?php

declare(strict_types=1);

namespace App\Services\Warehouse;

use App\Enums\ItemLedgerEntryType;
use App\Models\ItemLedgerEntry;
use App\Models\WarehouseEntry;
use Illuminate\Support\Facades\DB;

/**
 * Service to handle Item Ledger Entry creation from Warehouse transactions.
 * BC equivalent: Codeunit 22 (integration)
 */
class ItemLedgerService
{
    /**
     * Post a physical inventory movement into Item Ledger from a Warehouse Entry.
     */
    public function postFromWarehouseEntry(WarehouseEntry $warehouseEntry): ItemLedgerEntry
    {
        return DB::transaction(function () use ($warehouseEntry) {
            
            // Map Warehouse Entry type to Item Ledger Entry type
            $entryType = $warehouseEntry->entry_type === 'positive' 
                ? ItemLedgerEntryType::POSITIVE_ADJUSTMENT->value 
                : ItemLedgerEntryType::NEGATIVE_ADJUSTMENT->value;
            
            return ItemLedgerEntry::create([
                'entry_number' => $this->getNextEntryNumber(),
                'item_id' => $warehouseEntry->item_id,
                'location_id' => $warehouseEntry->location_id,
                'variant_code' => $warehouseEntry->item->variant_code ?? null,
                'bin_code' => $warehouseEntry->bin?->bin_code,
                'quantity' => $warehouseEntry->getSignedQuantity(),
                'remaining_quantity' => $warehouseEntry->getSignedQuantity(),
                'entry_type' => $entryType,
                'document_type' => $warehouseEntry->document_type,
                'document_number' => $warehouseEntry->document_no,
                'document_line_number' => $warehouseEntry->document_line_no ?? 0,
                'posting_date' => now(),
                'entry_date' => now(),
                'serial_number' => $warehouseEntry->serial_no,
                'lot_number' => $warehouseEntry->lot_no,
                'expiration_date' => $warehouseEntry->expiration_date,
                'cost_amount_actual' => $warehouseEntry->total_cost ?? 0,
                'open' => true,
                'source_type' => 'WAREHOUSE',
                'source_id' => $warehouseEntry->id,
            ]);
        });
    }

    private function getNextEntryNumber(): int
    {
        return (ItemLedgerEntry::max('entry_number') ?? 0) + 1;
    }
}
