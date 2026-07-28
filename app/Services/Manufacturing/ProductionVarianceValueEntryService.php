<?php

declare(strict_types=1);

namespace App\Services\Manufacturing;

use App\Enums\ItemLedgerEntryType;
use App\Enums\ManufacturingCostComponent;
use App\Models\ItemLedgerEntry;
use App\Models\Manufacturing\ProductionOrder;
use App\Models\ValueEntry;
use App\Services\Inventory\ValueEntryAccountingOrchestrator;
use App\Support\DecimalMath;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class ProductionVarianceValueEntryService
{
    public function __construct(
        private readonly ValueEntryAccountingOrchestrator $accountingOrchestrator,
    ) {}

    public function recordVariance(
        ProductionOrder $order,
        float $varianceAmount,
        ManufacturingCostComponent $component,
        mixed $postingDate,
        ?int $userId = null,
        ?string $reason = null
    ): ?ValueEntry {
        if (! $component->isVariance()) {
            throw new RuntimeException('Production variance value entries require a variance cost component.');
        }

        if (abs($varianceAmount) <= 0.0001) {
            return null;
        }

        return DB::transaction(function () use ($order, $varianceAmount, $component, $postingDate, $userId, $reason): ValueEntry {
            /** @var ProductionOrder $lockedOrder */
            $lockedOrder = ProductionOrder::query()
                ->with(['item', 'location'])
                ->lockForUpdate()
                ->findOrFail($order->id);

            $outputEntry = $lockedOrder->itemLedgerEntries()
                ->where('entry_type', ItemLedgerEntryType::OUTPUT)
                ->orderByDesc('id')
                ->first();

            if (! $outputEntry instanceof ItemLedgerEntry) {
                throw new RuntimeException("Production order {$lockedOrder->document_number} has no output item ledger entry for variance posting.");
            }

            $idempotencyKey = hash('sha256', implode('|', [
                'production-variance',
                $lockedOrder->id,
                $component->value,
                DecimalMath::amount($varianceAmount),
            ]));

            $existing = ValueEntry::query()->where('idempotency_key', $idempotencyKey)->first();
            if ($existing) {
                return $existing;
            }

            /** @var ValueEntry $valueEntry */
            $valueEntry = ValueEntry::query()->create([
                'entry_no' => (ValueEntry::max('entry_no') ?? 0) + 1,
                'item_ledger_entry_no' => $outputEntry->entry_number,
                'item_ledger_entry_type' => 7,
                'item_no' => (string) ($lockedOrder->item?->item_code ?? $lockedOrder->item_id),
                'location_code' => (string) ($lockedOrder->location_code ?? $lockedOrder->location?->code ?? 'MAIN'),
                'posting_date' => $postingDate,
                'valuation_date' => $postingDate,
                'document_type' => 'PRODUCTION_VARIANCE',
                'document_no' => $lockedOrder->document_number,
                'document_line_no' => $outputEntry->document_line_number,
                'description' => $reason ?? $component->label(),
                'quantity' => 0,
                'invoiced_quantity' => 0,
                'valued_quantity' => 0,
                'remaining_quantity' => 0,
                'cost_component' => $component->value,
                'value_entry_state' => 'variance',
                'cost_amount_actual' => DecimalMath::amount($varianceAmount),
                'cost_amount_actual_acy' => DecimalMath::amount($varianceAmount),
                'variance_amount' => DecimalMath::amount($varianceAmount),
                'material_variance_amount' => str_contains($component->value, 'material') ? DecimalMath::amount($varianceAmount) : 0,
                'capacity_variance_amount' => str_contains($component->value, 'capacity_') && ! str_contains($component->value, 'overhead') ? DecimalMath::amount($varianceAmount) : 0,
                'capacity_overhead_variance_amount' => $component === ManufacturingCostComponent::CapacityOverheadVariance ? DecimalMath::amount($varianceAmount) : 0,
                'manufacturing_overhead_variance_amount' => in_array($component, [ManufacturingCostComponent::CapacityOverheadVariance, ManufacturingCostComponent::Variance], true) ? DecimalMath::amount($varianceAmount) : 0,
                'unit_cost' => 0,
                'unit_cost_acy' => 0,
                'source_type' => ProductionOrder::class,
                'source_module' => 'manufacturing',
                'source_id' => $lockedOrder->id,
                'source_number' => $lockedOrder->document_number,
                'source_no' => $lockedOrder->document_number,
                'production_order_no' => $lockedOrder->document_number,
                'production_order_line_no' => (string) $outputEntry->document_line_number,
                'prod_order_line_item_no' => $lockedOrder->item?->item_code,
                'expected_cost' => false,
                'idempotency_key' => $idempotencyKey,
                'accounting_metadata' => [
                    'phase_1d_production_variance' => true,
                    'variance_component' => $component->value,
                    'variance_amount' => $varianceAmount,
                    'output_item_ledger_entry_id' => $outputEntry->id,
                ],
                'user_id' => $userId ? (string) $userId : null,
            ]);

            $this->accountingOrchestrator->post($valueEntry);

            return $valueEntry->fresh();
        });
    }
}
