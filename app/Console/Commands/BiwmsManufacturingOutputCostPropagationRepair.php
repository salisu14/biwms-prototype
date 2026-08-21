<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\Manufacturing\ProductionOutputCostService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('biwms:manufacturing-output-cost-propagation-repair {--dry-run : Report stale output allocations without mutating data} {--apply : Reallocate deterministic stale output cost using the production output cost service} {--production-order= : Limit repair candidates to one production order ID or document number}')]
#[Description('Report or repair stale production output cost allocations after upstream manufacturing cost adjustments.')]
class BiwmsManufacturingOutputCostPropagationRepair extends Command
{
    public function __construct(
        private readonly ProductionOutputCostService $outputCostService,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $apply = (bool) $this->option('apply');
        $productionOrderFilter = $this->option('production-order');
        $result = $this->outputCostService->repairStaleOutputCostPropagations(
            apply: $apply,
            productionOrderFilter: $productionOrderFilter,
        );

        $this->info('BIWMS Manufacturing Output Cost Propagation Repair');
        $this->line($apply ? 'Mode: apply. Deterministic stale output allocations will be reallocated.' : 'Mode: dry-run. No data was changed.');

        if ($productionOrderFilter) {
            $this->line("Filter: production-order={$productionOrderFilter}");
        }

        $this->line("Candidates: {$result['scanned']}");
        $this->line("Repaired: {$result['repaired']}");

        foreach ($result['rows'] as $row) {
            $this->line(sprintf(
                '- status=%s production_order=%s order_status=%s settlement_status=%s total_accumulated=%0.4f allocated_output=%0.4f difference=%0.4f output_entry_count=%s allocation_ids=%s',
                $row['status'],
                $row['production_order_no'],
                $row['production_order_status'],
                $row['cost_settlement_status'],
                $row['total_accumulated_cost'],
                $row['allocated_output_cost'],
                $row['difference'],
                $row['output_entry_count'],
                $row['allocation_ids'] === [] ? 'pending' : implode(',', $row['allocation_ids']),
            ));
        }

        return self::SUCCESS;
    }
}
