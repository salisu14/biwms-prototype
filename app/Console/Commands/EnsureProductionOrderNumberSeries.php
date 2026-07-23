<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\Manufacturing\ProductionOrderNumberSeriesSetupService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('biwms:production-order-series-setup')]
#[Description('Ensure the canonical production order number series exists without resetting counters.')]
class EnsureProductionOrderNumberSeries extends Command
{
    public function handle(ProductionOrderNumberSeriesSetupService $service): int
    {
        $result = $service->ensure();

        $this->info(sprintf(
            'Production Order Number Series %s: series %s, line %s, last number preserved as %s.',
            $result['code'],
            $result['series_created'] ? 'created' : 'found',
            $result['line_created'] ? 'created' : 'found',
            $result['last_no_used'] ?? 'none',
        ));

        return self::SUCCESS;
    }
}
