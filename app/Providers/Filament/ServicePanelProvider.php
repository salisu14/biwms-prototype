<?php

namespace App\Providers\Filament;

use App\Filament\Pages\MyAttendance;
use App\Filament\Resources\MaintenanceContracts\MaintenanceContractResource;
use App\Filament\Resources\MaintenanceContractSchedules\MaintenanceContractScheduleResource;
use App\Filament\Service\Pages\DispatchBoard;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
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

class ServicePanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->id('service')
            ->path('service')
            ->login()
            ->colors([
                'primary' => Color::Cyan,
            ])
            ->brandName('BIFLI Globals - Service Role Center')
            ->favicon(asset('favicon.ico'))
            ->resources([
                MaintenanceContractResource::class,
                MaintenanceContractScheduleResource::class,
            ])
            ->pages([
                DispatchBoard::class,
                MyAttendance::class,
            ])
            ->widgets([
                AccountWidget::class,
            ])
            ->navigationGroups([
                'Service Contracts',
                'Service Dispatch',
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
                'super_admin_2fa',
                'service',
            ]);
    }
}
