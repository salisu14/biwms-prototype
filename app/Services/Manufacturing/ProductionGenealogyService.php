<?php

declare(strict_types=1);

namespace App\Services\Manufacturing;

use App\Enums\ItemLedgerEntryType;
use App\Models\ItemApplicationEntry;
use App\Models\ItemLedgerEntry;
use App\Models\Manufacturing\ProductionOrder;
use Illuminate\Support\Collection;

class ProductionGenealogyService
{
    /**
     * @return array<string, mixed>
     */
    public function traceBackwardFromOutput(ItemLedgerEntry $finishedOutput, int $maxDepth = 10): array
    {
        return $this->traceOutput($finishedOutput, $maxDepth, []);
    }

    /**
     * @return array<string, mixed>
     */
    public function traceForwardFromInput(ItemLedgerEntry $inputEntry, int $maxDepth = 10): array
    {
        return $this->traceInput($inputEntry, $maxDepth, []);
    }

    /**
     * @return array<string, mixed>
     */
    private function traceOutput(ItemLedgerEntry $outputEntry, int $remainingDepth, array $visited): array
    {
        if ($remainingDepth < 0 || in_array($outputEntry->id, $visited, true)) {
            return ['ledger_entry_id' => $outputEntry->id, 'truncated' => true];
        }

        $visited[] = $outputEntry->id;
        $productionOrder = $this->productionOrderFor($outputEntry);
        $consumptions = $productionOrder
            ? $this->consumptionEntriesFor($productionOrder)
            : collect();

        return [
            'ledger_entry_id' => $outputEntry->id,
            'item_id' => $outputEntry->item_id,
            'item_no' => $outputEntry->item?->item_code,
            'entry_type' => $outputEntry->entry_type?->value ?? $outputEntry->entry_type,
            'quantity' => (string) $outputEntry->quantity,
            'lot_number' => $outputEntry->lot_number,
            'serial_number' => $outputEntry->serial_number,
            'production_order_id' => $productionOrder?->id,
            'production_order_no' => $productionOrder?->document_number,
            'inputs' => $consumptions
                ->map(fn (ItemLedgerEntry $consumption): array => $this->traceConsumption($consumption, $remainingDepth - 1, $visited))
                ->values()
                ->all(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function traceConsumption(ItemLedgerEntry $consumptionEntry, int $remainingDepth, array $visited): array
    {
        $applications = ItemApplicationEntry::query()
            ->with('inboundItemLedgerEntry.item')
            ->where('outbound_item_ledger_entry_id', $consumptionEntry->id)
            ->where('is_reversed', false)
            ->get();

        return [
            'ledger_entry_id' => $consumptionEntry->id,
            'item_id' => $consumptionEntry->item_id,
            'item_no' => $consumptionEntry->item?->item_code,
            'quantity' => (string) $consumptionEntry->quantity,
            'lot_number' => $consumptionEntry->lot_number,
            'serial_number' => $consumptionEntry->serial_number,
            'sources' => $applications
                ->map(function (ItemApplicationEntry $application) use ($remainingDepth, $visited): array {
                    $inbound = $application->inboundItemLedgerEntry;
                    $childProductionOrder = $inbound ? $this->productionOrderFor($inbound) : null;

                    return [
                        'application_id' => $application->id,
                        'applied_quantity' => (string) $application->applied_quantity,
                        'cost_amount' => (string) $application->cost_amount,
                        'source_ledger_entry_id' => $inbound?->id,
                        'source_item_id' => $inbound?->item_id,
                        'source_item_no' => $inbound?->item?->item_code,
                        'source_lot_number' => $inbound?->lot_number,
                        'source_serial_number' => $inbound?->serial_number,
                        'child_output' => $inbound && $childProductionOrder
                            ? $this->traceOutput($inbound, $remainingDepth - 1, $visited)
                            : null,
                    ];
                })
                ->values()
                ->all(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function traceInput(ItemLedgerEntry $inputEntry, int $remainingDepth, array $visited): array
    {
        if ($remainingDepth < 0 || in_array($inputEntry->id, $visited, true)) {
            return ['ledger_entry_id' => $inputEntry->id, 'truncated' => true];
        }

        $visited[] = $inputEntry->id;
        $applications = ItemApplicationEntry::query()
            ->with('outboundItemLedgerEntry.item')
            ->where('inbound_item_ledger_entry_id', $inputEntry->id)
            ->where('is_reversed', false)
            ->get();

        return [
            'ledger_entry_id' => $inputEntry->id,
            'item_id' => $inputEntry->item_id,
            'item_no' => $inputEntry->item?->item_code,
            'lot_number' => $inputEntry->lot_number,
            'serial_number' => $inputEntry->serial_number,
            'used_by' => $applications
                ->map(function (ItemApplicationEntry $application) use ($remainingDepth, $visited): array {
                    $outbound = $application->outboundItemLedgerEntry;
                    $productionOrder = $outbound ? $this->productionOrderFor($outbound) : null;

                    return [
                        'application_id' => $application->id,
                        'applied_quantity' => (string) $application->applied_quantity,
                        'consumption_ledger_entry_id' => $outbound?->id,
                        'production_order_id' => $productionOrder?->id,
                        'production_order_no' => $productionOrder?->document_number,
                        'outputs' => $productionOrder
                            ? $this->outputEntriesFor($productionOrder)
                                ->map(fn (ItemLedgerEntry $output): array => $this->traceOutput($output, $remainingDepth - 1, $visited))
                                ->values()
                                ->all()
                            : [],
                    ];
                })
                ->values()
                ->all(),
        ];
    }

    private function productionOrderFor(ItemLedgerEntry $entry): ?ProductionOrder
    {
        if ($entry->source_type !== ProductionOrder::class || ! $entry->source_id) {
            return null;
        }

        return ProductionOrder::query()->find($entry->source_id);
    }

    /**
     * @return Collection<int, ItemLedgerEntry>
     */
    private function consumptionEntriesFor(ProductionOrder $order): Collection
    {
        return $order->itemLedgerEntries()
            ->with('item')
            ->where('entry_type', ItemLedgerEntryType::CONSUMPTION)
            ->orderBy('id')
            ->get();
    }

    /**
     * @return Collection<int, ItemLedgerEntry>
     */
    private function outputEntriesFor(ProductionOrder $order): Collection
    {
        return $order->itemLedgerEntries()
            ->with('item')
            ->where('entry_type', ItemLedgerEntryType::OUTPUT)
            ->orderBy('id')
            ->get();
    }
}
