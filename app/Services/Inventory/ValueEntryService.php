<?php

declare(strict_types=1);

namespace App\Services\Inventory;

use App\Models\ItemLedgerEntry;
use App\Models\ValueEntry;
use Illuminate\Support\Facades\Log;
use UnitEnum;

class ValueEntryService
{
    public function ensureForItemLedgerEntry(ItemLedgerEntry $entry): ?ValueEntry
    {
        try {
            $itemNo = (string) ($entry->item?->item_code ?? $entry->item_id);
            $locationCode = (string) ($entry->location?->code ?? $entry->location_id ?? 'MAIN');
            $quantity = (float) $entry->quantity;
            $costAmountActual = (float) $entry->cost_amount_actual;
            $costAmountExpected = (float) $entry->cost_amount_expected;
            $unitCost = $quantity !== 0.0 ? ($costAmountActual / $quantity) : 0.0;

            return ValueEntry::firstOrCreate(
                [
                    'item_ledger_entry_no' => (int) $entry->entry_number,
                    'document_no' => $entry->document_number,
                    'document_line_no' => $entry->document_line_number,
                ],
                [
                    'entry_no' => (ValueEntry::max('entry_no') ?? 0) + 1,
                    'item_ledger_entry_type' => $this->mapValueEntryItemLedgerType($this->entryTypeValue($entry->entry_type)),
                    'item_no' => $itemNo,
                    'location_code' => $locationCode,
                    'posting_date' => $entry->posting_date,
                    'document_type' => $entry->document_type,
                    'description' => null,
                    'quantity' => $quantity,
                    'invoiced_quantity' => 0,
                    'cost_amount_actual' => $costAmountActual,
                    'cost_amount_expected' => $costAmountExpected,
                    'cost_amount_actual_acy' => $costAmountActual,
                    'cost_amount_expected_acy' => $costAmountExpected,
                    'unit_cost' => $unitCost,
                    'unit_cost_acy' => $unitCost,
                    'source_type' => $entry->source_type,
                    'source_no' => $entry->source_id ? (string) $entry->source_id : null,
                ],
            );
        } catch (\Throwable $exception) {
            Log::warning('Failed to auto-create Value Entry for Item Ledger Entry', [
                'item_ledger_entry_id' => $entry->id,
                'entry_number' => $entry->entry_number,
                'error' => $exception->getMessage(),
            ]);

            return null;
        }
    }

    private function mapValueEntryItemLedgerType(string $entryType): int
    {
        return match (strtolower($entryType)) {
            'purchase' => 1,
            'sale' => 2,
            'positive_adj', 'positive adjustment', 'positive adjmt.' => 3,
            'negative_adj', 'negative adjustment', 'negative adjmt.' => 4,
            'transfer' => 5,
            'consumption' => 6,
            'output' => 7,
            default => 0,
        };
    }

    private function entryTypeValue(mixed $entryType): string
    {
        if ($entryType instanceof UnitEnum) {
            return (string) $entryType->value;
        }

        return (string) $entryType;
    }
}
