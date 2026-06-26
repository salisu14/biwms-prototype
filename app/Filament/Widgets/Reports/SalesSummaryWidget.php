<?php

namespace App\Filament\Widgets\Reports;

use Filament\Support\Enums\IconPosition;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class SalesSummaryWidget extends BaseWidget
{
    protected int|string|array $columnSpan = 'full';

    protected ?string $pollingInterval = null;

    protected static bool $isLazy = false;

    public float $totalRevenue = 0;

    public int $totalTransactions = 0;

    public float $avgTransaction = 0;

    public int $groupsCount = 0;

    public string $dateRange = '';

    public array $revenueTrend = [];

    public array $transactionTrend = [];

    public array $averageTrend = [];

    public array $groupTrend = [];

    protected function getStats(): array
    {
        return [
            Stat::make('Total Revenue', $this->money($this->totalRevenue))
                ->description("{$this->groupsCount} posting group(s)")
                ->descriptionIcon('heroicon-m-arrow-trending-up', IconPosition::Before)
                ->color('success')
                ->chart($this->revenueTrend ?: [0]),

            Stat::make('Total Transactions', number_format($this->totalTransactions))
                ->description('Average: ' . $this->money($this->avgTransaction) . ' / transaction')
                ->descriptionIcon('heroicon-m-clipboard-document-list', IconPosition::Before)
                ->color('info')
                ->chart($this->transactionTrend ?: [0]),

            Stat::make('Avg Transaction Size', $this->money($this->avgTransaction))
                ->description($this->dateRange ?: 'Selected reporting period')
                ->descriptionIcon('heroicon-m-calculator', IconPosition::Before)
                ->color('warning')
                ->chart($this->averageTrend ?: [0]),

            Stat::make('Active Posting Groups', number_format($this->groupsCount))
                ->description('Contributing to revenue')
                ->descriptionIcon('heroicon-m-squares-2x2', IconPosition::Before)
                ->color('primary')
                ->chart($this->groupTrend ?: [0]),
        ];
    }

    private function money(float|int|null $amount): string
    {
        return '₦' . number_format($amount ?? 0, 2);
    }
}
