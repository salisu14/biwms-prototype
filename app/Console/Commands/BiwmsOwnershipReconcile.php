<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\Business\ProductionInventoryOwnershipAuditService;
use Illuminate\Console\Command;

class BiwmsOwnershipReconcile extends Command
{
    protected $signature = 'biwms:ownership-reconcile {--details} {--json}';

    protected $description = 'Report production and inventory business ownership gaps without modifying data';

    public function handle(ProductionInventoryOwnershipAuditService $audit): int
    {
        $report = $audit->report();

        if ($this->option('json')) {
            $this->line((string) json_encode($report, JSON_PRETTY_PRINT));

            return self::SUCCESS;
        }

        $this->info('Production and inventory ownership reconciliation');
        foreach ($report as $key => $value) {
            if ($key === 'historical_examples' && ! $this->option('details')) {
                continue;
            }

            is_array($value)
                ? $this->line($key.': '.json_encode($value))
                : $this->line($key.': '.$value);
        }

        return self::SUCCESS;
    }
}
