<?php

declare(strict_types=1);

namespace App\Services\Inventory;

use App\Enums\CostingMethod;
use App\Exceptions\InsufficientInventoryApplicationException;
use App\Models\ItemApplicationEntry;
use App\Models\ItemLedgerEntry;
use App\Support\DecimalMath;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use UnitEnum;

class ItemApplicationService
{
    public function __construct(
        private readonly CostingPeriodService $costingPeriodService,
    ) {}

    /**
     * @return list<ItemApplicationEntry>
     */
    public function applyOutbound(ItemLedgerEntry $outboundEntry, ?string $applicationSource = null, bool $strict = true): array
    {
        if ((float) $outboundEntry->quantity >= 0.0) {
            return [];
        }

        return DB::transaction(function () use ($outboundEntry, $applicationSource, $strict): array {
            /** @var ItemLedgerEntry $outbound */
            $outbound = ItemLedgerEntry::query()
                ->with('item')
                ->lockForUpdate()
                ->findOrFail($outboundEntry->id);

            $this->costingPeriodService->assertApplicationMutable($outbound->posting_date);

            $existing = ItemApplicationEntry::query()
                ->where('outbound_item_ledger_entry_id', $outbound->id)
                ->where('is_reversed', false)
                ->get();

            if ($existing->isNotEmpty()) {
                return $existing->all();
            }

            $remainingToApply = abs((float) $outbound->quantity);
            $method = $this->costingMethod($outbound);
            $inboundLayers = $this->candidateInboundLayers($outbound, $method);
            $averageUnitCost = $method === CostingMethod::AVERAGE->value ? $this->averageUnitCost($inboundLayers) : null;
            $applications = [];

            foreach ($inboundLayers as $inbound) {
                if ($remainingToApply <= 0.00000001) {
                    break;
                }

                $availableQuantity = max(0.0, (float) $inbound->remaining_quantity);
                if ($availableQuantity <= 0.00000001) {
                    continue;
                }

                $appliedQuantity = min($remainingToApply, $availableQuantity);
                $remainingAfter = $availableQuantity - $appliedQuantity;
                $unitCost = $averageUnitCost ?? $this->unitCostForLayer($inbound, $method);

                $application = ItemApplicationEntry::query()->firstOrCreate(
                    [
                        'idempotency_key' => $this->idempotencyKey($inbound, $outbound, $appliedQuantity),
                    ],
                    [
                        'inbound_item_ledger_entry_id' => $inbound->id,
                        'outbound_item_ledger_entry_id' => $outbound->id,
                        'applied_quantity' => DecimalMath::quantity($appliedQuantity),
                        'remaining_quantity_after_application' => DecimalMath::quantity($remainingAfter),
                        'application_date' => Carbon::parse($outbound->posting_date)->toDateString(),
                        'application_source' => $applicationSource ?? (string) ($outbound->document_type ?? 'ITEM_APPLICATION'),
                        'costing_method' => $method,
                        'unit_cost' => DecimalMath::unitCost($unitCost),
                        'cost_amount' => DecimalMath::amount($appliedQuantity * $unitCost),
                        'audit_metadata' => [
                            'outbound_document_type' => $outbound->document_type,
                            'outbound_document_number' => $outbound->document_number,
                            'inbound_document_type' => $inbound->document_type,
                            'inbound_document_number' => $inbound->document_number,
                        ],
                    ],
                );

                $inbound->forceFill([
                    'remaining_quantity' => DecimalMath::quantity($remainingAfter),
                    'open' => $remainingAfter > 0.00000001,
                ])->save();

                $applications[] = $application;
                $remainingToApply -= $appliedQuantity;
            }

            if ($remainingToApply > 0.00000001 && $strict) {
                throw InsufficientInventoryApplicationException::forOutboundEntry($outbound->entry_number);
            }

            $appliedCost = collect($applications)->sum(fn (ItemApplicationEntry $application): float => (float) $application->cost_amount);
            $updates = [
                'remaining_quantity' => 0,
                'open' => false,
            ];

            if ($appliedCost > 0.0) {
                $updates['cost_amount_actual'] = DecimalMath::amount($appliedCost);
                $updates['purchase_amount_actual'] = DecimalMath::amount($appliedCost);
            }

            $outbound->forceFill($updates)->save();

            return $applications;
        });
    }

    /**
     * @return Collection<int, ItemLedgerEntry>
     */
    private function candidateInboundLayers(ItemLedgerEntry $outbound, string $method): Collection
    {
        $query = ItemLedgerEntry::query()
            ->with('item')
            ->where('item_id', $outbound->item_id)
            ->where('remaining_quantity', '>', 0)
            ->where('id', '!=', $outbound->id)
            ->lockForUpdate();

        if ($outbound->location_id) {
            $query->where('location_id', $outbound->location_id);
        }

        if ($outbound->business_id !== null) {
            $query->where('business_id', $outbound->business_id);
        }

        if ($method === CostingMethod::SPECIFIC->value && $outbound->applied_entry_id) {
            return $query->where('id', $outbound->applied_entry_id)->get();
        }

        if ($method === CostingMethod::LIFO->value) {
            return $query->orderByDesc('posting_date')->orderByDesc('entry_number')->get();
        }

        return $query->orderBy('posting_date')->orderBy('entry_number')->get();
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
        if ($quantity <= 0.00000001) {
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
            if ($quantity <= 0.00000001) {
                continue;
            }

            $totalQuantity += $quantity;
            $totalCost += $quantity * $this->unitCostForLayer($layer, CostingMethod::FIFO->value);
        }

        return $totalQuantity > 0.00000001 ? $totalCost / $totalQuantity : 0.0;
    }

    private function idempotencyKey(ItemLedgerEntry $inbound, ItemLedgerEntry $outbound, float $appliedQuantity): string
    {
        return hash('sha256', implode('|', [
            'item-application',
            $inbound->id,
            $outbound->id,
            DecimalMath::quantity($appliedQuantity),
        ]));
    }
}
