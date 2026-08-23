<?php

declare(strict_types=1);

namespace App\Services\Inventory;

use App\Enums\CostingMethod;
use App\Models\ItemApplicationEntry;
use App\Models\ItemLedgerEntry;
use App\Models\ValueEntry;
use App\Support\DecimalMath;
use App\Support\DecimalPrecision;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use UnitEnum;

class HistoricalSalesShipmentCostRepairService
{
    private const QUANTITY_TOLERANCE = 0.00000001;

    private const AMOUNT_TOLERANCE = 0.0001;

    public function __construct(
        private readonly ValueEntryEconomicValueService $economicValueService,
        private readonly ValueEntryAccountingOrchestrator $accountingOrchestrator,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function analyze(ItemLedgerEntry $outboundEntry): array
    {
        return $this->buildAnalysis($outboundEntry, lock: false);
    }

    /**
     * @return array<string, mixed>
     */
    public function repair(ItemLedgerEntry $outboundEntry): array
    {
        return DB::transaction(function () use ($outboundEntry): array {
            $analysis = $this->buildAnalysis($outboundEntry, lock: true);

            if (! $analysis['eligible']) {
                return [
                    ...$analysis,
                    'repaired' => false,
                    'idempotent' => $analysis['eligibility_classification'] === 'already_repaired',
                ];
            }

            /** @var ItemLedgerEntry $outbound */
            $outbound = ItemLedgerEntry::query()
                ->with(['item', 'location'])
                ->lockForUpdate()
                ->findOrFail($analysis['outbound_item_ledger_entry_id']);

            $applications = [];
            $touchedInboundIds = [];

            foreach ($analysis['proposed_applications'] as $proposal) {
                /** @var ItemLedgerEntry $inbound */
                $inbound = ItemLedgerEntry::query()
                    ->lockForUpdate()
                    ->findOrFail($proposal['inbound_item_ledger_entry_id']);

                $application = ItemApplicationEntry::query()->firstOrCreate(
                    ['idempotency_key' => $proposal['idempotency_key']],
                    [
                        'inbound_item_ledger_entry_id' => $inbound->id,
                        'outbound_item_ledger_entry_id' => $outbound->id,
                        'applied_quantity' => DecimalMath::quantity($proposal['proposed_applied_quantity']),
                        'remaining_quantity_after_application' => DecimalMath::quantity($proposal['historical_remaining_after_application']),
                        'application_date' => $analysis['posting_date'],
                        'application_source' => 'historical_sales_shipment_cost_repair',
                        'costing_method' => $analysis['costing_method'],
                        'unit_cost' => DecimalMath::unitCost($proposal['unit_cost']),
                        'cost_amount' => DecimalMath::amount($proposal['cost_amount']),
                        'audit_metadata' => [
                            'historical_sales_shipment_cost_repair' => true,
                            'outbound_item_ledger_entry_id' => $outbound->id,
                            'outbound_entry_number' => $outbound->entry_number,
                            'outbound_document_type' => $outbound->document_type,
                            'outbound_document_number' => $outbound->document_number,
                            'inbound_item_ledger_entry_id' => $inbound->id,
                            'inbound_entry_number' => $inbound->entry_number,
                            'historical_available_quantity' => $proposal['historical_available_quantity'],
                        ],
                    ],
                );

                $applications[] = $application;
                $touchedInboundIds[] = $inbound->id;
            }

            foreach (array_unique($touchedInboundIds) as $inboundId) {
                $this->refreshInboundRemainingQuantity((int) $inboundId);
            }

            $outbound->forceFill([
                'cost_amount_actual' => DecimalMath::amount($analysis['corrected_outbound_cost']),
                'purchase_amount_actual' => DecimalMath::amount($analysis['corrected_outbound_cost']),
                'remaining_quantity' => 0,
                'open' => false,
            ])->save();

            $adjustmentValueEntry = null;
            if (abs((float) $analysis['adjustment_delta']) > self::AMOUNT_TOLERANCE) {
                $adjustmentValueEntry = $this->createAdjustmentValueEntry($outbound->fresh(), $analysis, $applications);
                $this->accountingOrchestrator->post($adjustmentValueEntry);
            }

            return [
                ...$this->buildAnalysis($outbound->fresh(), lock: true),
                'repaired' => true,
                'idempotent' => false,
                'application_ids' => collect($applications)->pluck('id')->all(),
                'adjustment_value_entry_id' => $adjustmentValueEntry?->id,
            ];
        });
    }

    /**
     * @return array<string, mixed>
     */
    private function buildAnalysis(ItemLedgerEntry $outboundEntry, bool $lock): array
    {
        $query = ItemLedgerEntry::query()
            ->with([
                'item',
                'location',
                'outboundApplications' => fn ($query) => $query->where('is_reversed', false),
            ]);

        if ($lock) {
            $query->lockForUpdate();
        }

        /** @var ItemLedgerEntry $outbound */
        $outbound = $query->findOrFail($outboundEntry->id);

        $outboundQuantity = abs((float) $outbound->quantity);
        $existingAppliedQuantity = (float) $outbound->outboundApplications->sum('applied_quantity');
        $missingQuantity = max(0.0, $outboundQuantity - $existingAppliedQuantity);
        $costingMethod = $this->costingMethod($outbound);
        $candidateLayers = $this->historicalCandidateLayers($outbound, $costingMethod, $lock);
        $proposedApplications = $this->proposedHistoricalApplications($outbound, $candidateLayers, $missingQuantity);
        $existingApplicationCost = (float) $outbound->outboundApplications->sum('cost_amount');
        $proposedApplicationCost = (float) collect($proposedApplications)->sum('cost_amount');
        $correctedOutboundCost = DecimalMath::amount($existingApplicationCost + $proposedApplicationCost);
        $currentEconomicCost = DecimalMath::amount($this->economicValueService->economicActualValueForItemLedgerEntry($outbound));
        $adjustmentDelta = DecimalMath::sub($correctedOutboundCost, $currentEconomicCost, DecimalPrecision::AMOUNT_SCALE);
        $laterApplicationImpacts = $this->laterApplicationImpacts($outbound, $proposedApplications);
        $ambiguousEarlierOutbound = $this->hasEarlierUnappliedOutbound($outbound);
        $eligibility = $this->eligibility(
            outbound: $outbound,
            costingMethod: $costingMethod,
            existingAppliedQuantity: $existingAppliedQuantity,
            missingQuantity: $missingQuantity,
            proposedApplications: $proposedApplications,
            laterApplicationImpacts: $laterApplicationImpacts,
            ambiguousEarlierOutbound: $ambiguousEarlierOutbound,
        );
        $glDirection = $this->glDirection($adjustmentDelta);

        return [
            'outbound_item_ledger_entry_id' => $outbound->id,
            'entry_number' => $outbound->entry_number,
            'item_id' => $outbound->item_id,
            'item_code' => $outbound->item?->item_code,
            'document_type' => $outbound->document_type,
            'document_number' => $outbound->document_number,
            'posting_date' => optional($outbound->posting_date)->toDateString() ?? (string) $outbound->posting_date,
            'costing_method' => $costingMethod,
            'outbound_quantity' => round($outboundQuantity, 8),
            'currently_applied_quantity' => round($existingAppliedQuantity, 8),
            'missing_quantity' => round($missingQuantity, 8),
            'candidate_inbound_layers' => $candidateLayers
                ->map(fn (array $layer): array => [
                    'id' => $layer['entry']->id,
                    'entry_number' => $layer['entry']->entry_number,
                    'document_type' => $layer['entry']->document_type,
                    'document_number' => $layer['entry']->document_number,
                    'posting_date' => optional($layer['entry']->posting_date)->toDateString() ?? (string) $layer['entry']->posting_date,
                    'original_quantity' => round((float) $layer['entry']->quantity, 8),
                    'historical_prior_applied_quantity' => round((float) $layer['historical_prior_applied_quantity'], 8),
                    'historical_available_quantity' => round((float) $layer['historical_available_quantity'], 8),
                    'current_non_reversed_applied_quantity' => round((float) $layer['current_non_reversed_applied_quantity'], 8),
                    'current_remaining_quantity' => round((float) $layer['entry']->remaining_quantity, 8),
                    'unit_cost' => round((float) $layer['unit_cost'], 8),
                ])
                ->values()
                ->all(),
            'proposed_applications' => $proposedApplications,
            'old_outbound_ile_cost' => DecimalMath::amount($outbound->cost_amount_actual),
            'corrected_outbound_cost' => $correctedOutboundCost,
            'old_value_entry_economic_cost' => $currentEconomicCost,
            'corrected_value_entry_economic_cost' => $correctedOutboundCost,
            'adjustment_delta' => $adjustmentDelta,
            'gl_currency_rounded_delta' => DecimalMath::currency($adjustmentDelta),
            'value_entry_adjustment_required' => abs((float) $adjustmentDelta) > self::AMOUNT_TOLERANCE,
            'gl_adjustment_required' => abs((float) $adjustmentDelta) > self::AMOUNT_TOLERANCE,
            'expected_gl_debit' => $glDirection['debit'],
            'expected_gl_credit' => $glDirection['credit'],
            'later_application_impacts' => $laterApplicationImpacts,
            'eligibility_classification' => $eligibility['classification'],
            'eligible' => $eligibility['eligible'],
            'refusal_reason' => $eligibility['refusal_reason'],
        ];
    }

    /**
     * @return Collection<int, array{entry: ItemLedgerEntry, historical_prior_applied_quantity: float, historical_available_quantity: float, current_non_reversed_applied_quantity: float, unit_cost: float}>
     */
    private function historicalCandidateLayers(ItemLedgerEntry $outbound, string $method, bool $lock): Collection
    {
        if ($method !== CostingMethod::FIFO->value) {
            return collect();
        }

        $query = ItemLedgerEntry::query()
            ->with(['item', 'location'])
            ->where('item_id', $outbound->item_id)
            ->where('quantity', '>', 0)
            ->where('posting_date', '<=', $outbound->posting_date)
            ->where('id', '!=', $outbound->id);

        if ($lock) {
            $query->lockForUpdate();
        }

        if ($outbound->location_id) {
            $query->where('location_id', $outbound->location_id);
        }

        return $query
            ->orderBy('posting_date')
            ->orderBy('entry_number')
            ->get()
            ->map(function (ItemLedgerEntry $entry) use ($outbound, $method): array {
                $historicalPriorApplied = $this->appliedQuantityBeforeOutbound($entry, $outbound);
                $currentApplied = (float) ItemApplicationEntry::query()
                    ->where('inbound_item_ledger_entry_id', $entry->id)
                    ->where('is_reversed', false)
                    ->sum('applied_quantity');

                return [
                    'entry' => $entry,
                    'historical_prior_applied_quantity' => $historicalPriorApplied,
                    'historical_available_quantity' => max(0.0, (float) $entry->quantity - $historicalPriorApplied),
                    'current_non_reversed_applied_quantity' => $currentApplied,
                    'unit_cost' => $this->unitCostForLayer($entry, $method),
                ];
            });
    }

    /**
     * @param  Collection<int, array{entry: ItemLedgerEntry, historical_available_quantity: float, unit_cost: float}>  $candidateLayers
     * @return list<array<string, mixed>>
     */
    private function proposedHistoricalApplications(ItemLedgerEntry $outbound, Collection $candidateLayers, float $missingQuantity): array
    {
        $remaining = $missingQuantity;
        $applications = [];

        foreach ($candidateLayers as $layer) {
            if ($remaining <= self::QUANTITY_TOLERANCE) {
                break;
            }

            $availableQuantity = (float) $layer['historical_available_quantity'];
            if ($availableQuantity <= self::QUANTITY_TOLERANCE) {
                continue;
            }

            /** @var ItemLedgerEntry $inbound */
            $inbound = $layer['entry'];
            $appliedQuantity = min($remaining, $availableQuantity);
            $costAmount = DecimalMath::amount(DecimalMath::mul($appliedQuantity, $layer['unit_cost'], DecimalPrecision::AMOUNT_SCALE));

            $applications[] = [
                'inbound_item_ledger_entry_id' => $inbound->id,
                'entry_number' => $inbound->entry_number,
                'document_type' => $inbound->document_type,
                'document_number' => $inbound->document_number,
                'posting_date' => optional($inbound->posting_date)->toDateString() ?? (string) $inbound->posting_date,
                'historical_available_quantity' => round($availableQuantity, 8),
                'proposed_applied_quantity' => round($appliedQuantity, 8),
                'historical_remaining_after_application' => round($availableQuantity - $appliedQuantity, 8),
                'projected_current_applied_quantity' => round((float) $layer['current_non_reversed_applied_quantity'] + $appliedQuantity, 8),
                'projected_current_remaining_quantity' => round((float) $inbound->quantity - ((float) $layer['current_non_reversed_applied_quantity'] + $appliedQuantity), 8),
                'unit_cost' => round((float) $layer['unit_cost'], 8),
                'cost_amount' => (float) $costAmount,
                'idempotency_key' => hash('sha256', implode('|', [
                    'item-application',
                    $inbound->id,
                    $outbound->id,
                    DecimalMath::quantity($appliedQuantity),
                ])),
            ];

            $remaining -= $appliedQuantity;
        }

        return $applications;
    }

    /**
     * @param  list<array<string, mixed>>  $proposedApplications
     * @return list<array<string, mixed>>
     */
    private function laterApplicationImpacts(ItemLedgerEntry $outbound, array $proposedApplications): array
    {
        return collect($proposedApplications)
            ->map(function (array $proposal) use ($outbound): array {
                $laterApplications = ItemApplicationEntry::query()
                    ->where('inbound_item_ledger_entry_id', $proposal['inbound_item_ledger_entry_id'])
                    ->where('is_reversed', false)
                    ->whereHas('outboundItemLedgerEntry', function ($query) use ($outbound): void {
                        $query->where('posting_date', '>', $outbound->posting_date)
                            ->orWhere(function ($query) use ($outbound): void {
                                $query->where('posting_date', $outbound->posting_date)
                                    ->where('entry_number', '>', $outbound->entry_number);
                            });
                    })
                    ->sum('applied_quantity');

                return [
                    'inbound_item_ledger_entry_id' => $proposal['inbound_item_ledger_entry_id'],
                    'entry_number' => $proposal['entry_number'],
                    'later_non_reversed_applied_quantity' => round((float) $laterApplications, 8),
                    'projected_current_remaining_quantity' => $proposal['projected_current_remaining_quantity'],
                ];
            })
            ->values()
            ->all();
    }

    /**
     * @param  list<array<string, mixed>>  $proposedApplications
     * @param  list<array<string, mixed>>  $laterApplicationImpacts
     * @return array{eligible: bool, classification: string, refusal_reason: ?string}
     */
    private function eligibility(
        ItemLedgerEntry $outbound,
        string $costingMethod,
        float $existingAppliedQuantity,
        float $missingQuantity,
        array $proposedApplications,
        array $laterApplicationImpacts,
        bool $ambiguousEarlierOutbound,
    ): array {
        if ((float) $outbound->quantity >= 0.0) {
            return $this->ineligible('not_outbound', 'Only outbound Item Ledger Entries can be repaired.');
        }

        if ((string) $outbound->document_type !== 'SALES_ORDER_SHIPMENT') {
            return $this->ineligible('unsupported_document_type', 'Only SALES_ORDER_SHIPMENT entries are supported by this specialized economic repair.');
        }

        if ($costingMethod !== CostingMethod::FIFO->value) {
            return $this->ineligible('unsupported_costing_method', 'Only FIFO historical sales shipment repairs are supported by this command.');
        }

        if ($existingAppliedQuantity > self::QUANTITY_TOLERANCE && $missingQuantity > self::QUANTITY_TOLERANCE) {
            return $this->ineligible('partial_existing_applications_unsupported', 'Partial existing applications require manual forensic review.');
        }

        if ($missingQuantity <= self::QUANTITY_TOLERANCE) {
            return [
                'eligible' => false,
                'classification' => 'already_repaired',
                'refusal_reason' => null,
            ];
        }

        if ($ambiguousEarlierOutbound) {
            return $this->ineligible('ambiguous_layer_history', 'Earlier unapplied outbound entries for the same item/location could change historical FIFO ownership.');
        }

        if ((float) collect($proposedApplications)->sum('proposed_applied_quantity') + self::QUANTITY_TOLERANCE < $missingQuantity) {
            return $this->ineligible('insufficient_historical_inventory', 'Insufficient historically eligible FIFO quantity at the outbound posting date.');
        }

        foreach ($laterApplicationImpacts as $impact) {
            if ((float) $impact['projected_current_remaining_quantity'] < -self::QUANTITY_TOLERANCE) {
                return $this->ineligible('later_application_overallocates_layer', 'Existing later applications would over-apply an inbound layer after inserting the historical application.');
            }
        }

        return [
            'eligible' => true,
            'classification' => 'sales_shipment_application_cost_repair',
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

    private function createAdjustmentValueEntry(ItemLedgerEntry $outbound, array $analysis, array $applications): ValueEntry
    {
        $outbound->loadMissing(['item', 'location']);
        $idempotencyKey = hash('sha256', implode('|', [
            'historical-sales-shipment-cost-repair',
            $outbound->id,
            DecimalMath::amount($analysis['old_value_entry_economic_cost']),
            DecimalMath::amount($analysis['corrected_value_entry_economic_cost']),
        ]));

        $existing = ValueEntry::query()->where('idempotency_key', $idempotencyKey)->first();
        if ($existing) {
            return $existing;
        }

        return ValueEntry::query()->create([
            'entry_no' => (ValueEntry::max('entry_no') ?? 0) + 1,
            'item_ledger_entry_no' => $outbound->entry_number,
            'item_ledger_entry_type' => 2,
            'item_no' => (string) ($outbound->item?->item_code ?? $outbound->item_id),
            'location_code' => (string) ($outbound->location?->code ?? $outbound->location_id ?? 'MAIN'),
            'posting_date' => $this->adjustmentPostingDate($outbound),
            'valuation_date' => $outbound->posting_date,
            'document_type' => 'COST_ADJUSTMENT',
            'document_no' => 'HIST-SALES-SHIP-'.$outbound->entry_number,
            'document_line_no' => $outbound->document_line_number,
            'description' => 'Historical sales shipment cost correction',
            'quantity' => 0,
            'invoiced_quantity' => 0,
            'valued_quantity' => 0,
            'remaining_quantity' => 0,
            'cost_component' => 'cost_adjustment',
            'value_entry_state' => 'adjustment',
            'cost_amount_actual' => DecimalMath::amount($analysis['adjustment_delta']),
            'cost_amount_actual_acy' => DecimalMath::amount($analysis['adjustment_delta']),
            'cost_amount_expected' => 0,
            'cost_amount_expected_acy' => 0,
            'unit_cost' => 0,
            'unit_cost_acy' => 0,
            'source_type' => ItemLedgerEntry::class,
            'source_module' => 'inventory',
            'source_id' => $outbound->id,
            'source_number' => $outbound->document_number,
            'source_no' => (string) $outbound->id,
            'source_line_no' => $outbound->document_line_number,
            'sales_order_no' => (string) $outbound->source_id,
            'expected_cost' => false,
            'cost_adjusted' => true,
            'cost_adjustment_date' => now()->toDateString(),
            'original_entry_no' => $outbound->id,
            'idempotency_key' => $idempotencyKey,
            'accounting_metadata' => [
                'historical_sales_shipment_cost_repair' => true,
                'repaired_outbound_item_ledger_entry_id' => $outbound->id,
                'repaired_outbound_entry_number' => $outbound->entry_number,
                'created_item_application_entry_ids' => collect($applications)->pluck('id')->all(),
                'old_economic_cost' => DecimalMath::amount($analysis['old_value_entry_economic_cost']),
                'corrected_economic_cost' => DecimalMath::amount($analysis['corrected_value_entry_economic_cost']),
                'adjustment_delta' => DecimalMath::amount($analysis['adjustment_delta']),
                'base_document_type' => $outbound->document_type,
                'base_document_number' => $outbound->document_number,
            ],
            'user_id' => auth()->id() ? (string) auth()->id() : null,
        ]);
    }

    private function refreshInboundRemainingQuantity(int $inboundId): void
    {
        /** @var ItemLedgerEntry $inbound */
        $inbound = ItemLedgerEntry::query()->lockForUpdate()->findOrFail($inboundId);
        $appliedQuantity = (float) ItemApplicationEntry::query()
            ->where('inbound_item_ledger_entry_id', $inbound->id)
            ->where('is_reversed', false)
            ->sum('applied_quantity');
        $remainingQuantity = (float) $inbound->quantity - $appliedQuantity;

        if ($remainingQuantity < -self::QUANTITY_TOLERANCE) {
            throw new RuntimeException("Historical repair would over-apply inbound item ledger entry {$inbound->entry_number}.");
        }

        $inbound->forceFill([
            'remaining_quantity' => DecimalMath::quantity(max(0.0, $remainingQuantity)),
            'open' => $remainingQuantity > self::QUANTITY_TOLERANCE,
        ])->save();
    }

    private function appliedQuantityBeforeOutbound(ItemLedgerEntry $inbound, ItemLedgerEntry $outbound): float
    {
        return (float) ItemApplicationEntry::query()
            ->where('inbound_item_ledger_entry_id', $inbound->id)
            ->where('is_reversed', false)
            ->whereHas('outboundItemLedgerEntry', function ($query) use ($outbound): void {
                $query->where('posting_date', '<', $outbound->posting_date)
                    ->orWhere(function ($query) use ($outbound): void {
                        $query->where('posting_date', $outbound->posting_date)
                            ->where('entry_number', '<', $outbound->entry_number);
                    });
            })
            ->sum('applied_quantity');
    }

    private function hasEarlierUnappliedOutbound(ItemLedgerEntry $outbound): bool
    {
        return ItemLedgerEntry::query()
            ->where('item_id', $outbound->item_id)
            ->when($outbound->location_id, fn ($query) => $query->where('location_id', $outbound->location_id))
            ->where('quantity', '<', 0)
            ->where('id', '!=', $outbound->id)
            ->where(function ($query) use ($outbound): void {
                $query->where('posting_date', '<', $outbound->posting_date)
                    ->orWhere(function ($query) use ($outbound): void {
                        $query->where('posting_date', $outbound->posting_date)
                            ->where('entry_number', '<', $outbound->entry_number);
                    });
            })
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
        if ($method !== CostingMethod::FIFO->value) {
            return 0.0;
        }

        $quantity = abs((float) $inbound->quantity);
        if ($quantity <= self::QUANTITY_TOLERANCE) {
            return 0.0;
        }

        $cost = (float) $this->economicValueService->economicActualValueForItemLedgerEntry($inbound);
        if ($cost <= 0.0) {
            $cost = (float) $inbound->cost_amount_actual ?: (float) $inbound->cost_amount_expected;
        }

        return abs($cost) / $quantity;
    }

    private function adjustmentPostingDate(ItemLedgerEntry $outbound): string
    {
        return optional($outbound->posting_date)->toDateString() ?? now()->toDateString();
    }

    /**
     * @return array{debit: string, credit: string}
     */
    private function glDirection(string $adjustmentDelta): array
    {
        return (float) $adjustmentDelta >= 0.0
            ? ['debit' => 'COGS', 'credit' => 'Inventory']
            : ['debit' => 'Inventory', 'credit' => 'COGS'];
    }
}
