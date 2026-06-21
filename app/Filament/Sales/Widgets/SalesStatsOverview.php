<?php

namespace App\Filament\Sales\Widgets;

use App\Models\Customer;
use App\Models\SalesOrder;
use App\Models\SalesQuote;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class SalesStatsOverview extends BaseWidget
{
    protected function getStats(): array
    {
        return [
            Stat::make('Open Quotes', SalesQuote::whereIn('status', ['draft', 'sent', 'accepted'])->count())
                ->description('Pending customer approval')
                ->descriptionIcon('heroicon-m-arrow-trending-up')
                ->color('warning'),

            Stat::make('Orders to Ship', SalesOrder::whereIn('status', ['RELEASED', 'PICKING', 'PACKED', 'PARTIALLY_SHIPPED'])
                ->where('requested_delivery_date', '<=', now()->addDays(3))
                ->count())
                ->description('Due within 3 days')
                ->color('danger'),

            Stat::make('New Customers (MTD)', Customer::whereMonth('created_at', now()->month)->count())
                ->description('This month')
                ->color('success'),
        ];
    }
}
