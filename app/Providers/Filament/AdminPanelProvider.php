<?php

namespace App\Providers\Filament;

use App\Filament\Pages\AdminDashboard;
use App\Filament\Pages\Finance\CashFlowStatementReport;
use App\Filament\Pages\Finance\DepreciationBookReport;
use App\Filament\Pages\Finance\ExpenseReport;
use App\Filament\Pages\Finance\FixedAssetListReport;
use App\Filament\Pages\Finance\GeneralJournals;
use App\Filament\Pages\Finance\ItemLedgerSummary;
use App\Filament\Pages\Finance\PurchaseStatisticsReport;
use App\Filament\Pages\Finance\SalesStatisticsReport;
use App\Filament\Pages\FiscalYearManagement;
use App\Filament\Pages\PurchaseHistory;
use App\Filament\Pages\SalesHistory;
use App\Filament\Pages\UserSecurity;
use App\Filament\Resources\EmployeeIdCardHistories\EmployeeIdCardHistoryResource;
use App\Filament\Resources\EmployeeIdCardPrintBatches\EmployeeIdCardPrintBatchResource;
use App\Filament\Resources\EmployeeIdCards\EmployeeIdCardResource;
use App\Filament\Resources\EmployeeIdCardTemplates\EmployeeIdCardTemplateResource;
use App\Filament\Resources\EmployeeIdCardVerificationLogs\EmployeeIdCardVerificationLogResource;
use App\Filament\Resources\MaintenanceContractAssets\MaintenanceContractAssetResource;
use App\Filament\Resources\MaintenanceContractBillings\MaintenanceContractBillingResource;
use App\Filament\Resources\MaintenanceContracts\MaintenanceContractResource;
use App\Filament\Resources\MaintenanceContractSchedules\MaintenanceContractScheduleResource;
use App\Http\Middleware\AddSecurityHeaders;
use App\Http\Middleware\EnforceAdminAbsoluteSessionLifetime;
use App\Http\Middleware\EnforceAdminIdleTimeout;
use App\Http\Middleware\SetActiveBusinessContext;
use App\Models\Business;
use Filament\Actions\Action;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Navigation\NavigationBuilder;
use Filament\Navigation\NavigationGroup;
use Filament\Navigation\NavigationItem;
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

class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('admin')
            ->path('admin')
            ->login()
            ->passwordReset()
            ->profile()
            ->colors([
                'primary' => Color::Amber,
            ])
            ->strictAuthorization()
            ->spa(hasPrefetching: true)
            ->sidebarCollapsibleOnDesktop()
            ->globalSearch()
            ->globalSearchKeyBindings(['command+k', 'ctrl+k'])
            ->globalSearchFieldKeyBindingSuffix()
            ->brandName('BIFLI Group')
            ->favicon(asset('favicon.ico'))
            ->userMenuItems([
                Action::make('two_factor_auth')
                    ->label('Two-Factor Authentication')
                    ->icon('heroicon-o-shield-check')
                    ->url(fn(): string => UserSecurity::getUrl()),
            ])
            ->renderHook(
                PanelsRenderHook::TOPBAR_START,
                function (): string {
                    $businesses = Business::query()
                        ->where('is_active', true)
                        ->orderBy('name')
                        ->get(['id', 'code', 'name']);

                    if ($businesses->isEmpty()) {
                        return '';
                    }

                    return view('filament.components.topbar-business-switcher', [
                        'businesses' => $businesses,
                        'activeBusinessId' => (int)session('active_business_id', 0),
                    ])->render();
                }
            )
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
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\Filament\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\Filament\Pages')
            ->pages([
                AdminDashboard::class,
            ])
            ->navigation(function (NavigationBuilder $builder): NavigationBuilder {
                return $builder
                    // Dashboard
                    ->items([
                        NavigationItem::make('Dashboard')
                            ->icon('heroicon-o-home')
                            ->url('/admin')
                            ->isActiveWhen(fn() => request()->is('admin')),
                    ])

                    // Accounting Module
                    ->group(
                        NavigationGroup::make('Accounting')
//                            ->icon('heroicon-o-calculator')
                            ->items([
                                // Chart of Accounts
                                NavigationItem::make('Chart of Accounts')
                                    ->icon('heroicon-o-list-bullet')
                                    ->visible(fn() => auth()->user()?->can('chart_of_account.manage') ?? false)
                                    ->url('/admin/chart-of-accounts')
                                    ->isActiveWhen(fn() => request()->is('admin/chart-of-accounts*')),

                                // Account Schedules
                                NavigationItem::make('Account Schedules')
                                    ->icon('heroicon-o-calendar-date-range')
                                    ->visible(fn() => auth()->user()?->can('chart_of_account.manage') ?? false)
                                    ->url('/admin/account-schedules')
                                    ->isActiveWhen(fn() => request()->is('admin/account-schedules*')),

                                // Allocation
                                NavigationItem::make('Allocation')
                                    ->icon('heroicon-o-viewfinder-circle')
                                    ->url('/admin/allocations')
                                    ->isActiveWhen(fn() => request()->is('admin/allocations*')),

                                // VAT & Tax
                                NavigationItem::make('VAT & Tax Setup')
                                    ->icon('heroicon-o-receipt-percent')
                                    ->url('/admin/vat-masters')
                                    ->isActiveWhen(fn() => request()->is('admin/vat-masters*')),

                                // Approval Templates
                                NavigationItem::make('Approval Templates')
                                    ->icon('heroicon-o-document-duplicate')
                                    ->url('/admin/approval-templates')
                                    ->isActiveWhen(fn() => request()->is('admin/approval-templates*')),

                                // Posting Setups & Posting Groups
                                NavigationItem::make('Posting Groups')
                                    ->icon('heroicon-o-squares-2x2')
                                    ->visible(fn() => auth()->user()?->can('posting_setup.manage') ?? false)
                                    ->url('/admin/posting-groups')
                                    ->isActiveWhen(fn() => request()->is('admin/posting-groups*')),

                                // Bank Accounts
                                NavigationItem::make('Bank Accounts')
                                    ->icon('heroicon-o-banknotes')
                                    ->visible(fn() => auth()->user()?->can('finance.bank_account.view_any') ?? false)
                                    ->url('/admin/bank-accounts')
                                    ->isActiveWhen(fn() => request()->is('admin/bank-accounts*')),

                                // Bank Account Ledger Entries
                                NavigationItem::make('Bank Account Ledger Entries')
                                    ->icon('heroicon-o-banknotes')
                                    ->visible(fn() => auth()->user()?->can('finance.bank_account.view_any') ?? false)
                                    ->url('/admin/bank-account-ledger-entries')
                                    ->isActiveWhen(fn() => request()->is('admin/bank-account-ledger-entries*')),

                                // Petty Cash
                                NavigationItem::make('Petty Cash Funds')
                                    ->icon('heroicon-o-currency-pound')
                                    ->url('/admin/petty-cash-funds')
                                    ->isActiveWhen(fn() => request()->is('admin/petty-cash-funds*')),

                                // Petty Cash
                                NavigationItem::make('Petty Cash Vouchers')
                                    ->icon('heroicon-o-currency-euro')
                                    ->visible(fn() => auth()->user()?->can('finance.petty_cash_voucher.view_any') ?? false)
                                    ->url('/admin/petty-cash-vouchers')
                                    ->isActiveWhen(fn() => request()->is('admin/petty-cash-vouchers*')),

                                // Petty Cash Transactions
                                NavigationItem::make('Petty Cash Transactions')
                                    ->icon('heroicon-o-arrows-right-left')
                                    ->url('/admin/petty-cash-transactions')
                                    ->isActiveWhen(fn() => request()->is('admin/petty-cash-transactions*')),

                                // Payments
                                NavigationItem::make('Payments')
                                    ->icon('heroicon-o-credit-card')
                                    ->visible(fn() => auth()->user()?->can('finance.payment.view_any') ?? false)
                                    ->url('/admin/payments')
                                    ->isActiveWhen(fn() => request()->is('admin/payments*')),

                                NavigationItem::make('Fiscal Year Management')
                                    ->icon('heroicon-o-calendar-days')
                                    ->visible(fn() => auth()->user()?->hasRole('super_admin'))
                                    ->url(FiscalYearManagement::getUrl())
                                    ->isActiveWhen(fn() => request()->is('admin/fiscal-year-management*')),

                                NavigationItem::make('Value Entries')
                                    ->icon('heroicon-o-currency-dollar')
                                    ->url('/admin/value-entries')
                                    ->isActiveWhen(fn() => request()->is('admin/value-entries*')),

                                // Inventory Valuation Report
                                NavigationItem::make('Inventory Valuation Report')
                                    ->icon('heroicon-o-presentation-chart-line')
                                    ->url('/admin/inventory-valuation-report')
                                    ->isActiveWhen(fn() => request()->is('admin/inventory-valuation-report*')),
                            ])
                    )
                    // Finance Module
                    ->group(
                        NavigationGroup::make('Finance')
                            ->items([
                                NavigationItem::make('Expenses')
                                    ->icon('heroicon-o-banknotes')
                                    ->url('/admin/expense-transactions')
                                    ->isActiveWhen(fn() => request()->is('admin/expense-transaction*')),

                                NavigationItem::make('Expense Report')
                                    ->icon('heroicon-o-banknotes')
                                    ->url(ExpenseReport::getUrl())
                                    ->isActiveWhen(fn() => request()->is('admin/expense-report*')),

                                NavigationItem::make('Expenses Categories')
                                    ->icon('heroicon-o-tag')
                                    ->url('/admin/expense-categories')
                                    ->isActiveWhen(fn() => request()->is('admin/expense-categorie*')),

                                NavigationItem::make('Currencies')
                                    ->icon('heroicon-o-currency-dollar')
                                    ->url('/admin/currencies')
                                    ->isActiveWhen(fn() => request()->is('admin/currencies*')),

                                NavigationItem::make('Currency Adjustments')
                                    ->icon('heroicon-o-arrows-right-left')
                                    ->url('/admin/currency-adjustment-ledgers')
                                    ->isActiveWhen(fn() => request()->is('admin/currency-adjustment-ledger*')),

                                NavigationItem::make('Customer Price Overrides')
                                    ->icon('heroicon-o-adjustments-horizontal')
                                    ->url('/admin/customer-price-overrides')
                                    ->isActiveWhen(fn() => request()->is('admin/customer-price-overrides*')),

                                NavigationItem::make('Price Change Templates')
                                    ->icon('heroicon-o-calculator')
                                    ->url('/admin/price-change-templates')
                                    ->isActiveWhen(fn() => request()->is('admin/price-change-templates*')),

                                NavigationItem::make('Pricing Groups')
                                    ->icon('heroicon-o-squares-2x2')
                                    ->url('/admin/pricing-groups')
                                    ->isActiveWhen(fn() => request()->is('admin/pricing-groups*')),

                                NavigationItem::make('Pricing Masters')
                                    ->icon('heroicon-o-document-text')
                                    ->url('/admin/pricing-masters')
                                    ->isActiveWhen(fn() => request()->is('admin/pricing-masters*')),

                                NavigationItem::make('Price Lists')
                                    ->icon('heroicon-o-list-bullet')
                                    ->url('/admin/price-lists')
                                    ->isActiveWhen(fn() => request()->is('admin/price-lists*')),

                                NavigationItem::make('Pricing Master Quantity Breaks')
                                    ->icon('heroicon-o-magnifying-glass-plus')
                                    ->url('/admin/pricing-master-quantity-breaks')
                                    ->isActiveWhen(fn() => request()->is('admin/pricing-master-quantity-breaks*')),

                                NavigationItem::make('Discount Rules')
                                    ->icon('heroicon-o-minus-circle')
                                    ->url('/admin/discount-rules')
                                    ->isActiveWhen(fn() => request()->is('admin/discount-rules*')),

                                NavigationItem::make('CapEx Projects')
                                    ->icon('heroicon-o-briefcase')
                                    ->url('/admin/capex-projects')
                                    ->isActiveWhen(fn() => request()->is('admin/capex-projects*')),

                                NavigationItem::make('Fixed Assets')
                                    ->icon('heroicon-o-cube')
                                    ->visible(fn() => auth()->user()?->can('fixed_asset.view_any') ?? false)
                                    ->url('/admin/fixed-assets')
                                    ->isActiveWhen(fn() => request()->is('admin/fixed-assets*')),

                                NavigationItem::make('Depreciation Books')
                                    ->icon('heroicon-o-book-open')
                                    ->url('/admin/depreciation-books')
                                    ->isActiveWhen(fn() => request()->is('admin/depreciation-books*')),

                                NavigationItem::make('FA Classes')
                                    ->icon('heroicon-o-squares-2x2')
                                    ->url('/admin/f-a-classes')
                                    ->isActiveWhen(fn() => request()->is('admin/f-a-classes*')),

                                // Dimensions
                                NavigationItem::make('Dimensions')
                                    ->icon('heroicon-o-squares-2x2')
                                    ->url('/admin/dimensions')
                                    ->isActiveWhen(fn() => request()->is('admin/dimensions*')),

                                // Payment Terms
                                NavigationItem::make('Payment Terms')
                                    ->icon('heroicon-o-calendar-days')
                                    ->url('/admin/payment-terms')
                                    ->isActiveWhen(fn() => request()->is('admin/payment-terms*')),

                                // Units of Measure
                                NavigationItem::make('Units of Measure')
                                    ->icon('heroicon-o-scale')
                                    ->url('/admin/unit-of-measures')
                                    ->isActiveWhen(fn() => request()->is('admin/unit-of-measures*')),

                                // Number Series
                                NavigationItem::make('Number Series')
                                    ->icon('heroicon-o-hashtag')
                                    ->visible(fn() => auth()->user()?->can('number_series.manage') ?? false)
                                    ->url('/admin/number-series')
                                    ->isActiveWhen(fn() => request()->is('admin/number-series*')),

                                NavigationItem::make('Profit & Loss Report')
                                    ->icon('heroicon-o-document-chart-bar')
                                    ->isActiveWhen(fn() => request()->is('admin/profit-and-loss-report*'))
                                    ->url('/admin/profit-and-loss-report'),

                                NavigationItem::make('Group Summary / Trial Balance')
                                    ->icon('heroicon-o-table-cells')
                                    ->isActiveWhen(fn() => request()->is('admin/group-summary-report*'))
                                    ->url('/admin/group-summary-report'),

                                NavigationItem::make('Balance Sheet')
                                    ->icon('heroicon-o-scale')
                                    ->isActiveWhen(fn() => request()->is('admin/balance-sheet-report*'))
                                    ->url('/admin/balance-sheet-report'),

                                NavigationItem::make('Cash Flow Statement')
                                    ->icon('heroicon-o-arrows-right-left')
                                    ->isActiveWhen(fn() => request()->is('admin/cash-flow-statement-report*'))
                                    ->url(CashFlowStatementReport::getUrl()),

                                NavigationItem::make('Fixed Asset List')
                                    ->icon('heroicon-o-clipboard-document-list')
                                    ->isActiveWhen(fn() => request()->is('admin/fixed-asset-list-report*'))
                                    ->url(FixedAssetListReport::getUrl()),

                                NavigationItem::make('Depreciation Book Report')
                                    ->icon('heroicon-o-document-chart-bar')
                                    ->isActiveWhen(fn() => request()->is('admin/depreciation-book-report*'))
                                    ->url(DepreciationBookReport::getUrl()),

                                NavigationItem::make('Sales Statistics')
                                    ->icon('heroicon-o-chart-bar')
//                                    ->url('/admin/sales-statistics')
                                    ->url(SalesStatisticsReport::getUrl())
                                    ->isActiveWhen(fn() => request()->is('admin/sales-statistics*'))
                                    ->sort(10),

                                NavigationItem::make('Purchase Statistics')
                                    ->icon('heroicon-o-shopping-cart')
                                    ->url(PurchaseStatisticsReport::getUrl())
                                    ->isActiveWhen(fn() => request()->is('admin/purchase-statistics*'))
                                    ->sort(11),

                                NavigationItem::make('WIP Valuation')
                                    ->icon('heroicon-o-currency-dollar')
                                    ->url('/admin/wip-valuation-report')
                                    ->isActiveWhen(fn() => request()->is('admin/wip-valuation-report*')),

                                NavigationItem::make('Yield Report')
                                    ->icon('heroicon-o-beaker')
                                    ->url('/admin/yield-report')
                                    ->isActiveWhen(fn() => request()->is('admin/yield-report*')),

                                NavigationItem::make('Item Ledger Entries')
                                    ->icon('heroicon-o-book-open')
                                    ->url(ItemLedgerSummary::getUrl())
                                    ->isActiveWhen(fn() => request()->is('admin/item-ledger-entries*') || request()->is('admin/item-ledger-summary*')),

                                NavigationItem::make('Journals')
                                    ->icon('heroicon-o-book-open')
                                    ->url(GeneralJournals::getUrl())
                                    ->isActiveWhen(fn() => request()->routeIs('filament.admin.pages.journals')),
                            ])
                    )
                    // Purchasing Module
                    ->group(
                        NavigationGroup::make('Purchasing')
//                            ->icon('heroicon-o-shopping-cart')
                            ->items([
                                NavigationItem::make('Vendors')
                                    ->icon('heroicon-o-building-storefront')
                                    ->url('/admin/vendors')
                                    ->isActiveWhen(fn() => request()->is('admin/vendors*')),

                                NavigationItem::make('Vendor Ledger Entries')
                                    ->icon('heroicon-o-banknotes')
                                    ->url('/admin/vendor-ledger-entries')
                                    ->isActiveWhen(fn() => request()->is('admin/vendor-ledger-entries*')),

                                NavigationItem::make('Vendor Invoices')
                                    ->icon('heroicon-o-document-currency-dollar')
                                    ->url('/admin/vendor-invoices')
                                    ->isActiveWhen(fn() => request()->is('admin/vendor-invoices*')),

                                NavigationItem::make('Vendor Items')
                                    ->icon('heroicon-o-cube')
                                    ->url('/admin/vendor-items')
                                    ->isActiveWhen(fn() => request()->is('admin/vendor-items*')),

                                NavigationItem::make('Raw & Packaging Materials')
                                    ->icon('heroicon-o-cube')
                                    ->url('/admin/items/rm/raw-materials')
                                    ->isActiveWhen(fn() => request()->is('admin/items/rm/raw-materials*')),

                                NavigationItem::make('Purchase Quotes')
                                    ->icon('heroicon-o-document-text')
                                    ->url('/admin/purchase-quotes')
                                    ->isActiveWhen(fn() => request()->is('admin/purchase-quotes*')),

                                NavigationItem::make('Blanket Orders')
                                    ->icon('heroicon-o-archive-box-arrow-down')
                                    ->url('/admin/blanket-purchase-orders')
                                    ->isActiveWhen(fn() => request()->is('admin/blanket-purchase-orders*')),

                                NavigationItem::make('Purchase Orders')
                                    ->icon('heroicon-o-clipboard-document-list')
                                    ->url('/admin/purchase-orders')
                                    ->isActiveWhen(fn() => request()->is('admin/purchase-orders*')),

                                NavigationItem::make('Purchase Invoices')
                                    ->icon('heroicon-o-document-currency-dollar')
                                    ->url('/admin/purchase-invoices')
                                    ->isActiveWhen(fn() => request()->is('admin/purchase-invoices*')),

                                NavigationItem::make('Purchase Credit Memos')
                                    ->icon('heroicon-o-arrow-uturn-left')
                                    ->url('/admin/posted-purchase-credit-memos')
                                    ->isActiveWhen(fn() => request()->is('admin/posted-purchase-credit-memos*')),

                                NavigationItem::make('Purchase Receipts')
                                    ->icon('heroicon-o-receipt-percent')
                                    ->url('/admin/purchase-receipts')
                                    ->isActiveWhen(fn() => request()->is('admin/purchase-receipts*')),

                                NavigationItem::make('Item Charges')
                                    ->icon('heroicon-o-tag')
                                    ->url('/admin/item-charges')
                                    ->isActiveWhen(fn() => request()->is('admin/item-charges*')),

                                NavigationItem::make('Purchases History')
                                    ->icon('heroicon-o-document-check')
                                    ->url(PurchaseHistory::getUrl())
                                    ->isActiveWhen(fn() => request()->routeIs('filament.admin.pages.purchase-history')),
                            ])
                    )

                    // Sales Module
                    ->group(
                        NavigationGroup::make('Sales')
//                            ->icon('heroicon-o-currency-dollar')
                            ->items([
                                NavigationItem::make('Customer Groups')
                                    ->icon('heroicon-o-users')
                                    ->url('/admin/customer-groups')
                                    ->isActiveWhen(fn() => request()->is('admin/customer-groups*')),

                                NavigationItem::make('Customers')
                                    ->icon('heroicon-o-users')
                                    ->url('/admin/customers')
                                    ->isActiveWhen(fn() => request()->is('admin/customers*')),

                                NavigationItem::make('Customer Contacts')
                                    ->icon('heroicon-o-user-group')
                                    ->url('/admin/customer-contacts')
                                    ->isActiveWhen(fn() => request()->is('admin/customer-contacts*')),

                                NavigationItem::make('Finished Goods')
                                    ->icon('heroicon-o-check-badge')
                                    ->url('/admin/items/fg/finished-goods')
                                    ->isActiveWhen(fn() => request()->is('admin/items/fg/finished-goods*')),

                                NavigationItem::make('Sales Orders')
                                    ->icon('heroicon-o-document-text')
                                    ->url('/admin/sales-orders')
                                    ->isActiveWhen(fn() => request()->is('admin/sales-orders*')),

                                NavigationItem::make('Sales Quotes')
                                    ->icon('heroicon-o-document-text')
                                    ->url('/admin/sales-quotes')
                                    ->isActiveWhen(fn() => request()->is('admin/sales-quotes*')),

                                NavigationItem::make('Blanket Orders')
                                    ->icon('heroicon-o-archive-box-arrow-down')
                                    ->url('/admin/blanket-sales-orders')
                                    ->isActiveWhen(fn() => request()->is('admin/blanket-sales-orders*')),

                                NavigationItem::make('Sales Quote Revisions')
                                    ->icon('heroicon-o-arrow-path')
                                    ->url('/admin/sales-quote-revisions')
                                    ->isActiveWhen(fn() => request()->is('admin/sales-quote-revisions*')),

                                NavigationItem::make('Sales Invoices')
                                    ->icon('heroicon-o-document-currency-dollar')
                                    ->url('/admin/sales-invoices')
                                    ->isActiveWhen(fn() => request()->is('admin/sales-invoices*')),

                                NavigationItem::make('Sales Credit Memos')
                                    ->icon('heroicon-o-arrow-uturn-left')
                                    ->url('/admin/sales-credit-memos')
                                    ->isActiveWhen(fn() => request()->is('admin/sales-credit-memos*')),

                                // Shipping
                                NavigationItem::make('Shipping Agents')
                                    ->icon('heroicon-o-truck')
                                    ->url('/admin/shipping-agents')
                                    ->isActiveWhen(fn() => request()->is('admin/shipping-agents*')),

                                NavigationItem::make('Shipment Methods')
                                    ->icon('heroicon-o-paper-airplane')
                                    ->url('/admin/shipment-methods')
                                    ->isActiveWhen(fn() => request()->is('admin/shipment-methods*')),

                                NavigationItem::make('History')
                                    ->icon('heroicon-o-document-check')
                                    ->url(SalesHistory::getUrl())
                                    ->isActiveWhen(fn() => request()->routeIs('filament.admin.pages.sales-history')),
                            ])
                    )

                    // Inventory & Warehouse
                    ->
                    group(
                        NavigationGroup::make('Inventory')
//                            ->icon('heroicon-o-cube')
                            ->items([
                                NavigationItem::make('Items')
                                    ->icon('heroicon-o-cube')
                                    ->url('/admin/items')
                                    ->isActiveWhen(fn() => request()->is('admin/items*')),

                                NavigationItem::make('Categories')
                                    ->icon('heroicon-o-tag')
                                    ->url('/admin/categories')
                                    ->isActiveWhen(fn() => request()->is('admin/categories*')),

                                NavigationItem::make('Item Categories')
                                    ->icon('heroicon-o-tag')
                                    ->url('/admin/item-category-assignments')
                                    ->isActiveWhen(fn() => request()->is('admin/item-category-assignments*')),

                                NavigationItem::make('Item UOM Assignments')
                                    ->icon('heroicon-o-arrows-up-down')
                                    ->url('/admin/item-uom-assignments')
                                    ->isActiveWhen(fn() => request()->is('admin/item-uom-assignments*')),

                                NavigationItem::make('Locations')
                                    ->icon('heroicon-o-map-pin')
                                    ->url('/admin/locations')
                                    ->isActiveWhen(fn() => request()->is('admin/locations*')),

                                NavigationItem::make('Zones')
                                    ->icon('heroicon-o-map')
                                    ->url('/admin/zones')
                                    ->isActiveWhen(fn() => request()->is('admin/zones*')),

                                NavigationItem::make('Bins')
                                    ->icon('heroicon-o-inbox')
                                    ->url('/admin/bins')
                                    ->isActiveWhen(fn() => request()->is('admin/bins*')),

                                NavigationItem::make('Work Center Groups')
                                    ->icon('heroicon-o-squares-2x2')
                                    ->url('/admin/work-center-groups')
                                    ->isActiveWhen(fn() => request()->is('admin/work-center-groups*')),

                                NavigationItem::make('Work Centers')
                                    ->icon('heroicon-o-cog-6-tooth')
                                    ->url('/admin/work-centers')
                                    ->isActiveWhen(fn() => request()->is('admin/work-centers*')),

                                NavigationItem::make('Warehouse Receipts')
                                    ->icon('heroicon-o-arrow-down-tray')
                                    ->url('/admin/warehouse-receipts')
                                    ->isActiveWhen(fn() => request()->is('admin/warehouse-receipts*')),

                                NavigationItem::make('Warehouse Activities')
                                    ->icon('heroicon-o-arrows-right-left')
                                    ->url('/admin/warehouse-activities')
                                    ->isActiveWhen(fn() => request()->is('admin/warehouse-activities*')),

                                NavigationItem::make('Warehouse Entry')
                                    ->icon('heroicon-o-arrow-right-end-on-rectangle')
                                    ->url('/admin/warehouse-entries')
                                    ->isActiveWhen(fn() => request()->is('admin/warehouse-entries*')),

                                NavigationItem::make('Warehouse Setups')
                                    ->icon('heroicon-o-cog-6-tooth')
                                    ->url('/admin/warehouse-setups')
                                    ->isActiveWhen(fn() => request()->is('admin/warehouse-setups*')),

                                NavigationItem::make('Putaway Worksheets')
                                    ->icon('heroicon-o-arrow-path')
                                    ->url('/admin/putaway-worksheets')
                                    ->isActiveWhen(fn() => request()->is('admin/putaway-worksheets*')),

                                NavigationItem::make('Inventory Putaways')
                                    ->icon('heroicon-o-arrow-down-on-square')
                                    ->url('/admin/inventory-putaways')
                                    ->isActiveWhen(fn() => request()->is('admin/inventory-putaways*')),

                                NavigationItem::make('Warehouse Putaways')
                                    ->icon('heroicon-o-arrow-top-right-on-square')
                                    ->url('/admin/warehouse-putaways')
                                    ->isActiveWhen(fn() => request()->is('admin/warehouse-putaways*')),

                                NavigationItem::make('Warehouse Shipments')
                                    ->icon('heroicon-o-arrow-up-tray')
                                    ->url('/admin/warehouse-shipments')
                                    ->isActiveWhen(fn() => request()->is('admin/warehouse-shipments*')),

                                NavigationItem::make('Picks')
                                    ->icon('heroicon-o-hand-raised')
                                    ->url('/admin/picks')
                                    ->isActiveWhen(fn() => request()->is('admin/picks*')),

                                NavigationItem::make('Inventory Adjustments')
                                    ->icon('heroicon-o-adjustments-vertical')
                                    ->url('/admin/inventory-adjustment-journals')
                                    ->isActiveWhen(fn() => request()->is('admin/inventory-adjustment-journals*')),

                                NavigationItem::make('Reason Codes')
                                    ->icon('heroicon-o-tag')
                                    ->url('/admin/reason-codes')
                                    ->isActiveWhen(fn() => request()->is('admin/reason-codes*')),

                                NavigationItem::make('Item Tracking Codes')
                                    ->icon('heroicon-o-tag')
                                    ->url('/admin/item-tracking-codes')
                                    ->isActiveWhen(fn() => request()->is('admin/item-tracking-codes*')),

                                NavigationItem::make('Item Ledger Entries')
                                    ->icon('heroicon-o-book-open')
                                    ->url(ItemLedgerSummary::getUrl())
                                    ->isActiveWhen(fn() => request()->is('admin/item-ledger-entries*') || request()->is('admin/item-ledger-summary*')),

                                NavigationItem::make('Physical Inventories')
                                    ->icon('heroicon-o-clipboard')
                                    ->url('/admin/physical-inventory-journals')
                                    ->isActiveWhen(fn() => request()->is('admin/physical-inventory-journals*')),

                                NavigationItem::make('Inventory Valuation Report')
                                    ->icon('heroicon-o-presentation-chart-line')
                                    ->url('/admin/inventory-valuation-report')
                                    ->isActiveWhen(fn() => request()->is('admin/inventory-valuation-report*')),
                            ])
                    )

                    // Manufacturing
                    ->group(
                        NavigationGroup::make('Manufacturing')
//                            ->icon('heroicon-o-cog-6-tooth')
                            ->items([
                                NavigationItem::make('Production Orders')
                                    ->icon('heroicon-o-cog-6-tooth')
                                    ->url('/admin/production-orders')
                                    ->isActiveWhen(fn() => request()->is('admin/production-orders*')),

                                NavigationItem::make('Released Production Orders')
                                    ->icon('heroicon-o-play')
                                    ->url('/admin/released-production-orders')
                                    ->isActiveWhen(fn() => request()->is('admin/released-production-orders*')),

                                NavigationItem::make('Finished Production Orders')
                                    ->icon('heroicon-o-check-circle')
                                    ->url('/admin/finished-production-orders')
                                    ->isActiveWhen(fn() => request()->is('admin/finished-production-orders*')),

                                NavigationItem::make('Machine Centers')
                                    ->icon('heroicon-o-cpu-chip')
                                    ->url('/admin/machine-centers')
                                    ->isActiveWhen(fn() => request()->is('admin/machine-centers*')),

                                NavigationItem::make('Work Center Groups')
                                    ->icon('heroicon-o-squares-2x2')
                                    ->url('/admin/work-center-groups')
                                    ->isActiveWhen(fn() => request()->is('admin/work-center-groups*')),

                                NavigationItem::make('Work Centers')
                                    ->icon('heroicon-o-wrench-screwdriver')
                                    ->url('/admin/work-centers')
                                    ->isActiveWhen(fn() => request()->is('admin/work-centers*')),

                                NavigationItem::make('Work Center Calendars')
                                    ->icon('heroicon-o-calendar')
                                    ->url('/admin/work-center-calendars')
                                    ->isActiveWhen(fn() => request()->is('admin/work-center-calendars*')),

                                NavigationItem::make('Overhead Cost Categories')
                                    ->icon('heroicon-o-currency-pound')
                                    ->url('/admin/overhead-cost-categories')
                                    ->isActiveWhen(fn() => request()->is('admin/overhead-cost-categories*')),

                                NavigationItem::make('Actual Overhead Costs')
                                    ->icon('heroicon-o-calendar-days')
                                    ->url('/admin/actual-overhead-costs')
                                    ->isActiveWhen(fn() => request()->is('admin/actual-overhead-costs*')),

                                NavigationItem::make('Routing')
                                    ->icon('heroicon-o-map')
                                    ->url('/admin/routings')
                                    ->isActiveWhen(fn() => request()->is('admin/routings*')),

                                NavigationItem::make('Routing Versions')
                                    ->icon('heroicon-o-map')
                                    ->url('/admin/routing-versions')
                                    ->isActiveWhen(fn() => request()->is('admin/routing-versions*')),

                                NavigationItem::make('Production Bom')
                                    ->icon('heroicon-o-list-bullet')
                                    ->url('/admin/production-boms')
                                    ->isActiveWhen(fn() => request()->is('admin/production-boms*')),

                                NavigationItem::make('Production Bom Version')
                                    ->icon('heroicon-o-list-bullet')
                                    ->url('/admin/production-bom-versions')
                                    ->isActiveWhen(fn() => request()->is('admin/production-bom-versions*')),

                                NavigationItem::make('Production Performance')
                                    ->icon('heroicon-o-presentation-chart-line')
                                    ->url('/admin/production-performance-report')
                                    ->isActiveWhen(fn() => request()->is('admin/production-performance-report*')),
                            ])
                    )

                    // Manufacturing
                    ->group(
                        NavigationGroup::make('Human Resources')
//                            ->icon('heroicon-o-cog-6-tooth')
                            ->items([
                                NavigationItem::make('Business Units')
                                    ->icon('heroicon-o-building-office-2')
                                    ->url('/admin/businesses')
                                    ->isActiveWhen(fn() => request()->is('admin/businesses*')),

                                NavigationItem::make('Factories')
                                    ->icon('heroicon-o-building-storefront')
                                    ->url('/admin/factories')
                                    ->isActiveWhen(fn() => request()->is('admin/factories*')),

                                NavigationItem::make('Departments')
                                    ->icon('heroicon-o-building-office')
                                    ->url('/admin/departments')
                                    ->isActiveWhen(fn() => request()->is('admin/departments*')),

                                NavigationItem::make('Employees')
                                    ->icon('heroicon-o-user-group')
                                    ->url('/admin/employees')
                                    ->isActiveWhen(fn() => request()->is('admin/employees*')),

                                NavigationItem::make('Promotions History')
                                    ->icon('heroicon-o-arrow-up-circle')
                                    ->url('/admin/employee-promotion-histories')
                                    ->isActiveWhen(fn() => request()->is('admin/employee-promotion-histories*')),

                                NavigationItem::make('Employee Attendances')
                                    ->icon('heroicon-o-clock')
                                    ->url('/admin/attendance-ledger-entries')
                                    ->isActiveWhen(fn() => request()->is('admin/attendance-ledger-entries*')),

                                NavigationItem::make('Pay Codes')
                                    ->icon('heroicon-o-banknotes')
                                    ->url('/admin/pay-codes')
                                    ->isActiveWhen(fn() => request()->is('admin/pay-codes*')),

                                NavigationItem::make('Employee ID Cards')
                                    ->icon('heroicon-o-credit-card')
                                    ->url(EmployeeIdCardResource::getUrl())
                                    ->isActiveWhen(fn() => request()->is('admin/employee-id-cards*')),

                                NavigationItem::make('Employee ID Card Templates')
                                    ->icon('heroicon-o-credit-card')
                                    ->url(EmployeeIdCardTemplateResource::getUrl())
                                    ->isActiveWhen(fn() => request()->is('admin/employee-id-card-templates*')),

                                NavigationItem::make('Employee ID Card Print Batches')
                                    ->icon('heroicon-o-credit-card')
                                    ->url(EmployeeIdCardPrintBatchResource::getUrl())
                                    ->isActiveWhen(fn() => request()->is('admin/employee-id-card-print-batches*')),

                                NavigationItem::make('Employee ID Card History')
                                    ->icon('heroicon-o-credit-card')
                                    ->url(EmployeeIdCardHistoryResource::getUrl())
                                    ->isActiveWhen(fn() => request()->is('admin/employee-id-card-history*')),

                                NavigationItem::make('Employee ID Card Verification Logs')
                                    ->icon('heroicon-o-credit-card')
                                    ->url(EmployeeIdCardVerificationLogResource::getUrl())
                                    ->isActiveWhen(fn() => request()->is('admin/employee-id-card-verification-logs*')),

                                NavigationItem::make('Payroll Periods')
                                    ->icon('heroicon-o-calendar-date-range')
                                    ->url('/admin/payroll-periods')
                                    ->isActiveWhen(fn() => request()->is('admin/payroll-periods*')),

                                NavigationItem::make('Payroll Documents')
                                    ->icon('heroicon-o-document-currency-dollar')
                                    ->url('/admin/payroll-documents')
                                    ->isActiveWhen(fn() => request()->is('admin/payroll-documents*')),

                                NavigationItem::make('Tax Tables')
                                    ->icon('heroicon-o-table-cells')
                                    ->url('/admin/tax-tables')
                                    ->isActiveWhen(fn() => request()->is('admin/tax-tables*')),

                                NavigationItem::make('Social Security Tiers')
                                    ->icon('heroicon-o-square-2-stack')
                                    ->url('/admin/social-security-tiers')
                                    ->isActiveWhen(fn() => request()->is('admin/social-security-tiers*')),
                            ])
                    )

                    // Service Contracts
                    ->group(
                        NavigationGroup::make('Service Contracts')
                            ->collapsible()
                            ->items([
                                // Maintenance Contract
                                NavigationItem::make('Maintenance Contracts')
                                    ->icon('heroicon-o-document-text')
                                    ->url(MaintenanceContractResource::getUrl())
                                    ->isActiveWhen(fn() => request()->is('admin/service-contracts*')),

                                // Maintenance Contract Schedules
                                NavigationItem::make('Maintenance Contract Schedules')
                                    ->icon('heroicon-o-calendar-days')
                                    ->url(MaintenanceContractScheduleResource::getUrl())
                                    ->isActiveWhen(fn() => request()->is('admin/service-dispatches*')),

                                // Maintenance Contract Billings
                                NavigationItem::make('Maintenance Contract Billings')
                                    ->icon('heroicon-o-banknotes')
                                    ->url(MaintenanceContractBillingResource::getUrl())
                                    ->isActiveWhen(fn() => request()->is('admin/maintenance-contract-billings*')),

                                // Maintenance Contract Asset
                                NavigationItem::make('Maintenance Contract Assets')
                                    ->icon('heroicon-o-cube')
                                    ->url(MaintenanceContractAssetResource::getUrl())
                                    ->isActiveWhen(fn() => request()->is('admin/maintenance-contract-assets*')),
                            ])
                    )

                    // Setup & Administration
                    ->group(
                        NavigationGroup::make('Auth')
                            ->collapsible()
                            ->items([
                                // Company Information
                                NavigationItem::make('Company Information')
                                    ->icon('heroicon-o-building-office-2')
                                    ->url('/admin/company-information')
                                    ->isActiveWhen(fn() => request()->is('admin/company-information*')),

                                // Users & Permissions
                                NavigationItem::make('Users')
                                    ->icon('heroicon-o-users')
                                    ->visible(fn() => auth()->user()?->can('user.manage') ?? false)
                                    ->url('/admin/users')
                                    ->isActiveWhen(fn() => request()->is('admin/users*')),

                                NavigationItem::make('Roles & Permissions')
                                    ->icon('heroicon-o-shield-check')
                                    ->visible(fn() => auth()->user()?->can('role_permission.manage') ?? false)
                                    ->url('/admin/roles')
                                    ->isActiveWhen(fn() => request()->is('admin/roles*')),

                                NavigationItem::make('User Security')
                                    ->icon('heroicon-o-key')
                                    ->visible(fn() => auth()->user()?->hasRole('super_admin') ?? false)
                                    ->url(UserSecurity::getUrl())
                                    ->isActiveWhen(fn() => request()->is('admin/user-security*')),
                            ])
                    );
            })
            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\Filament\Widgets')
            ->widgets([
                AccountWidget::class,
            ])
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                SetActiveBusinessContext::class,
                EnforceAdminAbsoluteSessionLifetime::class,
                EnforceAdminIdleTimeout::class,
                AuthenticateSession::class,
                AddSecurityHeaders::class,
                ShareErrorsFromSession::class,
                PreventRequestForgery::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])
            ->authMiddleware([
                Authenticate::class,
                'super_admin_2fa',
            ]);
    }
}
