<?php

namespace App\Filament\Widgets;

use App\Filament\Widgets\Concerns\AppliesAdminDashboardFilters;
use App\Models\Manufacturing\ProductionOrder;
use App\Models\PurchaseOrder;
use App\Models\SalesOrder;
use Filament\Widgets\ChartWidget;
use Filament\Widgets\Concerns\InteractsWithPageFilters;

class AdminOpsTrendChart extends ChartWidget
{
    use AppliesAdminDashboardFilters;
    use InteractsWithPageFilters;

    protected ?string $heading = 'Operations Volume Trend';

    protected int|string|array $columnSpan = 'full';

    protected function getData(): array
    {
        $filters = $this->filters ?? [];
        [$startDate, $endDate] = $this->getPeriodRange($filters);

        $months = collect();
        $cursor = $startDate->copy()->startOfMonth();
        $finalMonth = $endDate->copy()->endOfMonth();

        while ($cursor->lessThanOrEqualTo($finalMonth)) {
            $months->push($cursor->copy());
            $cursor->addMonth();
        }

        $labels = $months->map(fn ($month) => $month->format('M Y'))->all();

        $sales = $months->map(fn ($month) => $this->applyCommonFilters(SalesOrder::query(), $filters)
            ->whereYear('created_at', $month->year)
            ->whereMonth('created_at', $month->month)
            ->count())->all();

        $purchases = $months->map(fn ($month) => $this->applyCommonFilters(PurchaseOrder::query(), $filters)
            ->whereYear('created_at', $month->year)
            ->whereMonth('created_at', $month->month)
            ->count())->all();

        $production = $months->map(fn ($month) => $this->applyCommonFilters(ProductionOrder::query(), $filters)
            ->whereYear('created_at', $month->year)
            ->whereMonth('created_at', $month->month)
            ->count())->all();

        return [
            'datasets' => [
                [
                    'label' => 'Sales Orders',
                    'data' => $sales,
                    'borderColor' => '#2563eb',
                    'backgroundColor' => 'rgba(37, 99, 235, 0.15)',
                ],
                [
                    'label' => 'Purchase Orders',
                    'data' => $purchases,
                    'borderColor' => '#f59e0b',
                    'backgroundColor' => 'rgba(245, 158, 11, 0.15)',
                ],
                [
                    'label' => 'Production Orders',
                    'data' => $production,
                    'borderColor' => '#10b981',
                    'backgroundColor' => 'rgba(16, 185, 129, 0.15)',
                ],
            ],
            'labels' => $labels,
        ];
    }

    protected function getType(): string
    {
        return 'line';
    }
}
