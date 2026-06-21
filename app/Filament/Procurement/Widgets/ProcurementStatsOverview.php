<?php

namespace App\Filament\Procurement\Widgets;

use App\Enums\PurchaseOrderStatus;
use App\Enums\PurchaseQuoteStatus;
use App\Models\PurchaseInvoice;
use App\Models\PurchaseOrder;
use App\Models\PurchaseQuote;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class ProcurementStatsOverview extends BaseWidget
{
    protected function getStats(): array
    {
        return [
            Stat::make('Open Quotes', PurchaseQuote::query()->where('status', PurchaseQuoteStatus::OPEN)->count())
                ->description('Quotes in negotiation')
                ->color('warning'),
            Stat::make('Orders Awaiting Receipt', PurchaseOrder::query()->whereIn('status', [
                PurchaseOrderStatus::APPROVED,
                PurchaseOrderStatus::PARTIALLY_RECEIVED,
            ])->count())
                ->description('Approved and not fully received')
                ->color('info'),
            Stat::make('Open Vendor Invoices', PurchaseInvoice::query()->open()->count())
                ->description('Not fully settled')
                ->color('success'),
        ];
    }
}
