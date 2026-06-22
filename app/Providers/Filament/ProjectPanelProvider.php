<?php

namespace App\Providers\Filament;

use App\Filament\Pages\MyAttendance;
use App\Filament\Project\Widgets\ProjectStatsOverview;
use App\Filament\Resources\CapExProjects\CapExProjectResource;
use App\Filament\Resources\PurchaseOrders\PurchaseOrderResource;
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

class ProjectPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->id('project')
            ->path('project')
            ->login()
            ->colors([
                'primary' => Color::Indigo,
            ])
            ->spa(hasPrefetching: true)
            ->sidebarCollapsibleOnDesktop()
            ->globalSearch()
            ->globalSearchKeyBindings(['command+k', 'ctrl+k'])
            ->globalSearchFieldKeyBindingSuffix()
            ->brandName('BIFLI Globals - Project Role Center')
            ->favicon(asset('favicon.ico'))
            ->resources([
                CapExProjectResource::class,
                PurchaseOrderResource::class,
            ])
            ->pages([
                Dashboard::class,
                MyAttendance::class,
            ])
            ->widgets([
                ProjectStatsOverview::class,
                AccountWidget::class,
            ])
            ->navigationGroups([
                'Projects',
                'Purchases',
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
                'project',
            ]);
    }
}
