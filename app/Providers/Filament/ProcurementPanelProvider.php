<?php

namespace App\Providers\Filament;

use App\Filament\Pages\MyAttendance;
use App\Filament\Procurement\Widgets\ProcurementStatsOverview;
use App\Filament\Resources\BlanketPurchaseOrders\BlanketPurchaseOrderResource;
use App\Filament\Resources\PurchaseCreditMemos\PurchaseCreditMemoResource;
use App\Filament\Resources\PurchaseInvoices\PurchaseInvoiceResource;
use App\Filament\Resources\PurchaseOrders\PurchaseOrderResource;
use App\Filament\Resources\PurchaseQuotes\PurchaseQuoteResource;
use App\Filament\Resources\PurchaseReceipts\PurchaseReceiptResource;
use App\Filament\Resources\Vendors\VendorResource;
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

class ProcurementPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->id('procurement')
            ->path('procurement')
            ->login()
            ->colors([
                'primary' => Color::Teal,
            ])
            ->brandName('BIFLI Globals - Procurement Role Center')
            ->favicon(asset('favicon.ico'))
            ->resources([
                PurchaseQuoteResource::class,
                PurchaseOrderResource::class,
                PurchaseReceiptResource::class,
                PurchaseInvoiceResource::class,
                PurchaseCreditMemoResource::class,
                BlanketPurchaseOrderResource::class,
                VendorResource::class,
            ])
            ->pages([
                Dashboard::class,
                MyAttendance::class,
            ])
            ->widgets([
                ProcurementStatsOverview::class,
                AccountWidget::class,
            ])
            ->navigationGroups([
                'Purchases',
                'Purchasing',
                'Master Data',
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
                'procurement',
            ]);
    }
}
