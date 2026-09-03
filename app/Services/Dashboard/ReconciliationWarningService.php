<?php

namespace App\Services\Dashboard;

use App\Services\Business\BusinessContextService;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;

class ReconciliationWarningService
{
    public function __construct(private readonly BusinessContextService $businessContext) {}

    /**
     * @return array{
     *     finance: array{total: int, critical: int, warning: int, info: int, sections: array<string, int>, scope: string},
     *     inventory: array{total: int, critical: int, warning: int, info: int, sections: array<string, int>, scope: string}
     * }
     */
    public function summary(?int $businessId = null): array
    {
        $businessId = $this->businessContext->resolveId($businessId);
        $cacheKey = 'dashboard.reconciliation_warnings.'.($businessId ?? 'global');

        return Cache::remember($cacheKey, now()->addMinutes(5), fn (): array => [
            'finance' => $this->financeWarnings($businessId),
            'inventory' => $this->inventoryWarnings($businessId),
        ]);
    }

    /**
     * @return array{total: int, critical: int, warning: int, info: int, sections: array<string, int>}
     */
    public function financeWarnings(?int $businessId = null): array
    {
        return $this->warningCountsFromCommand('biwms:finance-reconcile', $businessId);
    }

    /**
     * @return array{total: int, critical: int, warning: int, info: int, sections: array<string, int>}
     */
    public function inventoryWarnings(?int $businessId = null): array
    {
        return $this->warningCountsFromCommand('biwms:inventory-reconcile', $businessId);
    }

    /**
     * @return array{total: int, critical: int, warning: int, info: int, sections: array<string, int>, scope: string}
     */
    private function warningCountsFromCommand(string $command, ?int $businessId = null): array
    {
        Artisan::call($command, ['--json' => true]);

        $report = json_decode(trim(Artisan::output()), true);

        if (! is_array($report)) {
            return $this->emptyCounts($businessId);
        }

        $counts = $this->emptyCounts($businessId);

        foreach ($report as $section => $rows) {
            if (! is_array($rows) || ! array_is_list($rows)) {
                continue;
            }

            $sectionCount = count($rows);
            $counts['sections'][$section] = $sectionCount;
            $counts['total'] += $sectionCount;

            foreach ($rows as $row) {
                $severity = is_array($row) ? (string) ($row['severity'] ?? 'warning') : 'warning';

                if (! array_key_exists($severity, $counts)) {
                    $severity = 'warning';
                }

                $counts[$severity]++;
            }
        }

        return $counts;
    }

    /**
     * @return array{total: int, critical: int, warning: int, info: int, sections: array<string, int>, scope: string}
     */
    private function emptyCounts(?int $businessId = null): array
    {
        return [
            'total' => 0,
            'critical' => 0,
            'warning' => 0,
            'info' => 0,
            'sections' => [],
            // The current reconciliation commands are global. Keep this explicit
            // so a business dashboard does not present global findings as scoped.
            'scope' => 'global',
        ];
    }
}
