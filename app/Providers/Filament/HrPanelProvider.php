<?php

namespace App\Providers\Filament;

use App\Filament\Hr\Widgets\HrStatsOverview;
use App\Filament\Resources\Employees\EmployeeResource;
use App\Filament\Resources\PayCodes\PayCodeResource;
use App\Filament\Resources\PayrollDocuments\PayrollDocumentResource;
use App\Filament\Resources\PayrollPostingGroups\PayrollPostingGroupResource;
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

class HrPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->id('hr')
            ->path('hr')
            ->login()
            ->colors([
                'primary' => Color::Fuchsia,
            ])
            ->brandName('BIFLI Globals - HR Role Center')
            ->favicon(asset('favicon.ico'))
            ->resources([
                EmployeeResource::class,
                PayrollDocumentResource::class,
                PayrollPostingGroupResource::class,
                PayCodeResource::class,
            ])
            ->pages([
                Dashboard::class,
            ])
            ->widgets([
                HrStatsOverview::class,
                AccountWidget::class,
            ])
            ->navigationGroups([
                'Human Resources',
                'Payroll',
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
                'hr',
            ]);
    }
}
