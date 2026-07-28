<?php

declare(strict_types=1);

namespace App\Services\Inventory;

use App\Models\ItemLedgerEntry;
use App\Models\ValueEntry;
use App\Support\DecimalMath;
use Illuminate\Support\Facades\DB;

class ReturnCostApplicationService
{
    public function applyExactOrFallbackCost(ItemLedgerEntry $returnEntry, ?ItemLedgerEntry $originalOutboundEntry = null): ?ValueEntry
    {
        if ((float) $returnEntry->quantity <= 0.0) {
            return null;
        }

        return DB::transaction(function () use ($returnEntry, $originalOutboundEntry): ?ValueEntry {
            /** @var ItemLedgerEntry $lockedReturn */
            $lockedReturn = ItemLedgerEntry::query()
                ->with(['item', 'location', 'outboundApplications'])
                ->lockForUpdate()
                ->findOrFail($returnEntry->id);

            $quantity = (float) $lockedReturn->quantity;
            $costSource = 'fallback';
            $unitCost = (float) ($lockedReturn->item?->unit_cost ?? $lockedReturn->item?->standard_cost ?? 0);

            if ($originalOutboundEntry) {
                $applications = $originalOutboundEntry->outboundApplications()
                    ->where('is_reversed', false)
                    ->get();

                $appliedQuantity = (float) $applications->sum('applied_quantity');
                $appliedCost = (float) $applications->sum('cost_amount');

                if ($appliedQuantity > 0.00000001) {
                    $unitCost = $appliedCost / $appliedQuantity;
                    $costSource = 'exact_original_application';
                }
            }

            $totalCost = round($quantity * $unitCost, 4);
            $lockedReturn->forceFill([
                'cost_amount_actual' => DecimalMath::amount($totalCost),
                'purchase_amount_actual' => DecimalMath::amount($totalCost),
            ])->save();

            $valueEntry = app(ValueEntryService::class)->ensureForItemLedgerEntry($lockedReturn);
            if (! $valueEntry) {
                return null;
            }

            if (! $valueEntry->gl_posted) {
                $valueEntry->forceFill([
                    'cost_amount_actual' => DecimalMath::amount($totalCost),
                    'cost_amount_actual_acy' => DecimalMath::amount($totalCost),
                    'unit_cost' => DecimalMath::unitCost($unitCost),
                    'unit_cost_acy' => DecimalMath::unitCost($unitCost),
                    'single_level_material_cost' => DecimalMath::amount($totalCost),
                    'accounting_metadata' => array_merge($valueEntry->accounting_metadata ?? [], [
                        'phase_1c_return_cost_source' => $costSource,
                        'original_outbound_item_ledger_entry_id' => $originalOutboundEntry?->id,
                    ]),
                ])->save();
            }

            return $valueEntry->fresh();
        });
    }
}
