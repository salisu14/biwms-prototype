<?php

namespace App\Filament\Finance\Widgets;

use App\Models\Payment;
use App\Models\SalesInvoice;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class FinanceStatsOverview extends BaseWidget
{
    protected function getStats(): array
    {
        $pendingInvoices = SalesInvoice::query()
            ->whereIn('status', ['pending', 'approved'])
            ->count();

        $overdueInvoices = SalesInvoice::query()
            ->whereDate('due_date', '<', now())
            ->whereNotIn('status', ['posted', 'paid', 'cancelled'])
            ->count();

        $paymentsToday = Payment::query()
            ->whereDate('created_at', now())
            ->count();

        return [
            Stat::make('Pending Invoices', $pendingInvoices)
                ->description('Awaiting posting or payment')
                ->color('warning'),
            Stat::make('Overdue Invoices', $overdueInvoices)
                ->description('Due date passed')
                ->color('danger'),
            Stat::make('Payments Today', $paymentsToday)
                ->description('Captured today')
                ->color('success'),
        ];
    }
}
