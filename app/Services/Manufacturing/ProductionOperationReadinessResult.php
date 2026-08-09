<?php

declare(strict_types=1);

namespace App\Services\Manufacturing;

use App\Enums\ProductionOperationDependencyReadiness;

final readonly class ProductionOperationReadinessResult
{
    /**
     * @param  array<int, array<string, mixed>>  $findings
     */
    public function __construct(
        public ProductionOperationDependencyReadiness $classification,
        public bool $ready,
        public array $findings = [],
    ) {}

    public function reason(): string
    {
        if ($this->ready) {
            return 'All inter-order operation dependencies are satisfied.';
        }

        return (string) ($this->findings[0]['reason'] ?? 'Operation is blocked by an upstream dependency.');
    }
}
