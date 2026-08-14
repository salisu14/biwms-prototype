<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\Inventory\CostAdjustmentService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('biwms:manufacturing-cost-adjustment-propagation-repair {--dry-run : Report missing manufacturing cost-adjustment companions without mutating data} {--apply : Create deterministic missing manufacturing cost-adjustment companions} {--production-order= : Limit repair candidates to one production order ID or document number}')]
#[Description('Report or repair missing manufacturing late material cost-adjustment propagation Value Entries.')]
class BiwmsManufacturingCostAdjustmentPropagationRepair extends Command
{
    public function __construct(
        private readonly CostAdjustmentService $costAdjustmentService,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $apply = (bool) $this->option('apply');
        $productionOrderFilter = $this->option('production-order');
        $result = $this->costAdjustmentService->repairMissingManufacturingCostAdjustmentPropagations(
            apply: $apply,
            productionOrderFilter: $productionOrderFilter,
        );

        $this->info('BIWMS Manufacturing Cost Adjustment Propagation Repair');
        $this->line($apply ? 'Mode: apply. Deterministic missing companions will be created.' : 'Mode: dry-run. No data was changed.');

        if ($productionOrderFilter) {
            $this->line("Filter: production-order={$productionOrderFilter}");
        }

        $this->line("Candidates: {$result['scanned']}");
        $this->line("Repaired: {$result['repaired']}");

        foreach ($result['rows'] as $row) {
            $this->line(sprintf(
                '- status=%s batch=%s production_order=%s consumption_ile_id=%s generic_ve=%s amount=%0.4f companion_ve=%s',
                $row['status'],
                $row['batch_number'],
                $row['production_order_no'],
                $row['consumption_item_ledger_entry_id'],
                $row['generic_value_entry_no'],
                $row['adjustment_amount'],
                $row['companion_value_entry_id'] ?? 'pending',
            ));
        }

        return self::SUCCESS;
    }
}
