<?php

namespace App\Filament\Widgets\Concerns;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Schema;

trait AppliesAdminDashboardFilters
{
    /**
     * @return array{0: Carbon, 1: Carbon}
     */
    protected function getPeriodRange(array $filters): array
    {
        $period = $filters['period'] ?? 'last_90_days';
        $today = now()->endOfDay();

        return match ($period) {
            'this_month' => [now()->startOfMonth(), $today],
            'this_quarter' => [now()->startOfQuarter(), $today],
            'ytd' => [now()->startOfYear(), $today],
            'last_30_days' => [now()->subDays(30)->startOfDay(), $today],
            'last_180_days' => [now()->subDays(180)->startOfDay(), $today],
            default => [now()->subDays(90)->startOfDay(), $today],
        };
    }

    protected function applyCommonFilters(
        Builder $query,
        array $filters,
        string $dateColumn = 'created_at'
    ): Builder {
        [$startDate, $endDate] = $this->getPeriodRange($filters);

        $model = $query->getModel();
        $table = $model->getTable();

        if (Schema::hasColumn($table, $dateColumn)) {
            $query->whereBetween($table.'.'.$dateColumn, [$startDate, $endDate]);
        }

        $companyCode = $filters['company_code'] ?? null;
        $factoryCode = $filters['factory_code'] ?? null;

        if ($companyCode && Schema::hasColumn($table, 'shortcut_dimension_1_code')) {
            $query->where($table.'.shortcut_dimension_1_code', $companyCode);
        }

        if ($factoryCode && Schema::hasColumn($table, 'shortcut_dimension_2_code')) {
            $query->where($table.'.shortcut_dimension_2_code', $factoryCode);
        }

        return $query;
    }
}
