<?php

declare(strict_types=1);

namespace App\Services\Manufacturing;

use App\Models\Manufacturing\ProductionSchedule;

readonly class ProductionSchedulingResult
{
    /**
     * @param  array<int, array<string, mixed>>  $operationSchedules
     * @param  array<int, array<string, mixed>>  $exceptions
     * @param  array<int, array<string, mixed>>  $bottlenecks
     * @param  array<string, mixed>  $summary
     */
    public function __construct(
        public ?ProductionSchedule $schedule,
        public array $operationSchedules,
        public array $exceptions,
        public array $bottlenecks,
        public array $summary,
    ) {}
}
