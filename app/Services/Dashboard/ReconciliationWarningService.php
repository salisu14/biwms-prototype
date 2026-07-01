<?php

namespace App\Services\Dashboard;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;

class ReconciliationWarningService
{
    /**
     * @return array{
     *     finance: array{total: int, critical: int, warning: int, info: int, sections: array<string, int>},
     *     inventory: array{total: int, critical: int, warning: int, info: int, sections: array<string, int>}
     * }
     */
    public function summary(): array
    {
        return Cache::remember('dashboard.reconciliation_warnings', now()->addMinutes(5), fn (): array => [
            'finance' => $this->financeWarnings(),
            'inventory' => $this->inventoryWarnings(),
        ]);
    }

    /**
     * @return array{total: int, critical: int, warning: int, info: int, sections: array<string, int>}
     */
    public function financeWarnings(): array
    {
        return $this->warningCountsFromCommand('biwms:finance-reconcile');
    }

    /**
     * @return array{total: int, critical: int, warning: int, info: int, sections: array<string, int>}
     */
    public function inventoryWarnings(): array
    {
        return $this->warningCountsFromCommand('biwms:inventory-reconcile');
    }

    /**
     * @return array{total: int, critical: int, warning: int, info: int, sections: array<string, int>}
     */
    private function warningCountsFromCommand(string $command): array
    {
        Artisan::call($command, ['--json' => true]);

        $report = json_decode(trim(Artisan::output()), true);

        if (! is_array($report)) {
            return $this->emptyCounts();
        }

        $counts = $this->emptyCounts();

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
     * @return array{total: int, critical: int, warning: int, info: int, sections: array<string, int>}
     */
    private function emptyCounts(): array
    {
        return [
            'total' => 0,
            'critical' => 0,
            'warning' => 0,
            'info' => 0,
            'sections' => [],
        ];
    }
}
