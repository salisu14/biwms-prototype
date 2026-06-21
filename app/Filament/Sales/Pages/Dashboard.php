<?php

namespace App\Filament\Sales\Pages;

use App\Filament\Sales\Widgets\OpenQuotesWidget;
use App\Filament\Sales\Widgets\OrdersToShipWidget;
use App\Filament\Sales\Widgets\RecentCustomersWidget;
use App\Filament\Sales\Widgets\SalesStatsOverview;
use Filament\Pages\Dashboard as BaseDashboard;
use Filament\Panel;

class Dashboard extends BaseDashboard
{
    protected static string|null|\BackedEnum $navigationIcon = 'heroicon-o-home';

    protected static ?string $navigationLabel = 'Role Center';

    protected static ?string $title = 'Sales Role Center';

    public static function getRoutePath(Panel $panel): string
    {
        return '/';
    }

    public function getWidgets(): array
    {
        return [
            SalesStatsOverview::class,
            OpenQuotesWidget::class,
            OrdersToShipWidget::class,
            RecentCustomersWidget::class,
        ];
    }
}
