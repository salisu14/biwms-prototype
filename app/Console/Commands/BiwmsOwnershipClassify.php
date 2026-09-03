<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\Business\ProductionInventoryOwnershipAuditService;
use Illuminate\Console\Command;

class BiwmsOwnershipClassify extends Command
{
    protected $signature = 'biwms:ownership-classify {--json}';

    protected $description = 'Classify historical production and inventory ownership without writing rows';

    public function handle(ProductionInventoryOwnershipAuditService $audit): int
    {
        $report = $audit->report();
        $output = [
            'mode' => 'dry-run',
            'writes' => 0,
            'classifications' => [
                'CONFIRMED' => 0,
                'AMBIGUOUS' => 0,
                'UNKNOWN' => ($report['production_orders_without_business'] ?? 0)
                    + ($report['item_ledger_entries_without_business'] ?? 0)
                    + ($report['capacity_ledger_entries_without_business'] ?? 0),
            ],
            'evidence' => $report['historical_examples'] ?? [],
        ];

        $this->line((string) json_encode($output, $this->option('json') ? JSON_PRETTY_PRINT : 0));

        return self::SUCCESS;
    }
}
