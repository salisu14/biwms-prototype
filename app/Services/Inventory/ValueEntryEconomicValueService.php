<?php

declare(strict_types=1);

namespace App\Services\Inventory;

use App\Models\CostAdjustmentBatch;
use App\Models\ItemApplicationEntry;
use App\Models\ItemLedgerEntry;
use App\Models\ValueEntry;
use App\Support\DecimalMath;
use App\Support\DecimalPrecision;

class ValueEntryEconomicValueService
{
    public function currentEconomicCostForApplication(ItemApplicationEntry $application): string
    {
        $outbound = $application->outboundItemLedgerEntry;
        if (! $outbound) {
            return DecimalMath::amount('0');
        }

        $baseActualCost = ValueEntry::query()
            ->where('item_ledger_entry_no', $outbound->entry_number)
            ->where('value_entry_state', 'actual')
            ->where(function ($query): void {
                $query->where('expected_cost', false)
                    ->orWhereNull('expected_cost');
            })
            ->sum('cost_amount_actual');

        if (abs((float) $baseActualCost) <= 0.0001) {
            $baseActualCost = $application->cost_amount;
        }

        $applicationAdjustmentCost = ValueEntry::query()
            ->where('item_ledger_entry_no', $outbound->entry_number)
            ->where('source_type', CostAdjustmentBatch::class)
            ->where('source_line_no', $application->id)
            ->whereIn('value_entry_state', ['adjustment', 'reversal'])
            ->where(function ($query): void {
                $query->where('document_type', 'COST_ADJUSTMENT')
                    ->orWhere('value_entry_state', 'reversal');
            })
            ->sum('cost_amount_actual');

        return DecimalMath::amount(DecimalMath::add($baseActualCost, $applicationAdjustmentCost, DecimalPrecision::AMOUNT_SCALE));
    }

    public function targetCostForApplication(ItemApplicationEntry $application, string $correctedInboundUnitCost): string
    {
        return DecimalMath::amount(DecimalMath::mul(
            DecimalMath::abs($application->applied_quantity, DecimalPrecision::QUANTITY_SCALE),
            $correctedInboundUnitCost,
            DecimalPrecision::AMOUNT_SCALE,
        ));
    }

    public function outstandingAdjustmentForApplication(ItemApplicationEntry $application, string $correctedInboundUnitCost): string
    {
        return DecimalMath::sub(
            $this->targetCostForApplication($application, $correctedInboundUnitCost),
            $this->currentEconomicCostForApplication($application),
            DecimalPrecision::AMOUNT_SCALE,
        );
    }

    public function originalActualValueForItemLedgerEntry(ItemLedgerEntry $entry): string
    {
        return DecimalMath::amount(ValueEntry::query()
            ->where('item_ledger_entry_no', $entry->entry_number)
            ->where('value_entry_state', 'actual')
            ->where(function ($query): void {
                $query->where('expected_cost', false)
                    ->orWhereNull('expected_cost');
            })
            ->sum('cost_amount_actual'));
    }

    public function economicValueForItemLedgerEntry(ItemLedgerEntry $entry): string
    {
        $value = ValueEntry::query()
            ->where('item_ledger_entry_no', $entry->entry_number)
            ->where(function ($query): void {
                $query->where('expected_cost', false)
                    ->orWhereNull('expected_cost');
            })
            ->whereIn('value_entry_state', ['actual', 'adjustment', 'reversal'])
            ->where(function ($query): void {
                $query->where('document_type', '!=', 'PRODUCTION_COST_ADJUSTMENT')
                    ->orWhereNull('document_type');
            })
            ->sum('cost_amount_actual');

        return DecimalMath::amount($value);
    }

    public function economicValueForInboundLayer(ItemLedgerEntry $entry): string
    {
        $batchAdjustmentCost = CostAdjustmentBatch::query()
            ->where('source_type', ItemLedgerEntry::class)
            ->where('source_id', $entry->id)
            ->where('dry_run', false)
            ->get()
            ->sum(function (CostAdjustmentBatch $batch): float {
                return (float) ValueEntry::query()
                    ->where('source_type', CostAdjustmentBatch::class)
                    ->where('source_id', $batch->id)
                    ->whereIn('value_entry_state', ['adjustment', 'reversal'])
                    ->where(function ($query): void {
                        $query->where('document_type', '!=', 'PRODUCTION_COST_ADJUSTMENT')
                            ->orWhereNull('document_type');
                    })
                    ->sum('cost_amount_actual');
            });

        return DecimalMath::amount(DecimalMath::add(
            $this->originalActualValueForItemLedgerEntry($entry),
            $batchAdjustmentCost,
            DecimalPrecision::AMOUNT_SCALE,
        ));
    }

    public function hasPostedCostAdjustmentBatch(ItemLedgerEntry $entry): bool
    {
        return CostAdjustmentBatch::query()
            ->where('source_type', ItemLedgerEntry::class)
            ->where('source_id', $entry->id)
            ->where('dry_run', false)
            ->exists();
    }
}
