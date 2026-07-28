<?php

declare(strict_types=1);

namespace App\Services\Inventory;

use App\Models\CapacityLedgerEntry as InventoryCapacityLedgerEntry;
use App\Models\ItemLedgerEntry;
use App\Models\Manufacturing\CapacityLedgerEntry;
use App\Models\Manufacturing\ProductionOrder;
use App\Models\PurchaseInvoice;
use App\Models\PurchaseInvoiceLine;
use App\Models\ValueEntry;
use App\Support\DecimalMath;
use App\Support\DecimalPrecision;
use Illuminate\Support\Facades\Log;
use UnitEnum;

class ValueEntryService
{
    public function ensureForItemLedgerEntry(ItemLedgerEntry $entry): ?ValueEntry
    {
        try {
            $entry->loadMissing(['item', 'location', 'source']);

            $quantity = DecimalMath::quantity($entry->quantity);
            $costAmountActual = DecimalMath::amount($entry->cost_amount_actual);
            $costAmountExpected = DecimalMath::amount($entry->cost_amount_expected);
            $isExpectedCost = DecimalMath::isZero($costAmountActual) && ! DecimalMath::isZero($costAmountExpected);
            $valuationAmount = $isExpectedCost ? $costAmountExpected : $costAmountActual;
            $unitCost = ! DecimalMath::isZero($quantity)
                ? DecimalMath::div($valuationAmount, $quantity, DecimalPrecision::UNIT_COST_SCALE)
                : DecimalMath::unitCost('0');

            if (! $isExpectedCost && DecimalMath::isZero($unitCost) && ! DecimalMath::isZero($quantity) && ! DecimalMath::isZero($entry->item?->unit_cost ?? 0)) {
                $unitCost = DecimalMath::compare($quantity, '0') < 0
                    ? DecimalMath::unitCost(DecimalMath::of($entry->item?->unit_cost)->negated())
                    : DecimalMath::unitCost($entry->item?->unit_cost);
            }
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
                'valued_quantity' => $quantity,
                'remaining_quantity' => DecimalMath::quantity($entry->remaining_quantity),
                'cost_component' => $this->costComponentForItemLedgerType($this->entryTypeValue($entry->entry_type)),
                'value_entry_state' => $isExpectedCost ? 'expected' : 'actual',
                'cost_amount_actual' => $costAmountActual,
                'cost_amount_expected' => $costAmountExpected,
                'cost_amount_actual_acy' => $costAmountActual,
                'cost_amount_expected_acy' => $costAmountExpected,
                'unit_cost' => $unitCost,
                'unit_cost_acy' => $unitCost,
                'single_level_material_cost' => $costAmountActual,
                'source_type' => $entry->source_type,
                'source_module' => $this->sourceModuleForDocumentType((string) $entry->document_type),
                'source_id' => $entry->source_id,
                'source_number' => $entry->document_number,
                'source_no' => $productionOrder?->document_number ?? ($entry->source_id ? (string) $entry->source_id : null),
                'source_line_no' => $entry->document_line_number,
                'production_order_no' => $productionOrder?->document_number,
                'production_order_line_no' => $productionOrder && $isOutput ? (string) $entry->document_line_number : null,
                'production_order_component_line_no' => $productionOrder && $isConsumption ? (string) $entry->document_line_number : null,
                'prod_order_line_item_no' => $productionOrder ? (string) ($entry->item?->item_code ?? $entry->item_id) : null,
                'user_id' => auth()->id() ? (string) auth()->id() : null,
                'expected_cost' => $isExpectedCost,
            ];

            $valueEntry = ValueEntry::query()->where($lookup)->first();

            if ($valueEntry) {
                if ($valueEntry->gl_posted) {
                    return $valueEntry;
                }

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
            $quantity = DecimalMath::quantity(DecimalMath::add($entry->setup_time, $entry->run_time, DecimalPrecision::QUANTITY_SCALE));
            $directCostAmount = DecimalMath::amount($entry->direct_cost);
            $overheadCostAmount = DecimalMath::amount($entry->overhead_cost);
            $costAmountActual = DecimalMath::amount($entry->total_cost);
            $unitCost = ! DecimalMath::isZero($quantity)
                ? DecimalMath::div($costAmountActual, $quantity, DecimalPrecision::UNIT_COST_SCALE)
                : DecimalMath::unitCost('0');
            $routingLineNumber = $routingLine?->line_number;
            $capacityCenter = $entry->machineCenter ?? $entry->workCenter;

            $baseValues = [
                'item_no' => (string) ($productionOrder?->item?->item_code ?? $productionOrder?->item_id ?? 'CAPACITY'),
                'location_code' => (string) ($productionOrder?->location_code ?? $capacityCenter?->location_code ?? 'MAIN'),
                'posting_date' => $entry->posting_date,
                'document_type' => 'PRODUCTION_ORDER',
                'document_no' => $entry->document_number,
                'document_line_no' => $routingLineNumber,
                'description' => $routingLine?->description,
                'quantity' => $quantity,
                'invoiced_quantity' => 0,
                'valued_quantity' => $quantity,
                'remaining_quantity' => 0,
                'value_entry_state' => 'actual',
                'cost_amount_expected' => 0,
                'cost_amount_expected_acy' => 0,
                'unit_cost' => $unitCost,
                'unit_cost_acy' => $unitCost,
                'source_module' => 'manufacturing',
                'source_id' => $entry->id,
                'source_number' => $entry->document_number,
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

            $directValueEntry = $this->updateOrCreateCapacityValueEntry(
                lookup: [
                    'item_ledger_entry_type' => 8,
                    'source_type' => CapacityLedgerEntry::class,
                    'source_id' => $entry->id,
                ],
                values: [
                    ...$baseValues,
                    'source_no' => (string) $entry->id,
                    'cost_component' => 'capacity',
                    'cost_amount_actual' => $directCostAmount,
                    'cost_amount_actual_acy' => $directCostAmount,
                    'direct_cost_amount' => $directCostAmount,
                    'indirect_cost_amount' => 0,
                    'overhead_amount' => 0,
                    'single_level_capacity_cost' => $directCostAmount,
                    'single_level_overhead_cost' => 0,
                ],
            );

            if (! DecimalMath::isZero($overheadCostAmount)) {
                $this->updateOrCreateCapacityValueEntry(
                    lookup: [
                        'item_ledger_entry_type' => 10,
                        'source_type' => CapacityLedgerEntry::class,
                        'source_id' => $entry->id,
                    ],
                    values: [
                        ...$baseValues,
                        'source_no' => (string) $entry->id,
                        'cost_component' => 'overhead',
                        'cost_amount_actual' => $overheadCostAmount,
                        'cost_amount_actual_acy' => $overheadCostAmount,
                        'direct_cost_amount' => 0,
                        'indirect_cost_amount' => $overheadCostAmount,
                        'overhead_amount' => $overheadCostAmount,
                        'single_level_capacity_cost' => 0,
                        'single_level_overhead_cost' => $overheadCostAmount,
                    ],
                );
            }

            return $directValueEntry;
        } catch (\Throwable $exception) {
            Log::warning('Failed to auto-create Value Entry for Capacity Ledger Entry', [
                'capacity_ledger_entry_id' => $entry->id,
                'production_order_id' => $entry->production_order_id,
                'error' => $exception->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * @param  array<string, mixed>  $lookup
     * @param  array<string, mixed>  $values
     */
    private function updateOrCreateCapacityValueEntry(array $lookup, array $values): ValueEntry
    {
        $valueEntry = ValueEntry::query()->where($lookup)->first();

        if ($valueEntry) {
            if ($valueEntry->gl_posted) {
                return $valueEntry;
            }

            $valueEntry->fill($values);
            $valueEntry->save();

            return $valueEntry;
        }

        return ValueEntry::query()->create([
            'entry_no' => (ValueEntry::max('entry_no') ?? 0) + 1,
            ...$lookup,
            ...$values,
        ]);
    }

    public function actualizePurchaseReceiptForInvoiceLine(
        ItemLedgerEntry $receiptEntry,
        PurchaseInvoice $invoice,
        PurchaseInvoiceLine $line,
        float $quantityBase,
        ?float $costAmountActual = null
    ): ValueEntry {
        $receiptEntry->loadMissing(['item', 'location']);

        if ($quantityBase <= 0) {
            throw new \RuntimeException('Actualized purchase quantity must be greater than zero.');
        }

        $existingActualQuantity = (float) ValueEntry::query()
            ->where('item_ledger_entry_no', $receiptEntry->entry_number)
            ->where('document_no', $invoice->document_number)
            ->where('document_line_no', $line->line_number ?? $line->id)
            ->where('value_entry_state', 'actual')
            ->sum('valued_quantity');

        if ($existingActualQuantity >= $quantityBase) {
            /** @var ValueEntry $existing */
            $existing = ValueEntry::query()
                ->where('item_ledger_entry_no', $receiptEntry->entry_number)
                ->where('document_no', $invoice->document_number)
                ->where('document_line_no', $line->line_number ?? $line->id)
                ->where('value_entry_state', 'actual')
                ->firstOrFail();

            return $existing;
        }

        $expectedEntry = ValueEntry::query()
            ->where('item_ledger_entry_no', $receiptEntry->entry_number)
            ->where('document_no', $receiptEntry->document_number)
            ->where('document_line_no', $receiptEntry->document_line_number)
            ->where('value_entry_state', 'expected')
            ->lockForUpdate()
            ->first();

        if (! $expectedEntry) {
            throw new \RuntimeException("Expected Value Entry is missing for purchase receipt {$receiptEntry->document_number} line {$receiptEntry->document_line_number}.");
        }

        $remainingExpectedQuantity = abs((float) ($expectedEntry->remaining_quantity ?? $expectedEntry->valued_quantity ?? $expectedEntry->quantity));
        if ($quantityBase > $remainingExpectedQuantity + 0.0001) {
            throw new \RuntimeException('Purchase invoice quantity exceeds remaining received quantity available for actualization.');
        }

        $lineCost = $costAmountActual ?? (float) $line->line_total;
        $unitCost = $quantityBase > 0 ? $lineCost / $quantityBase : 0.0;

        /** @var ValueEntry $actualEntry */
        $actualEntry = ValueEntry::query()->create([
            'entry_no' => (ValueEntry::max('entry_no') ?? 0) + 1,
            'item_ledger_entry_no' => $receiptEntry->entry_number,
            'item_ledger_entry_type' => $this->mapValueEntryItemLedgerType($this->entryTypeValue($receiptEntry->entry_type)),
            'item_no' => (string) ($receiptEntry->item?->item_code ?? $receiptEntry->item_id),
            'location_code' => (string) ($receiptEntry->location?->code ?? $receiptEntry->location_id ?? 'MAIN'),
            'posting_date' => $invoice->posting_date,
            'document_type' => 'PURCHASE_INVOICE',
            'document_no' => $invoice->document_number,
            'document_line_no' => $line->line_number ?? $line->id,
            'description' => $line->item_description,
            'quantity' => $quantityBase,
            'invoiced_quantity' => $quantityBase,
            'valued_quantity' => $quantityBase,
            'remaining_quantity' => 0,
            'cost_component' => 'inventory',
            'value_entry_state' => 'actual',
            'cost_amount_actual' => DecimalMath::amount($lineCost),
            'cost_amount_expected' => 0,
            'cost_amount_actual_acy' => DecimalMath::amount($lineCost),
            'cost_amount_expected_acy' => 0,
            'unit_cost' => DecimalMath::unitCost($unitCost),
            'unit_cost_acy' => DecimalMath::unitCost($unitCost),
            'single_level_material_cost' => DecimalMath::amount($lineCost),
            'source_type' => PurchaseInvoice::class,
            'source_module' => 'purchases',
            'source_id' => $invoice->id,
            'source_number' => $invoice->document_number,
            'source_no' => (string) $invoice->id,
            'source_line_no' => $line->line_number ?? $line->id,
            'purchase_order_no' => $invoice->order_number,
            'purchase_order_line_no' => $line->po_line_number,
            'vendor_no' => (string) ($invoice->vendor?->vendor_number ?? $invoice->vendor_id),
            'expected_cost' => false,
            'original_entry_no' => $expectedEntry->id,
            'idempotency_key' => hash('sha256', implode('|', [
                'purchase-actual-value-entry',
                $receiptEntry->id,
                $invoice->id,
                $line->id,
                DecimalMath::quantity($quantityBase),
            ])),
            'accounting_metadata' => [
                'phase_1b_actualized_from_expected_entry_id' => $expectedEntry->id,
                'phase_1c_idempotent_actualization' => true,
                'receipt_item_ledger_entry_id' => $receiptEntry->id,
                'receipt_document_no' => $receiptEntry->document_number,
                'receipt_document_line_no' => $receiptEntry->document_line_number,
            ],
            'user_id' => auth()->id() ? (string) auth()->id() : null,
        ]);

        $expectedEntry->forceFill([
            'invoiced_quantity' => DecimalMath::quantity((float) $expectedEntry->invoiced_quantity + $quantityBase),
            'remaining_quantity' => DecimalMath::quantity(max(0.0, $remainingExpectedQuantity - $quantityBase)),
            'completely_invoiced' => ($remainingExpectedQuantity - $quantityBase) <= 0.0001,
            'accounting_metadata' => array_merge($expectedEntry->accounting_metadata ?? [], [
                'phase_1b_actualized_quantity_base' => (float) $expectedEntry->invoiced_quantity + $quantityBase,
                'phase_1b_last_actualization_document_no' => $invoice->document_number,
            ]),
        ])->save();

        app(ExpectedCostClearingService::class)->clearForActualPurchaseInvoice(
            expectedEntry: $expectedEntry->fresh(),
            invoice: $invoice,
            line: $line,
            quantityBase: $quantityBase,
            actualCostAmount: (float) $lineCost,
        );

        return $actualEntry;
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

    private function sourceModuleForDocumentType(string $documentType): string
    {
        return match (true) {
            str_starts_with($documentType, 'SALES') => 'sales',
            str_starts_with($documentType, 'PURCHASE') => 'purchases',
            str_contains($documentType, 'PRODUCTION') => 'manufacturing',
            str_contains($documentType, 'TRANSFER') => 'warehouse',
            default => 'inventory',
        };
    }

    private function costComponentForItemLedgerType(string $entryType): string
    {
        return match (strtolower($entryType)) {
            'sale' => 'cogs',
            'purchase' => 'inventory',
            'consumption' => 'material',
            'output' => 'output',
            'capacity' => 'capacity',
            'positive_adj', 'positive adjustment', 'positive adjmt.', 'negative_adj', 'negative adjustment', 'negative adjmt.' => 'adjustment',
            'transfer' => 'transfer',
            default => 'inventory',
        };
    }
}
