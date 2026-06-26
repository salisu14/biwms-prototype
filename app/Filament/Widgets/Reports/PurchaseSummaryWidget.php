<?php

namespace App\Filament\Widgets\Reports;

use Filament\Support\Enums\IconPosition;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class PurchaseSummaryWidget extends BaseWidget
{
    protected int|string|array $columnSpan = 'full';

    protected ?string $pollingInterval = null;

    protected static bool $isLazy = false;

    public float $totalCost = 0;

    public int $totalTransactions = 0;

    public float $avgCost = 0;

    public int $groupsCount = 0;

    public string $dateRange = '';

    public array $costTrend = [];

    public array $transactionTrend = [];

    public array $averageTrend = [];

    public array $groupTrend = [];

    protected function getStats(): array
    {
        return [
            Stat::make('Total Costs', $this->money($this->totalCost))
                ->description("Across {$this->groupsCount} posting group(s)")
                ->descriptionIcon('heroicon-m-arrow-trending-down', IconPosition::Before)
                ->color('danger')
                ->chart($this->costTrend ?: [0])
                ->extraAttributes([
                    'class' => 'cursor-help',
                    'title' => 'Sum of all purchase cost amounts in the selected period',
                ]),

            Stat::make('Total Transactions', number_format($this->totalTransactions))
                ->description('Average: ' . $this->money($this->avgCost) . ' / transaction')
                ->descriptionIcon('heroicon-m-clipboard-document-list', IconPosition::Before)
                ->color('info')
                ->chart($this->transactionTrend ?: [0]),

            Stat::make('Average per Transaction', $this->money($this->avgCost))
                ->description($this->dateRange ?: 'Selected reporting period')
                ->descriptionIcon('heroicon-m-calculator', IconPosition::Before)
                ->color('warning')
                ->chart($this->averageTrend ?: [0]),

            Stat::make('Active Posting Groups', number_format($this->groupsCount))
                ->description('With purchase activity')
                ->descriptionIcon('heroicon-m-user-group', IconPosition::Before)
                ->color('primary')
                ->chart($this->groupTrend ?: [0]),
        ];
    }

    private function money(float|int|null $amount): string
    {
        return '₦' . number_format($amount ?? 0, 2);
    }
}
