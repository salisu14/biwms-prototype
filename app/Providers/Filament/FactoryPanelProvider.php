<?php

namespace App\Providers\Filament;

use App\Filament\Factory\Widgets\FactoryStatsOverview;
use App\Filament\Pages\Manufacturing\ProductionPerformanceReport;
use App\Filament\Pages\Manufacturing\WipValuationReport;
use App\Filament\Pages\MyAttendance;
use App\Filament\Resources\Items\ItemResource;
use App\Filament\Resources\MachineCenters\MachineCenterResource;
use App\Filament\Resources\PriceLists\PriceListResource;
use App\Filament\Resources\ProductionBoms\ProductionBomResource;
use App\Filament\Resources\ProductionJournalBatches\ProductionJournalBatchResource;
use App\Filament\Resources\ProductionJournalTemplates\ProductionJournalTemplateResource;
use App\Filament\Resources\ProductionOrders\FinishedProductionOrderResource;
use App\Filament\Resources\ProductionOrders\ProductionOrderResource;
use App\Filament\Resources\ProductionOrders\ReleasedProductionOrderResource;
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
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
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
            ->brandName('BIFLI Globals - Factory Role Center')
            ->favicon(asset('favicon.ico'))
            ->spa(hasPrefetching: true)
            ->sidebarCollapsibleOnDesktop()
            ->globalSearch()
            ->globalSearchKeyBindings(['command+k', 'ctrl+k'])
            ->globalSearchFieldKeyBindingSuffix()
            ->brandName('BIFLI Group')
            ->resources([
                ProductionOrderResource::class,
                ReleasedProductionOrderResource::class,
                FinishedProductionOrderResource::class,
                ProductionBomResource::class,
                RoutingResource::class,
                MachineCenterResource::class,
                ProductionJournalBatchResource::class,
                ProductionJournalTemplateResource::class,
                ItemResource::class,
                PriceListResource::class,
            ])
            ->pages([
                Dashboard::class,
                MyAttendance::class,
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
                PreventRequestForgery::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])
            ->authMiddleware([
                Authenticate::class,
                'super_admin_2fa',
                'factory',
            ]);
    }
}
