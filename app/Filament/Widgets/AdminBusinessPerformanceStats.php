<?php

namespace App\Filament\Widgets;

use App\Models\Business;
use App\Services\Business\BusinessContextService;
use App\Services\Dashboard\FinanceDashboardService;
use Filament\Widgets\Concerns\InteractsWithPageFilters;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class AdminBusinessPerformanceStats extends BaseWidget
{
    use InteractsWithPageFilters;

    protected ?string $heading = 'PERIOD PERFORMANCE (LCY)';

    protected function getStats(): array
    {
        $filters = $this->filters ?? [];
        [$start, $end] = $this->period($filters['period'] ?? 'last_90_days');
        $businessId = filled($filters['company_code'] ?? null)
            ? Business::query()->where('code', $filters['company_code'])->value('id')
            : app(BusinessContextService::class)->resolveId();
        $businessId = app(BusinessContextService::class)->resolveId($businessId);
        $summary = app(FinanceDashboardService::class)->summary($start, $end, $businessId);
        $money = fn (float $value): string => 'LCY '.number_format($value, 2);

        return [
            Stat::make('Revenue', $money((float) $summary['revenue']))
                ->description('For selected period')
                ->color('success'),
            Stat::make('COGS', $money((float) $summary['cogs']))
                ->description('Cost of goods sold')
                ->color('warning'),
            Stat::make('Gross Profit', $money((float) $summary['gross_profit']))
                ->description('Gross Margin: '.$this->margin($summary['gross_margin_percent']))
                ->color($this->profitColor((float) $summary['gross_profit'])),
            Stat::make('Operating Expenses', $money((float) $summary['operating_expenses']))
                ->description('Operating expenses')
                ->color('warning'),
            Stat::make('Operating Profit', $money((float) $summary['operating_profit']))
                ->description('Operating Margin: '.$this->margin($summary['operating_margin_percent']))
                ->color($this->profitColor((float) $summary['operating_profit'])),
            Stat::make('Net Profit / Loss', $money((float) $summary['net_profit_loss']))
                ->description('Net Margin: '.$this->margin($summary['net_margin_percent']))
                ->color($this->profitColor((float) $summary['net_profit_loss'])),
        ];
    }

    private function period(string $period): array
    {
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

    private function margin(?float $value): string
    {
        return $value === null ? 'N/A' : number_format($value, 2).'%';
    }

    private function profitColor(float $value): string
    {
        return $value > 0 ? 'success' : ($value < 0 ? 'danger' : 'gray');
    }
}
