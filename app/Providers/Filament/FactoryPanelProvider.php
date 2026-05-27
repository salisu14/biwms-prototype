<?php

namespace App\Providers\Filament;

use App\Filament\Factory\Widgets\FactoryStatsOverview;
use App\Filament\Pages\Manufacturing\ProductionPerformanceReport;
use App\Filament\Pages\Manufacturing\WipValuationReport;
use App\Filament\Resources\Items\ItemResource;
use App\Filament\Resources\MachineCenters\MachineCenterResource;
use App\Filament\Resources\ProductionBoms\ProductionBomResource;
use App\Filament\Resources\ProductionOrders\ProductionOrderResource;
use App\Filament\Resources\Routings\RoutingResource;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Pages\Dashboard;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\Widgets\AccountWidget;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;

class FactoryPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->id('factory')
            ->path('factory')
            ->login()
            ->colors([
                'primary' => Color::Orange,
            ])
            ->brandName('Factory Role Center')
            ->favicon(asset('favicon.ico'))
            ->resources([
                ProductionOrderResource::class,
                ProductionBomResource::class,
                RoutingResource::class,
                MachineCenterResource::class,
                ItemResource::class,
            ])
            ->pages([
                Dashboard::class,
                ProductionPerformanceReport::class,
                WipValuationReport::class,
            ])
            ->widgets([
                FactoryStatsOverview::class,
                AccountWidget::class,
            ])
            ->navigationGroups([
                'Manufacturing',
                'Master Data',
                'Reports',
            ])
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                VerifyCsrfToken::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])
            ->authMiddleware([
                Authenticate::class,
                'factory',
            ]);
    }
}
