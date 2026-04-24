<?php

namespace App\Providers\Filament;

use App\Filament\Pages\Finance\GeneralJournals;
use App\Filament\Pages\PurchaseHistory;
use App\Filament\Pages\SalesHistory;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Navigation\NavigationBuilder;
use Filament\Navigation\NavigationGroup;
use Filament\Navigation\NavigationItem;
use Filament\Pages\Dashboard;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\Widgets\AccountWidget;
use Filament\Widgets\FilamentInfoWidget;
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
            ->colors([
                'primary' => Color::Amber,
            ])
            ->spa(hasPrefetching: true)
            ->brandName('BIFLI Group')
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\Filament\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\Filament\Pages')
            ->pages([
                Dashboard::class,
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
                                    ->url('/admin/chart-of-accounts')
                                    ->isActiveWhen(fn() => request()->is('admin/chart-of-accounts*')),

                                // Account Schedules
                                NavigationItem::make('Account Schedules')
                                    ->icon('heroicon-o-calendar-date-range')
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
                                    ->icon('heroicon-o-paper-clip')
                                    ->url('/admin/approval-templates')
                                    ->isActiveWhen(fn() => request()->is('admin/approval-templates*')),

                                // Posting Setups & Posting Groups
                                NavigationItem::make('Posting Groups')
                                    ->icon('heroicon-o-squares-2x2')
                                    ->url('/admin/posting-groups')
                                    ->isActiveWhen(fn() => request()->is('admin/posting-groups*')),

                                // Bank Accounts
                                NavigationItem::make('Bank Accounts')
                                    ->icon('heroicon-o-document-currency-dollar')
                                    ->url('/admin/bank-accounts')
                                    ->isActiveWhen(fn() => request()->is('admin/bank-accounts*')),

                                // Payments
                                NavigationItem::make('Payments')
                                    ->icon('heroicon-o-document-currency-dollar')
                                    ->url('/admin/payments')
                                    ->isActiveWhen(fn() => request()->is('admin/payments*')),

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
                                    ->icon('heroicon-o-credit-card')
                                    ->url('/admin/expense-transactions')
                                    ->isActiveWhen(fn() => request()->is('admin/expense-transaction*')),

                                NavigationItem::make('Expenses Categories')
                                    ->icon('heroicon-o-list-bullet')
                                    ->url('/admin/expense-categories')
                                    ->isActiveWhen(fn() => request()->is('admin/expense-categorie*')),

                                NavigationItem::make('Currencies')
                                    ->icon('heroicon-o-currency-dollar')
                                    ->url('/admin/currencies')
                                    ->isActiveWhen(fn() => request()->is('admin/currencies*')),

                                NavigationItem::make('Currency Adjustments')
                                    ->icon('heroicon-o-presentation-chart-bar')
                                    ->url('/admin/currency-adjustment-ledgers')
                                    ->isActiveWhen(fn() => request()->is('admin/currency-adjustment-ledger*')),

                                NavigationItem::make('CapEx Projects')
                                    ->icon('heroicon-o-banknotes')
                                    ->url('/admin/capex-projects')
                                    ->isActiveWhen(fn() => request()->is('admin/capex-projects*')),

                                NavigationItem::make('Fixed Assets')
                                    ->icon('heroicon-o-adjustments-vertical')
                                    ->url('/admin/fixed-assets')
                                    ->isActiveWhen(fn() => request()->is('admin/fixed-assets*')),

                                NavigationItem::make('Depreciation Books')
                                    ->icon('heroicon-o-minus-circle')
                                    ->url('/admin/depreciation-books')
                                    ->isActiveWhen(fn() => request()->is('admin/depreciation-books*')),

                                NavigationItem::make('FA Classes')
                                    ->icon('heroicon-o-arrow-right-circle')
                                    ->url('/admin/f-a-classes')
                                    ->isActiveWhen(fn() => request()->is('admin/f-a-classes*')),

                                // Dimensions
                                NavigationItem::make('Dimensions')
                                    ->icon('heroicon-o-square-3-stack-3d')
                                    ->url('/admin/dimensions'),

                                // Payment Terms
                                NavigationItem::make('Payment Terms')
                                    ->icon('heroicon-o-calendar-days')
                                    ->url('/admin/payment-terms'),

                                // Units of Measure
                                NavigationItem::make('Units of Measure')
                                    ->icon('heroicon-o-scale')
                                    ->url('/admin/unit-of-measures'),

                                // Number Series
                                NavigationItem::make('Number Series')
                                    ->icon('heroicon-o-hashtag')
                                    ->url('/admin/number-series'),

                                NavigationItem::make('Profit & Loss Report')
                                    ->icon('heroicon-o-document-chart-bar')
                                    ->isActiveWhen(fn() => request()->is('admin/profit-and-loss-report*'))
                                    ->url('/admin/profit-and-loss-report'),

                                NavigationItem::make('WIP Valuation')
                                    ->icon('heroicon-o-currency-dollar')
                                    ->url('/admin/wip-valuation-report')
                                    ->isActiveWhen(fn() => request()->is('admin/wip-valuation-report*')),

                                NavigationItem::make('Yield Report')
                                    ->icon('heroicon-o-beaker')
                                    ->url('/admin/yield-report')
                                    ->isActiveWhen(fn() => request()->is('admin/yield-report*')),

                                NavigationItem::make('Production Journal Template')
                                    ->icon('heroicon-o-beaker')
                                    ->url('/admin/production-journal-templates')
                                    ->isActiveWhen(fn() => request()->is('admin/production-journal-templates*')),

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
                                    ->icon('heroicon-o-truck')
                                    ->url('/admin/vendors')
                                    ->isActiveWhen(fn() => request()->is('admin/vendors*')),

//                                NavigationItem::make('Vendor Contacts')
//                                    ->icon('heroicon-o-users')
//                                    ->url('/admin/vendor-contacts')
//                                    ->isActiveWhen(fn () => request()->is('admin/vendor-contacts*')),

                                NavigationItem::make('Raw Materials')
                                    ->icon('heroicon-o-shopping-bag')
                                    ->url('/admin/items/rm/raw-materials'),

                                NavigationItem::make('Purchase Quotes')
                                    ->icon('heroicon-o-document')
                                    ->url('/admin/purchase-quotes'),

                                NavigationItem::make('Blanket Orders')
                                    ->icon('heroicon-o-shopping-cart')
                                    ->url('/admin/blanket-purchase-orders'),

                                NavigationItem::make('Purchase Orders')
                                    ->icon('heroicon-o-clipboard-document-list')
                                    ->url('/admin/purchase-orders'),

                                NavigationItem::make('Purchase Invoices')
                                    ->icon('heroicon-o-document-currency-dollar')
                                    ->url('/admin/purchase-invoices'),

                                NavigationItem::make('Purchase Credit Memos')
                                    ->icon('heroicon-o-arrow-uturn-left')
                                    ->url('/admin/posted-purchase-credit-memos'),

                                NavigationItem::make('Purchases History')
                                    ->icon('heroicon-o-document-check')
                                    ->url(PurchaseHistory::getUrl())
                                    ->isActiveWhen(fn() => request()->routeIs('filament.admin.pages.purchases-history')),
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
                                    ->icon('heroicon-o-user-circle')
                                    ->url('/admin/customers')
                                    ->isActiveWhen(fn() => request()->is('admin/customers*')),

                                NavigationItem::make('Customer Contacts')
                                    ->icon('heroicon-o-device-phone-mobile')
                                    ->url('/admin/customer-contacts')
                                    ->isActiveWhen(fn() => request()->is('admin/customer-contacts*')),

                                NavigationItem::make('Finished Goods')
                                    ->icon('heroicon-o-bolt')
                                    ->url('/admin/items/fg/finished-goods'),

                                NavigationItem::make('Price Change Templates')
                                    ->icon('heroicon-o-document')
                                    ->url('/admin/price-change-templates'),

                                NavigationItem::make('Sales Orders')
                                    ->icon('heroicon-o-clipboard-document-list')
                                    ->url('/admin/sales-orders'),

                                NavigationItem::make('Sales Quotes')
                                    ->icon('heroicon-o-sparkles')
                                    ->url('/admin/sales-quotes'),

                                NavigationItem::make('Blanket Orders')
                                    ->icon('heroicon-o-archive-box-arrow-down')
                                    ->url('/admin/blanket-sales-orders'),

                                NavigationItem::make('Sales Quote Revisions')
                                    ->icon('heroicon-o-star')
                                    ->url('/admin/sales-quote-revisions'),

                                NavigationItem::make('Sales Invoices')
                                    ->icon('heroicon-o-document-currency-dollar')
                                    ->url('/admin/sales-invoices'),

                                NavigationItem::make('Sales Credit Memos')
                                    ->icon('heroicon-o-arrow-uturn-left')
                                    ->url('/admin/sales-credit-memos'),

                                // Shipping
                                NavigationItem::make('Shipping Agents')
                                    ->icon('heroicon-o-truck')
                                    ->url('/admin/shipping-agents'),

                                NavigationItem::make('Shipment Methods')
                                    ->icon('heroicon-o-paper-airplane')
                                    ->url('/admin/shipment-methods'),

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
                                    ->icon('heroicon-o-tag')
                                    ->url('/admin/items')
                                    ->isActiveWhen(fn() => request()->is('admin/items*')),

                                NavigationItem::make('Categories')
                                    ->icon('heroicon-o-folder')
                                    ->url('/admin/categories'),

                                NavigationItem::make('Item Categories')
                                    ->icon('heroicon-o-folder')
                                    ->url('/admin/item-category-assignments')
                                    ->isActiveWhen(fn() => request()->is('admin/item-category-assignments*')),

                                NavigationItem::make('Locations')
                                    ->icon('heroicon-o-map-pin')
                                    ->url('/admin/locations')
                                    ->isActiveWhen(fn() => request()->is('admin/locations*')),

                                NavigationItem::make('Zones')
                                    ->icon('heroicon-o-inbox')
                                    ->url('/admin/zones')
                                    ->isActiveWhen(fn() => request()->is('admin/zones*')),

                                NavigationItem::make('Bins')
                                    ->icon('heroicon-o-bold')
                                    ->url('/admin/bins')
                                    ->isActiveWhen(fn() => request()->is('admin/bins*')),

                                NavigationItem::make('Work Center Groups')
                                    ->icon('heroicon-o-hand-raised')
                                    ->url('/admin/work-center-groups')
                                    ->isActiveWhen(fn() => request()->is('admin/work-center-groups*')),

                                NavigationItem::make('Work Centers')
                                    ->icon('heroicon-o-calculator')
                                    ->url('/admin/work-centers')
                                    ->isActiveWhen(fn() => request()->is('admin/work-centers*')),

                                NavigationItem::make('Warehouse Receipts')
                                    ->icon('heroicon-o-arrow-down-tray')
                                    ->url('/admin/warehouse-receipts')
                                    ->isActiveWhen(fn() => request()->is('admin/warehouse-receipts*')),

                                NavigationItem::make('Warehouse Activities')
                                    ->icon('heroicon-o-arrow-right-start-on-rectangle')
                                    ->url('/admin/warehouse-activities')
                                    ->isActiveWhen(fn() => request()->is('admin/warehouse-activities*')),

                                NavigationItem::make('Warehouse Entry')
                                    ->icon('heroicon-o-arrow-right-end-on-rectangle')
                                    ->url('/admin/warehouse-entries')
                                    ->isActiveWhen(fn() => request()->is('admin/warehouse-entries*')),

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
                                    ->url('/admin/inventory-adjustments')
                                    ->isActiveWhen(fn() => request()->is('admin/inventory-adjustments*')),

                                NavigationItem::make('Item Ledger Entries')
                                    ->icon('heroicon-o-book-open')
                                    ->url('/admin/item-ledger-entries')
                                    ->isActiveWhen(fn() => request()->is('admin/item-ledger-entries*')),

                                NavigationItem::make('Physical Inventory')
                                    ->icon('heroicon-o-clipboard')
                                    ->url('/admin/physical-inventory')
                                    ->isActiveWhen(fn() => request()->is('admin/physical-inventory*')),

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
                                    ->icon('heroicon-o-wrench')
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
                                    ->icon('heroicon-o-cpu-chip')
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
                                    ->icon('heroicon-o-wrench')
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

                                NavigationItem::make('Pay Codes')
                                    ->icon('heroicon-o-banknotes')
                                    ->url('/admin/pay-codes')
                                    ->isActiveWhen(fn() => request()->is('admin/pay-codes*')),

                                NavigationItem::make('Payroll Periods')
                                    ->icon('heroicon-o-calendar-date-range')
                                    ->url('/admin/payroll-periods')
                                    ->isActiveWhen(fn() => request()->is('admin/payroll-periods*')),

                                NavigationItem::make('Payroll Documents')
                                    ->icon('heroicon-o-document-currency-dollar')
                                    ->url('/admin/payroll-documents')
                                    ->isActiveWhen(fn() => request()->is('admin/payroll-documents*')),

                                NavigationItem::make('Tax Tables')
                                    ->icon('heroicon-o-square-2-stack')
                                    ->url('/admin/tax-tables')
                                    ->isActiveWhen(fn() => request()->is('admin/tax-tables*')),

                                NavigationItem::make('Social Security Tiers')
                                    ->icon('heroicon-o-square-2-stack')
                                    ->url('/admin/social-security-tiers')
                                    ->isActiveWhen(fn() => request()->is('admin/social-security-tiers*')),

                                NavigationItem::make('Purchase Receipts')
                                    ->icon('heroicon-o-receipt-percent')
                                    ->url('/admin/purchase-receipts')
                                    ->isActiveWhen(fn() => request()->is('admin/purchase-receipts*')),

//                                NavigationItem::make('WIP Valuation')
//                                    ->icon('heroicon-o-currency-dollar')
//                                    ->url('/admin/wip-valuation-report')
//                                    ->isActiveWhen(fn() => request()->is('admin/wip-valuation-report*')),
                            ])
                    )

                    // Setup & Administration
                    ->group(
                        NavigationGroup::make('Auth')
//                            ->icon('heroicon-o-cog')
                            ->collapsible()
                            ->items([
                                // Company Information
                                NavigationItem::make('Company Information')
                                    ->icon('heroicon-o-building-office-2')
                                    ->url('/admin/company-information'),

                                // Users & Permissions
                                NavigationItem::make('Users')
                                    ->icon('heroicon-o-users')
                                    ->url('/admin/users'),

                                NavigationItem::make('Roles & Permissions')
                                    ->icon('heroicon-o-shield-check')
                                    ->url('/admin/roles'),
                            ])
                    );
            })
            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\Filament\Widgets')
            ->widgets([
                AccountWidget::class,
                FilamentInfoWidget::class,
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
            ]);
    }
}
