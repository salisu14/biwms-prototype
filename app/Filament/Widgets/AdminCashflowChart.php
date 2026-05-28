<?php

namespace App\Filament\Widgets;

use App\Filament\Widgets\Concerns\AppliesAdminDashboardFilters;
use App\Models\Payment;
use Filament\Widgets\ChartWidget;
use Filament\Widgets\Concerns\InteractsWithPageFilters;

class AdminCashflowChart extends ChartWidget
{
    use AppliesAdminDashboardFilters;
    use InteractsWithPageFilters;

    protected ?string $heading = 'Cashflow Snapshot';

    protected function getData(): array
    {
        $filters = $this->filters ?? [];
        [$startDate, $endDate] = $this->getPeriodRange($filters);

        $months = collect(range(0, max(0, $startDate->diffInMonths($endDate))))
            ->map(fn (int $offset) => $startDate->copy()->startOfMonth()->addMonths($offset))
            ->filter(fn ($month) => $month->lessThanOrEqualTo($endDate))
            ->values();

        $labels = $months->map(fn ($month) => $month->format('M Y'))->all();

        $receipts = $months->map(fn ($month) => (float) $this->applyCommonFilters(Payment::query(), $filters, 'payment_date')
            ->where('payment_direction', 'RECEIPT')
            ->whereYear('payment_date', $month->year)
            ->whereMonth('payment_date', $month->month)
            ->sum('payment_amount'))->all();

        $disbursements = $months->map(fn ($month) => (float) $this->applyCommonFilters(Payment::query(), $filters, 'payment_date')
            ->where('payment_direction', 'DISBURSEMENT')
            ->whereYear('payment_date', $month->year)
            ->whereMonth('payment_date', $month->month)
            ->sum('payment_amount'))->all();

        return [
            'datasets' => [
                [
                    'label' => 'Receipts',
                    'data' => $receipts,
                    'backgroundColor' => '#16a34a',
                ],
                [
                    'label' => 'Disbursements',
                    'data' => $disbursements,
                    'backgroundColor' => '#dc2626',
                ],
            ],
            'labels' => $labels,
        ];
    }

    protected function getType(): string
    {
        return 'bar';
    }
}
