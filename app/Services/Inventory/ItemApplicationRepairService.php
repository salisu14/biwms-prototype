<?php

declare(strict_types=1);

namespace App\Services\Inventory;

use App\Enums\CostingMethod;
use App\Models\ItemLedgerEntry;
use App\Support\DecimalMath;
use App\Support\DecimalPrecision;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use UnitEnum;

class ItemApplicationRepairService
{
    private const QUANTITY_TOLERANCE = 0.00000001;

    public function __construct(
        private readonly ItemApplicationService $itemApplicationService,
        private readonly ValueEntryEconomicValueService $economicValueService,
        private readonly ValueEntryAccountingOrchestrator $accountingOrchestrator,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function analyze(ItemLedgerEntry $outboundEntry): array
    {
        /** @var ItemLedgerEntry $outbound */
        $outbound = ItemLedgerEntry::query()
            ->with(['item', 'outboundApplications' => fn ($query) => $query->where('is_reversed', false)])
            ->findOrFail($outboundEntry->id);

        $quantity = abs((float) $outbound->quantity);
        $appliedQuantity = (float) $outbound->outboundApplications->sum('applied_quantity');
        $missingQuantity = max(0.0, $quantity - $appliedQuantity);
        $costingMethod = $this->costingMethod($outbound);
        $currentEconomicCost = abs((float) $this->economicValueService->economicActualValueForItemLedgerEntry($outbound));
        $existingApplicationCost = (float) $outbound->outboundApplications->sum('cost_amount');
        $candidateLayers = $this->candidateInboundLayers($outbound, $costingMethod);
        $proposedApplications = $this->proposedApplications($outbound, $candidateLayers, $costingMethod, $missingQuantity);
        $proposedCost = (float) collect($proposedApplications)->sum('cost_amount');
        $expectedCostAfterApplication = round($existingApplicationCost + $proposedCost, 4);
        $requiresValueEntryAdjustment = abs($currentEconomicCost - $expectedCostAfterApplication) > 0.0001;
        $ambiguousEarlierOutbound = $this->hasEarlierUnappliedOutbound($outbound);
        $eligibility = $this->eligibility(
            outbound: $outbound,
            appliedQuantity: $appliedQuantity,
            missingQuantity: $missingQuantity,
            candidateLayers: $candidateLayers,
            proposedApplications: $proposedApplications,
            requiresValueEntryAdjustment: $requiresValueEntryAdjustment,
            ambiguousEarlierOutbound: $ambiguousEarlierOutbound,
        );

        return [
            'outbound_item_ledger_entry_id' => $outbound->id,
            'entry_number' => $outbound->entry_number,
            'item_id' => $outbound->item_id,
            'item_code' => $outbound->item?->item_code,
            'document_type' => $outbound->document_type,
            'document_number' => $outbound->document_number,
            'posting_date' => optional($outbound->posting_date)->toDateString() ?? (string) $outbound->posting_date,
            'costing_method' => $costingMethod,
            'outbound_quantity' => round($quantity, 8),
            'currently_applied_quantity' => round($appliedQuantity, 8),
            'missing_quantity' => round($missingQuantity, 8),
            'candidate_inbound_layers' => $candidateLayers
                ->map(fn (ItemLedgerEntry $layer): array => [
                    'id' => $layer->id,
                    'entry_number' => $layer->entry_number,
                    'document_type' => $layer->document_type,
                    'document_number' => $layer->document_number,
                    'posting_date' => optional($layer->posting_date)->toDateString() ?? (string) $layer->posting_date,
                    'available_quantity' => round((float) $layer->remaining_quantity, 8),
                    'unit_cost' => round($this->unitCostForLayer($layer, $costingMethod), 8),
                ])
                ->values()
                ->all(),
            'proposed_applications' => $proposedApplications,
            'proposed_total_cost' => round($proposedCost, 4),
            'current_outbound_value_entry_economic_cost' => round($currentEconomicCost, 4),
            'expected_cost_after_application' => round($expectedCostAfterApplication, 4),
            'value_entry_adjustment_required' => $requiresValueEntryAdjustment,
            'gl_adjustment_required' => $requiresValueEntryAdjustment,
            'eligibility_classification' => $eligibility['classification'],
            'eligible' => $eligibility['eligible'],
            'refusal_reason' => $eligibility['refusal_reason'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function repair(ItemLedgerEntry $outboundEntry): array
    {
        return DB::transaction(function () use ($outboundEntry): array {
            /** @var ItemLedgerEntry $lockedOutbound */
            $lockedOutbound = ItemLedgerEntry::query()
                ->lockForUpdate()
                ->findOrFail($outboundEntry->id);

            $analysis = $this->analyze($lockedOutbound);

            if (! $analysis['eligible']) {
                return [
                    ...$analysis,
                    'repaired' => false,
                    'idempotent' => $analysis['eligibility_classification'] === 'already_applied',
                ];
            }

            $this->candidateInboundLayers($lockedOutbound, (string) $analysis['costing_method'], lock: true);

            $applications = $this->itemApplicationService->applyOutbound($lockedOutbound, 'historical_sales_shipment_repair');
            $this->accountingOrchestrator->postForItemLedgerEntry($lockedOutbound->fresh());

            return [
                ...$this->analyze($lockedOutbound->fresh()),
                'repaired' => true,
                'idempotent' => false,
                'application_ids' => collect($applications)->pluck('id')->all(),
            ];
        });
    }

    /**
     * @return Collection<int, ItemLedgerEntry>
     */
    private function candidateInboundLayers(ItemLedgerEntry $outbound, string $method, bool $lock = false): Collection
    {
        $query = ItemLedgerEntry::query()
            ->with('item')
            ->where('item_id', $outbound->item_id)
            ->where('quantity', '>', 0)
            ->where('remaining_quantity', '>', 0)
            ->where('id', '!=', $outbound->id)
            ->where('posting_date', '<=', $outbound->posting_date);

        if ($lock) {
            $query->lockForUpdate();
        }

        if ($outbound->location_id) {
            $query->where('location_id', $outbound->location_id);
        }

        if ($method === CostingMethod::SPECIFIC->value) {
            return $outbound->applied_entry_id
                ? $query->where('id', $outbound->applied_entry_id)->get()
                : collect();
        }

        if ($method === CostingMethod::LIFO->value) {
            return $query->orderByDesc('posting_date')->orderByDesc('entry_number')->get();
        }

        return $query->orderBy('posting_date')->orderBy('entry_number')->get();
    }

    /**
     * @param  Collection<int, ItemLedgerEntry>  $candidateLayers
     * @return list<array<string, mixed>>
     */
    private function proposedApplications(ItemLedgerEntry $outbound, Collection $candidateLayers, string $method, float $missingQuantity): array
    {
        $remaining = $missingQuantity;
        $averageUnitCost = $method === CostingMethod::AVERAGE->value ? $this->averageUnitCost($candidateLayers) : null;
        $applications = [];

        foreach ($candidateLayers as $layer) {
            if ($remaining <= self::QUANTITY_TOLERANCE) {
                break;
            }

            $availableQuantity = max(0.0, (float) $layer->remaining_quantity);
            if ($availableQuantity <= self::QUANTITY_TOLERANCE) {
                continue;
            }

            $appliedQuantity = min($remaining, $availableQuantity);
            $unitCost = $averageUnitCost ?? $this->unitCostForLayer($layer, $method);

            $applications[] = [
                'inbound_item_ledger_entry_id' => $layer->id,
                'entry_number' => $layer->entry_number,
                'posting_date' => optional($layer->posting_date)->toDateString() ?? (string) $layer->posting_date,
                'available_quantity' => round($availableQuantity, 8),
                'proposed_applied_quantity' => round($appliedQuantity, 8),
                'unit_cost' => round($unitCost, 8),
                'cost_amount' => (float) DecimalMath::amount(DecimalMath::mul($appliedQuantity, $unitCost, DecimalPrecision::AMOUNT_SCALE)),
                'idempotency_key' => hash('sha256', implode('|', [
                    'item-application',
                    $layer->id,
                    $outbound->id,
                    DecimalMath::quantity($appliedQuantity),
                ])),
            ];

            $remaining -= $appliedQuantity;
        }

        return $applications;
    }

    /**
     * @param  Collection<int, ItemLedgerEntry>  $candidateLayers
     * @param  list<array<string, mixed>>  $proposedApplications
     * @return array{eligible: bool, classification: string, refusal_reason: ?string}
     */
    private function eligibility(
        ItemLedgerEntry $outbound,
        float $appliedQuantity,
        float $missingQuantity,
        Collection $candidateLayers,
        array $proposedApplications,
        bool $requiresValueEntryAdjustment,
        bool $ambiguousEarlierOutbound,
    ): array {
        if ((float) $outbound->quantity >= 0.0) {
            return $this->ineligible('not_outbound', 'Only outbound Item Ledger Entries can be repaired.');
        }

        if ((string) $outbound->document_type !== 'SALES_ORDER_SHIPMENT') {
            return $this->ineligible('unsupported_document_type', 'Only SALES_ORDER_SHIPMENT entries are supported by this narrow repair.');
        }

        if ($appliedQuantity > self::QUANTITY_TOLERANCE && $missingQuantity > self::QUANTITY_TOLERANCE) {
            return $this->ineligible('partial_existing_applications_unsupported', 'Partial existing applications require a manual forensic review.');
        }

        if ($missingQuantity <= self::QUANTITY_TOLERANCE) {
            return [
                'eligible' => false,
                'classification' => 'already_applied',
                'refusal_reason' => null,
            ];
        }

        if ($ambiguousEarlierOutbound) {
            return $this->ineligible('ambiguous_layer_history', 'Earlier unapplied outbound entries for the same item/location could change layer ownership.');
        }

        if ((float) collect($proposedApplications)->sum('proposed_applied_quantity') + self::QUANTITY_TOLERANCE < $missingQuantity) {
            return $this->ineligible('insufficient_historical_inventory', 'Insufficient historically eligible open inbound quantity.');
        }

        if ($candidateLayers->isEmpty()) {
            return $this->ineligible('no_candidate_layers', 'No historically eligible inbound layers were found.');
        }

        if ($requiresValueEntryAdjustment) {
            return $this->ineligible('value_entry_or_gl_adjustment_required', 'This repair would require Value Entry/G/L adjustment and is refused by the narrow metadata repair workflow.');
        }

        return [
            'eligible' => true,
            'classification' => 'safe_application_metadata_repair',
            'refusal_reason' => null,
        ];
    }

    /**
     * @return array{eligible: false, classification: string, refusal_reason: string}
     */
    private function ineligible(string $classification, string $reason): array
    {
        return [
            'eligible' => false,
            'classification' => $classification,
            'refusal_reason' => $reason,
        ];
    }

    private function hasEarlierUnappliedOutbound(ItemLedgerEntry $outbound): bool
    {
        return ItemLedgerEntry::query()
            ->where('item_id', $outbound->item_id)
            ->when($outbound->location_id, fn ($query) => $query->where('location_id', $outbound->location_id))
            ->where('quantity', '<', 0)
            ->where('id', '!=', $outbound->id)
            ->where('posting_date', '<=', $outbound->posting_date)
            ->whereDoesntHave('outboundApplications', fn ($query) => $query->where('is_reversed', false))
            ->exists();
    }

    private function costingMethod(ItemLedgerEntry $entry): string
    {
        $method = $entry->item?->costing_method;
        $method = $method instanceof UnitEnum ? $method->value : (string) ($method ?: CostingMethod::FIFO->value);

        return in_array($method, array_column(CostingMethod::cases(), 'value'), true) ? $method : CostingMethod::FIFO->value;
    }

    private function unitCostForLayer(ItemLedgerEntry $inbound, string $method): float
    {
        if ($method === CostingMethod::STANDARD->value) {
            return (float) ($inbound->item?->standard_cost ?: $inbound->item?->unit_cost ?: 0);
        }

        $quantity = abs((float) $inbound->quantity);
        if ($quantity <= self::QUANTITY_TOLERANCE) {
            return 0.0;
        }

        $cost = (float) $inbound->cost_amount_actual;
        if ($cost <= 0.0) {
            $cost = (float) $inbound->cost_amount_expected;
        }

        return abs($cost) / $quantity;
    }

    /**
     * @param  Collection<int, ItemLedgerEntry>  $layers
     */
    private function averageUnitCost(Collection $layers): float
    {
        $totalQuantity = 0.0;
        $totalCost = 0.0;

        foreach ($layers as $layer) {
            $quantity = max(0.0, (float) $layer->remaining_quantity);
            if ($quantity <= self::QUANTITY_TOLERANCE) {
                continue;
            }

            $totalQuantity += $quantity;
            $totalCost += $quantity * $this->unitCostForLayer($layer, CostingMethod::FIFO->value);
        }

        return $totalQuantity > self::QUANTITY_TOLERANCE ? $totalCost / $totalQuantity : 0.0;
    }
}
