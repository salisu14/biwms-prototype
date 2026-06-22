<?php

namespace App\Providers\Filament;

use App\Filament\Finance\Widgets\FinanceStatsOverview;
use App\Filament\Pages\Finance\BalanceSheetReport;
use App\Filament\Pages\Finance\CashFlowStatementReport;
use App\Filament\Pages\Finance\CustomerSubledgerSummary;
use App\Filament\Pages\Finance\DepreciationBookReport;
use App\Filament\Pages\Finance\FixedAssetLedgerEntries;
use App\Filament\Pages\Finance\FixedAssetListReport;
use App\Filament\Pages\Finance\GeneralJournals;
use App\Filament\Pages\Finance\GroupSummaryReport;
use App\Filament\Pages\Finance\ProfitAndLossReport;
use App\Filament\Pages\MyAttendance;
use App\Filament\Resources\AccountSchedules\AccountScheduleResource;
use App\Filament\Resources\BankAccounts\BankAccountResource;
use App\Filament\Resources\CurrencyAdjustmentLedgers\CurrencyAdjustmentLedgerResource;
use App\Filament\Resources\CustomerLedgerEntries\CustomerLedgerEntryResource;
use App\Filament\Resources\GeneralJournalBatches\GeneralJournalBatchResource;
use App\Filament\Resources\JournalLines\JournalLineResource;
use App\Filament\Resources\Payments\PaymentResource;
use App\Filament\Resources\SalesInvoices\SalesInvoiceResource;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Pages\Dashboard;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\View\PanelsRenderHook;
use Filament\Widgets\AccountWidget;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;

class FinancePanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->id('finance')
            ->path('finance')
            ->login()
            ->colors([
                'primary' => Color::Blue,
            ])
            ->spa(hasPrefetching: true)
            ->sidebarCollapsibleOnDesktop()
            ->globalSearch()
            ->globalSearchKeyBindings(['command+k', 'ctrl+k'])
            ->globalSearchFieldKeyBindingSuffix()
            ->brandName('BIFLI Globals - Finance Role Center')
            ->favicon(asset('favicon.ico'))
            ->renderHook(
                PanelsRenderHook::STYLES_AFTER,
                fn(): string => <<<'HTML'
                    <style>
                        html:not(.dark) .fi-body,
                        html:not(.dark) body {
                            background-color: rgb(243 244 246);
                        }

                        html.dark .fi-body,
                        html.dark body {
                            background-color: rgb(3 7 18);
                        }
                    </style>
                    HTML
            )
            ->resources([
                PaymentResource::class,
                BankAccountResource::class,
                SalesInvoiceResource::class,
                GeneralJournalBatchResource::class,
                JournalLineResource::class,
                CurrencyAdjustmentLedgerResource::class,
                CustomerLedgerEntryResource::class,
                AccountScheduleResource::class,
            ])
            ->pages([
                Dashboard::class,
                MyAttendance::class,
                GeneralJournals::class,
                ProfitAndLossReport::class,
                GroupSummaryReport::class,
                BalanceSheetReport::class,
                CashFlowStatementReport::class,
                CustomerSubledgerSummary::class,
                FixedAssetListReport::class,
                DepreciationBookReport::class,
                FixedAssetLedgerEntries::class,
            ])
            ->widgets([
                FinanceStatsOverview::class,
                AccountWidget::class,
            ])
            ->navigationGroups([
                'Finance',
                'Accounting',
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
                'finance',
            ]);
    }
}
