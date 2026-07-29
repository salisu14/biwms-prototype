<?php

declare(strict_types=1);

namespace App\Services\Manufacturing;

use App\Models\Manufacturing\ProductionOrder;
use App\Models\ProductionOutputCostAllocation;
use App\Models\ProductionVarianceCalculation;
use App\Models\ValueEntry;
use Illuminate\Support\Facades\Schema;

class ProductionCostSummaryService
{
    /**
     * @return array<string, float|bool|null>
     */
    public function summarize(ProductionOrder $order): array
    {
        $order->refresh();

        $valueEntries = ValueEntry::query()
            ->where(function ($query) use ($order): void {
                $query->where('production_order_no', $order->document_number)
                    ->orWhere(function ($nested) use ($order): void {
                        $nested->where('source_module', 'manufacturing')
                            ->where('source_id', $order->id);
                    });
            })
            ->get()
            ->reject(fn (ValueEntry $entry): bool => in_array((string) $entry->value_entry_state, ['reversed', 'cleared'], true));

        $expectedMaterial = $this->sumExpected($valueEntries, ['expected_direct_material', 'direct_material', 'material']);
        $actualMaterial = $this->sumActual($valueEntries, ['direct_material', 'material']);
        $expectedCapacity = $this->sumExpected($valueEntries, ['expected_direct_capacity', 'direct_capacity', 'capacity']);
        $actualCapacity = $this->sumActual($valueEntries, ['direct_capacity', 'capacity']);
        $expectedOverhead = $this->sumExpected($valueEntries, ['expected_capacity_overhead', 'capacity_overhead', 'overhead', 'material_overhead']);
        $actualOverhead = $this->sumActual($valueEntries, ['capacity_overhead', 'overhead', 'material_overhead']);
        $expectedOutput = $this->sumExpected($valueEntries, ['expected_output', 'output']);
        $actualOutput = $this->sumActual($valueEntries, ['output']);
        $variance = $this->sumActual($valueEntries, [
            'variance',
            'material_price_variance',
            'material_quantity_variance',
            'capacity_rate_variance',
            'capacity_efficiency_variance',
            'capacity_overhead_variance',
            'output_quantity_variance',
            'rounding_variance',
            'standard_cost_variance',
        ]);
        $allocatedOutputCost = Schema::hasTable('production_output_cost_allocations')
            ? (float) ProductionOutputCostAllocation::query()
                ->where('production_order_id', $order->id)
                ->sum('allocated_total_cost')
            : 0.0;
        $totalAccumulatedCost = $actualMaterial + $actualCapacity + $actualOverhead;
        $varianceCalculations = ProductionVarianceCalculation::query()
            ->where('production_order_id', $order->id)
            ->get();
        $unclearedExpectedCost = (float) $valueEntries
            ->where('expected_cost', true)
            ->where('value_entry_state', 'expected')
            ->sum('cost_amount_expected');
        $postedAdjustments = (float) $valueEntries
            ->where('value_entry_state', 'adjustment')
            ->where('gl_posted', true)
            ->sum('cost_amount_actual');
        $pendingAdjustments = (float) $valueEntries
            ->where('value_entry_state', 'adjustment')
            ->where('gl_posted', false)
            ->sum('cost_amount_actual');
        $standardOutputCost = $this->sumActual($valueEntries, ['output']);

        return [
            'expected_material_cost' => round($expectedMaterial, 4),
            'actual_material_cost' => round($actualMaterial, 4),
            'expected_capacity_cost' => round($expectedCapacity, 4),
            'actual_capacity_cost' => round($actualCapacity, 4),
            'expected_overhead_cost' => round($expectedOverhead, 4),
            'actual_overhead_cost' => round($actualOverhead, 4),
            'total_accumulated_cost' => round($totalAccumulatedCost, 4),
            'allocated_output_cost' => round($allocatedOutputCost, 4),
            'unallocated_cost' => round($totalAccumulatedCost - $allocatedOutputCost, 4),
            'expected_output_cost' => round($expectedOutput, 4),
            'actual_output_cost' => round($actualOutput, 4),
            'standard_output_cost' => round($standardOutputCost, 4),
            'material_price_variance' => round((float) $varianceCalculations->where('variance_type', 'material_price')->sum('variance_amount'), 4),
            'material_quantity_variance' => round((float) $varianceCalculations->where('variance_type', 'material_quantity')->sum('variance_amount'), 4),
            'capacity_rate_variance' => round((float) $varianceCalculations->where('variance_type', 'capacity_rate')->sum('variance_amount'), 4),
            'capacity_efficiency_variance' => round((float) $varianceCalculations->where('variance_type', 'capacity_efficiency')->sum('variance_amount'), 4),
            'capacity_overhead_variance' => round((float) $varianceCalculations->where('variance_type', 'capacity_overhead')->sum('variance_amount'), 4),
            'variance' => round($variance, 4),
            'total_production_variance' => round($variance + (float) $varianceCalculations->sum('variance_amount'), 4),
            'uncleared_expected_cost' => round($unclearedExpectedCost, 4),
            'pending_adjustments' => round($pendingAdjustments, 4),
            'posted_adjustments' => round($postedAdjustments, 4),
            'cost_posted_to_gl' => round((float) $valueEntries->where('gl_posted', true)->sum(fn (ValueEntry $entry): float => abs((float) $entry->cost_amount_actual) + abs((float) $entry->cost_amount_expected)), 4),
            'cost_not_posted_to_gl' => round((float) $valueEntries->where('gl_posted', false)->sum(fn (ValueEntry $entry): float => abs((float) $entry->cost_amount_actual) + abs((float) $entry->cost_amount_expected)), 4),
            'settlement_status' => $order->cost_settlement_status?->value ?? $order->cost_settlement_status,
            'settlement_classification' => $order->cost_settlement_classification?->value ?? $order->cost_settlement_classification,
            'last_expected_cost_calculation_date' => optional($order->expectedCostSnapshots()->latest('calculated_at')->first())->calculated_at,
            'last_settlement_date' => $order->cost_settled_at,
            'last_adjustment_date' => optional($valueEntries->where('value_entry_state', 'adjustment')->sortByDesc('created_at')->first())->created_at,
            'is_settled' => $order->cost_settled_at !== null,
        ];
    }

    private function sumActual($entries, array $components): float
    {
        return (float) $entries
            ->whereIn('cost_component', $components)
            ->where('expected_cost', false)
            ->sum('cost_amount_actual');
    }

    private function sumExpected($entries, array $components): float
    {
        return (float) $entries
            ->whereIn('cost_component', $components)
            ->sum('cost_amount_expected');
    }
}
