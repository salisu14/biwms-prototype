<?php

declare(strict_types=1);

namespace App\Services\Manufacturing;

use App\Enums\ProductionOperationExecutionStatus;
use App\Models\CapacityLedgerEntry;
use App\Models\ItemLedgerEntry;
use App\Models\Manufacturing\ProductionOrder;

class ProductionProgressService
{
    /**
     * @return array<string, mixed>
     */
    public function forOrder(ProductionOrder $order): array
    {
        $executions = $order->operationExecutions()->with(['routingLine', 'qualityHolds'])->get();
        $plannedQuantity = (float) $order->quantity_base;
        $goodQuantity = (float) $executions->sum('good_quantity');
        $scrapQuantity = (float) $executions->sum('scrap_quantity');
        $reworkQuantity = (float) $executions->sum('rework_quantity');
        $ledgerOutput = (float) ItemLedgerEntry::query()
            ->where('source_type', ProductionOrder::class)
            ->where('source_id', $order->id)
            ->where('quantity', '>', 0)
            ->sum('quantity');
        $capacitySeconds = (int) CapacityLedgerEntry::query()
            ->where('production_order_id', $order->id)
            ->get()
            ->sum(fn (CapacityLedgerEntry $entry): int => (int) round(((float) $entry->setup_time + (float) $entry->run_time) * 3600));

        return [
            'planned_quantity' => $plannedQuantity,
            'good_quantity' => $goodQuantity,
            'scrap_quantity' => $scrapQuantity,
            'rework_quantity' => $reworkQuantity,
            'remaining_quantity' => max(0, $plannedQuantity - $goodQuantity),
            'good_output_percent' => $plannedQuantity > 0 ? round(($goodQuantity / $plannedQuantity) * 100, 2) : 0.0,
            'scrap_percent' => ($goodQuantity + $scrapQuantity) > 0 ? round(($scrapQuantity / ($goodQuantity + $scrapQuantity)) * 100, 2) : 0.0,
            'setup_seconds' => (int) $executions->sum('setup_seconds'),
            'run_seconds' => (int) $executions->sum('run_seconds'),
            'labour_seconds' => (int) $executions->sum('labour_seconds'),
            'machine_seconds' => (int) $executions->sum('machine_seconds'),
            'downtime_seconds' => (int) $executions->sum('downtime_seconds'),
            'ledger_output_quantity' => $ledgerOutput,
            'capacity_ledger_seconds' => $capacitySeconds,
            'current_operation' => $executions
                ->reject(fn ($execution): bool => in_array($execution->status, [
                    ProductionOperationExecutionStatus::Posted,
                    ProductionOperationExecutionStatus::Cancelled,
                    ProductionOperationExecutionStatus::Reversed,
                ], true))
                ->sortBy('routingLine.line_number')
                ->first()?->routingLine?->operation_no,
            'active_quality_holds' => $executions->sum(fn ($execution): int => $execution->qualityHolds->where('status', 'active')->count()),
        ];
    }
}
