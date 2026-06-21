<?php

declare(strict_types=1);

namespace App\Services\Inventory;

use App\Models\CapacityLedgerEntry as InventoryCapacityLedgerEntry;
use App\Models\ItemLedgerEntry;
use App\Models\Manufacturing\CapacityLedgerEntry;
use App\Models\Manufacturing\ProductionOrder;
use App\Models\ValueEntry;
use Illuminate\Support\Facades\Log;
use UnitEnum;

class ValueEntryService
{
    public function ensureForItemLedgerEntry(ItemLedgerEntry $entry): ?ValueEntry
    {
        try {
            $entry->loadMissing(['item', 'location', 'source']);

            $quantity = (float) $entry->quantity;
            $costAmountActual = (float) $entry->cost_amount_actual;
            $costAmountExpected = (float) $entry->cost_amount_expected;
            $unitCost = $quantity !== 0.0 ? ($costAmountActual / $quantity) : 0.0;
            $productionOrder = $entry->source instanceof ProductionOrder ? $entry->source : null;
            $entryType = strtolower($this->entryTypeValue($entry->entry_type));
            $isConsumption = $entryType === 'consumption';
            $isOutput = $entryType === 'output';

            $lookup = [
                'item_ledger_entry_no' => (int) $entry->entry_number,
                'document_no' => $entry->document_number,
                'document_line_no' => $entry->document_line_number,
            ];

            $values = [
                'item_ledger_entry_type' => $this->mapValueEntryItemLedgerType($this->entryTypeValue($entry->entry_type)),
                'item_no' => (string) ($entry->item?->item_code ?? $entry->item_id),
                'location_code' => (string) ($entry->location?->code ?? $entry->location_id ?? 'MAIN'),
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
                'single_level_material_cost' => $costAmountActual,
                'source_type' => $entry->source_type,
                'source_no' => $productionOrder?->document_number ?? ($entry->source_id ? (string) $entry->source_id : null),
                'source_line_no' => $entry->document_line_number,
                'production_order_no' => $productionOrder?->document_number,
                'production_order_line_no' => $productionOrder && $isOutput ? (string) $entry->document_line_number : null,
                'production_order_component_line_no' => $productionOrder && $isConsumption ? (string) $entry->document_line_number : null,
                'prod_order_line_item_no' => $productionOrder ? (string) ($entry->item?->item_code ?? $entry->item_id) : null,
                'user_id' => auth()->id() ? (string) auth()->id() : null,
            ];

            $valueEntry = ValueEntry::query()->where($lookup)->first();

            if ($valueEntry) {
                $valueEntry->fill($values);
                $valueEntry->save();

                return $valueEntry;
            }

            return ValueEntry::query()->create([
                'entry_no' => (ValueEntry::max('entry_no') ?? 0) + 1,
                ...$lookup,
                ...$values,
            ]);
        } catch (\Throwable $exception) {
            Log::warning('Failed to auto-create Value Entry for Item Ledger Entry', [
                'item_ledger_entry_id' => $entry->id,
                'entry_number' => $entry->entry_number,
                'error' => $exception->getMessage(),
            ]);

            return null;
        }
    }

    public function ensureForCapacityLedgerEntry(
        CapacityLedgerEntry|InventoryCapacityLedgerEntry $entry,
        ?int $userId = null
    ): ?ValueEntry {
        try {
            $entry->loadMissing(['productionOrder.item', 'routingLine', 'workCenter', 'machineCenter']);

            $productionOrder = $entry->productionOrder;
            $routingLine = $entry->routingLine;
            $quantity = (float) $entry->setup_time + (float) $entry->run_time;
            $costAmountActual = (float) $entry->total_cost;
            $unitCost = $quantity !== 0.0 ? $costAmountActual / $quantity : 0.0;
            $routingLineNumber = $routingLine?->line_number;
            $capacityCenter = $entry->machineCenter ?? $entry->workCenter;

            $lookup = [
                'item_ledger_entry_type' => 8,
                'source_type' => CapacityLedgerEntry::class,
                'source_no' => (string) $entry->id,
            ];

            $values = [
                'item_no' => (string) ($productionOrder?->item?->item_code ?? $productionOrder?->item_id ?? 'CAPACITY'),
                'location_code' => (string) ($productionOrder?->location_code ?? $capacityCenter?->location_code ?? 'MAIN'),
                'posting_date' => $entry->posting_date,
                'document_type' => 'PRODUCTION_ORDER',
                'document_no' => $entry->document_number,
                'document_line_no' => $routingLineNumber,
                'description' => $routingLine?->description,
                'quantity' => $quantity,
                'invoiced_quantity' => 0,
                'cost_amount_actual' => $costAmountActual,
                'cost_amount_expected' => 0,
                'cost_amount_actual_acy' => $costAmountActual,
                'cost_amount_expected_acy' => 0,
                'direct_cost_amount' => (float) $entry->direct_cost,
                'indirect_cost_amount' => (float) $entry->overhead_cost,
                'overhead_amount' => (float) $entry->overhead_cost,
                'unit_cost' => $unitCost,
                'unit_cost_acy' => $unitCost,
                'single_level_capacity_cost' => (float) $entry->direct_cost,
                'single_level_overhead_cost' => (float) $entry->overhead_cost,
                'source_line_no' => $routingLineNumber,
                'production_order_no' => $productionOrder?->document_number,
                'production_order_line_no' => $routingLineNumber !== null ? (string) $routingLineNumber : null,
                'prod_order_line_item_no' => $productionOrder?->item?->item_code,
                'capacity_type' => $entry->machine_center_id ? 'MACHINE_CENTER' : 'WORK_CENTER',
                'capacity_no' => $capacityCenter?->code,
                'routing_reference_no' => $routingLineNumber,
                'operation_no' => $routingLine?->operation_no,
                'user_id' => $userId ? (string) $userId : (auth()->id() ? (string) auth()->id() : null),
            ];

            $valueEntry = ValueEntry::query()->where($lookup)->first();

            if ($valueEntry) {
                $valueEntry->fill($values);
                $valueEntry->save();

                return $valueEntry;
            }

            return ValueEntry::query()->create([
                'entry_no' => (ValueEntry::max('entry_no') ?? 0) + 1,
                ...$lookup,
                ...$values,
            ]);
        } catch (\Throwable $exception) {
            Log::warning('Failed to auto-create Value Entry for Capacity Ledger Entry', [
                'capacity_ledger_entry_id' => $entry->id,
                'production_order_id' => $entry->production_order_id,
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
            'capacity' => 8,
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
