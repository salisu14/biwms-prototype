<?php

namespace App\Providers\Filament;

use App\Filament\Resources\Items\ItemResource;
use App\Filament\Resources\WarehouseActivities\WarehouseActivityResource;
use App\Filament\Resources\WarehousePutaways\WarehousePutawayResource;
use App\Filament\Resources\WarehouseReceipts\WarehouseReceiptResource;
use App\Filament\Resources\WarehouseShipments\WarehouseShipmentResource;
use App\Filament\Warehouse\Widgets\WarehouseStatsOverview;
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

class WarehousePanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->id('warehouse')
            ->path('warehouse')
            ->login()
            ->colors([
                'primary' => Color::Amber,
            ])
            ->brandName('BIFLI Globals - Warehouse Role Center')
            ->favicon(asset('favicon.ico'))
            ->resources([
                WarehouseReceiptResource::class,
                WarehouseActivityResource::class,
                WarehousePutawayResource::class,
                WarehouseShipmentResource::class,
                ItemResource::class,
            ])
            ->pages([
                Dashboard::class,
            ])
            ->widgets([
                WarehouseStatsOverview::class,
                AccountWidget::class,
            ])
            ->navigationGroups([
                'Warehouse',
                'Inventory',
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
                'warehouse',
            ]);
    }
}
