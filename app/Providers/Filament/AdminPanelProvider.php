<?php

namespace App\Providers\Filament;

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
                            ->isActiveWhen(fn () => request()->is('admin')),
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
                                    ->isActiveWhen(fn () => request()->is('admin/chart-of-accounts*')),

                                // Posting Groups Cluster
//                                NavigationItem::make('Posting Groups')
//                                    ->icon('heroicon-o-squares-2x2')
//                                    ->url('/admin/posting-groups')
//                                    ->isActiveWhen(fn () => request()->is('admin/posting-groups*') ||
//                                        request()->is('admin/general-business-posting-groups*') ||
//                                        request()->is('admin/general-product-posting-groups*') ||
//                                        request()->is('admin/vendor-posting-groups*') ||
//                                        request()->is('admin/customer-posting-groups*') ||
//                                        request()->is('admin/vat-posting-groups*')),

                                // Posting Setups
//                                NavigationItem::make('Posting Setups')
//                                    ->icon('heroicon-o-adjustments-horizontal')
//                                    ->url('/admin/posting-setups')
//                                    ->isActiveWhen(fn () => request()->is('admin/posting-setups*') ||
//                                        request()->is('admin/general-posting-setups*') ||
//                                        request()->is('admin/vat-posting-setups*')),

                                // VAT & Tax
                                NavigationItem::make('VAT & Tax Setup')
                                    ->icon('heroicon-o-receipt-percent')
                                    ->url('/admin/vat-masters')
                                    ->isActiveWhen(fn () => request()->is('admin/vat-masters*')),

                                NavigationItem::make('Journal Templates')
                                    ->icon('heroicon-o-document-text')
                                    ->url('/admin/journal-templates'),

                                NavigationItem::make('Gen. Posting Setups')
                                    ->icon('heroicon-o-document-text')
                                    ->url('/admin/general-posting-setups'),

                                NavigationItem::make('Gen. Business Posting Group')
                                    ->icon('heroicon-o-document-text')
                                    ->url('/admin/general-business-posting-groups'),

                                NavigationItem::make('Vat Posting Setups')
                                    ->icon('heroicon-o-document-text')
                                    ->url('/admin/vat-posting-setups'),

                                NavigationItem::make('Vat Buss. Posting Groups')
                                    ->icon('heroicon-o-document-text')
                                    ->url('/admin/vat-business-posting-groups'),

                                NavigationItem::make('Vat Product Posting Groups')
                                    ->icon('heroicon-o-document-text')
                                    ->url('/admin/vat-product-posting-groups'),

                                NavigationItem::make('Bank Accounts')
                                    ->icon('heroicon-o-document-currency-dollar')
                                    ->url('/admin/bank-accounts'),

                                NavigationItem::make('Payments')
                                    ->icon('heroicon-o-document-currency-dollar')
                                    ->url('/admin/payments'),

                                NavigationItem::make('FixedAsset Posting Groups')
                                    ->icon('heroicon-o-document-currency-dollar')
                                    ->url('/admin/f-a-posting-groups'),

                                NavigationItem::make('Inventory Valuation Report')
                                    ->icon('heroicon-o-presentation-chart-line')
                                    ->url('/admin/inventory-valuation-report'),

                                NavigationItem::make('Financial Reports')
                                    ->icon('heroicon-o-chart-bar')
                                    ->url('/admin/financial-reports'),
                            ])
                    )
                    // Finance Module
                    ->group(
                        NavigationGroup::make('Finance')
                            ->items([
                                // Expense Categories
                                NavigationItem::make('Expenses')
                                    ->icon('heroicon-o-list-bullet')
                                    ->url('/admin/expense-categories')
                                    ->isActiveWhen(fn () => request()->is('admin/expense-categories*')),

                                // Currencies
                                NavigationItem::make('Currencies')
                                    ->icon('heroicon-o-currency-dollar')
                                    ->url('/admin/currencies'),

                                NavigationItem::make('Currency Adjustments')
                                    ->icon('heroicon-o-currency-dollar')
                                    ->url('/admin/currency-adjustment-ledgers'),

                                NavigationItem::make('Profit and Loss Report')
                                    ->icon('heroicon-o-currency-dollar')
                                    ->url('/admin/profit-and-loss-report'),

                                NavigationItem::make('Financial Reports')
                                    ->icon('heroicon-o-chart-bar')
                                    ->url('/admin/financial-reports'),
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
                                    ->isActiveWhen(fn () => request()->is('admin/vendors*')),

                                NavigationItem::make('Vendor Contacts')
                                    ->icon('heroicon-o-users')
                                    ->url('/admin/vendor-contacts')
                                    ->isActiveWhen(fn () => request()->is('admin/vendor-contacts*')),

                                NavigationItem::make('Raw Materials')
                                    ->icon('heroicon-o-document')
                                    ->url('/admin/items/rm/raw-materials'),

                                NavigationItem::make('Purchase Quotes')
                                    ->icon('heroicon-o-document')
                                    ->url('/admin/purchase-quotes'),

                                NavigationItem::make('Blanket Orders')
                                    ->icon('heroicon-o-document')
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
                                    ->isActiveWhen(fn () => request()->routeIs('filament.admin.pages.purchases-history')),

                                NavigationItem::make('Vendor Contacts')
                                    ->icon('heroicon-o-users')
                                    ->url('/admin/vendor-contacts')
                                    ->isActiveWhen(fn () => request()->is('admin/vendor-contacts*')),
                            ])
                    )

                    // Sales Module
                    ->group(
                        NavigationGroup::make('Sales')
//                            ->icon('heroicon-o-currency-dollar')
                            ->items([
                                NavigationItem::make('Customers')
                                    ->icon('heroicon-o-users')
                                    ->url('/admin/customers')
                                    ->isActiveWhen(fn () => request()->is('admin/customers*')),

                                NavigationItem::make('Customer Contacts')
                                    ->icon('heroicon-o-users')
                                    ->url('/admin/customer-contacts')
                                    ->isActiveWhen(fn () => request()->is('admin/customer-contacts*')),

                                NavigationItem::make('Finished Goods')
                                    ->icon('heroicon-o-document')
                                    ->url('/admin/items/fg/finished-goods'),

                                NavigationItem::make('Price Change Templates')
                                    ->icon('heroicon-o-document')
                                    ->url('/admin/price-change-templates'),

                                NavigationItem::make('Sales Orders')
                                    ->icon('heroicon-o-clipboard-document-list')
                                    ->url('/admin/sales-orders'),

                                NavigationItem::make('Sales Quotes')
                                    ->icon('heroicon-o-document')
                                    ->url('/admin/sales-quotes'),

                                NavigationItem::make('Blanket Orders')
                                    ->icon('heroicon-o-document')
                                    ->url('/admin/blanket-sales-orders'),

                                NavigationItem::make('Sales Quote Revisions')
                                    ->icon('heroicon-o-document')
                                    ->url('/admin/sales-quote-revisions'),

                                NavigationItem::make('Sales Invoices')
                                    ->icon('heroicon-o-document-currency-dollar')
                                    ->url('/admin/sales-invoices'),

                                NavigationItem::make('Sales Credit Memos')
                                    ->icon('heroicon-o-arrow-uturn-left')
                                    ->url('/admin/sales-credit-memos'),

                                NavigationItem::make('History')
                                    ->icon('heroicon-o-document-check')
                                    ->url(SalesHistory::getUrl())
                                    ->isActiveWhen(fn () => request()->routeIs('filament.admin.pages.sales-history')),
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
                                    ->isActiveWhen(fn () => request()->is('admin/items*')),

                                NavigationItem::make('Categories')
                                    ->icon('heroicon-o-folder')
                                    ->url('/admin/categories'),

                                NavigationItem::make('Item Categories')
                                    ->icon('heroicon-o-folder')
                                    ->url('/admin/item-category-assignments'),

                                NavigationItem::make('Product Groups')
                                    ->icon('heroicon-o-squares-plus')
                                    ->url('/admin/product-groups'),

                                NavigationItem::make('Locations')
                                    ->icon('heroicon-o-map-pin')
                                    ->url('/admin/locations'),

                                NavigationItem::make('Zones')
                                    ->icon('heroicon-o-inbox')
                                    ->url('/admin/zones'),

                                NavigationItem::make('Bins')
                                    ->icon('heroicon-o-inbox')
                                    ->url('/admin/bins'),

                                NavigationItem::make('Work Center Groups')
                                    ->icon('heroicon-o-hand-raised')
                                    ->url('/admin/work-center-groups'),


                                NavigationItem::make('Work Centers')
                                    ->icon('heroicon-o-hand-raised')
                                    ->url('/admin/work-centers'),

                                NavigationItem::make('Warehouse Receipts')
                                    ->icon('heroicon-o-arrow-down-tray')
                                    ->url('/admin/warehouse-receipts'),

                                NavigationItem::make('Warehouse Activities')
                                    ->icon('heroicon-o-arrow-down-tray')
                                    ->url('/admin/warehouse-activities'),

                                NavigationItem::make('Warehouse Entry')
                                    ->icon('heroicon-o-arrow-down-tray')
                                    ->url('/admin/warehouse-entries'),

                                NavigationItem::make('Inventory Putaways')
                                    ->icon('heroicon-o-arrow-down-on-square')
                                    ->url('/admin/inventory-putaways'),

                                NavigationItem::make('Warehouse Putaways')
                                    ->icon('heroicon-o-arrow-down-on-square')
                                    ->url('/admin/warehouse-putaways'),

                                NavigationItem::make('Warehouse Shipments')
                                    ->icon('heroicon-o-arrow-up-tray')
                                    ->url('/admin/warehouse-shipments'),

                                NavigationItem::make('Picks')
                                    ->icon('heroicon-o-hand-raised')
                                    ->url('/admin/picks'),

                                NavigationItem::make('Inventory Adjustments')
                                    ->icon('heroicon-o-adjustments-vertical')
                                    ->url('/admin/inventory-adjustments'),

                                NavigationItem::make('Item Ledger Entries')
                                    ->icon('heroicon-o-book-open')
                                    ->url('/admin/item-ledger-entries'),

                                NavigationItem::make('Physical Inventory')
                                    ->icon('heroicon-o-clipboard')
                                    ->url('/admin/physical-inventory'),

                                NavigationItem::make('Inventory Valuation Report')
                                    ->icon('heroicon-o-presentation-chart-line')
                                    ->url('/admin/inventory-valuation-report'),
                            ])
                    )

                    // Manufacturing
                        ->group(
                            NavigationGroup::make('Manufacturing')
//                            ->icon('heroicon-o-cog-6-tooth')
                                ->items([
                                    NavigationItem::make('Production Orders')
                                        ->icon('heroicon-o-wrench')
                                        ->url('/admin/production-orders'),

                                    NavigationItem::make('Released Production Orders')
                                        ->icon('heroicon-o-play')
                                        ->url('/admin/released-production-orders'),

                                    NavigationItem::make('Finished Production Orders')
                                        ->icon('heroicon-o-check-circle')
                                        ->url('/admin/finished-production-orders'),

                                    NavigationItem::make('CapEx Projects')
                                        ->icon('heroicon-o-building-office')
                                        ->url('/admin/capex-projects'),

                                    NavigationItem::make('Assets')
                                        ->icon('heroicon-o-building-office')
                                        ->url('/admin/assets'),

                                    NavigationItem::make('Depreciation Books')
                                        ->icon('heroicon-o-building-office')
                                        ->url('/admin/depreciation-books'),

                                    NavigationItem::make('FA Classes')
                                        ->icon('heroicon-o-building-office')
                                        ->url('/admin/f-a-classes'),

                                    NavigationItem::make('Machine Centers')
                                        ->icon('heroicon-o-cpu-chip')
                                        ->url('/admin/machine-centers'),

                                    NavigationItem::make('Work Center Groups')
                                        ->icon('heroicon-o-cpu-chip')
                                        ->url('/admin/work-center-groups'),

                                    NavigationItem::make('Work Centers')
                                        ->icon('heroicon-o-wrench-screwdriver')
                                        ->url('/admin/work-centers'),

                                    NavigationItem::make('Work Center Calendars')
                                        ->icon('heroicon-o-calendar')
                                        ->url('/admin/work-center-calendars'),

                                    NavigationItem::make('Routing')
                                        ->icon('heroicon-o-map')
                                        ->url('/admin/routings'),

                                    NavigationItem::make('Routing Versions')
                                        ->icon('heroicon-o-map')
                                        ->url('/admin/routing-versions'),

                                    NavigationItem::make('Production Bom')
                                        ->icon('heroicon-o-list-bullet')
                                        ->url('/admin/production-boms'),

                                    NavigationItem::make('Production Bom Version')
                                        ->icon('heroicon-o-list-bullet')
                                        ->url('/admin/production-bom-versions'),

                                    NavigationItem::make('Production Performance')
                                        ->icon('heroicon-o-presentation-chart-line')
                                        ->url('/admin/production-performance-report')
                                        ->isActiveWhen(fn () => request()->is('admin/production-performance-report*')),

                                    NavigationItem::make('WIP Valuation')
                                        ->icon('heroicon-o-currency-dollar')
                                        ->url('/admin/wip-valuation-report')
                                        ->isActiveWhen(fn () => request()->is('admin/wip-valuation-report*')),
                                ])
                        )

                    // Manufacturing
                        ->group(
                            NavigationGroup::make('Human Resources')
//                            ->icon('heroicon-o-cog-6-tooth')
                                ->items([
                                    NavigationItem::make('Business Units')
                                        ->icon('heroicon-o-wrench')
                                        ->url('/admin/businesses'),

                                    NavigationItem::make('Factories')
                                        ->icon('heroicon-o-wrench')
                                        ->url('/admin/factories'),

                                    NavigationItem::make('Departments')
                                        ->icon('heroicon-o-wrench')
                                        ->url('/admin/departments'),

                                    NavigationItem::make('Employees')
                                        ->icon('heroicon-o-user-group')
                                        ->url('/admin/employees'),

                                    NavigationItem::make('Pay Codes')
                                        ->icon('heroicon-o-banknotes')
                                        ->url('/admin/pay-codes'),

                                    NavigationItem::make('Payroll Documents')
                                        ->icon('heroicon-o-document-currency-dollar')
                                        ->url('/admin/payroll-documents')
                                        ->isActiveWhen(fn () => request()->is('admin/payroll-documents*')),

                                    NavigationItem::make('Purchase Receipts')
                                        ->icon('heroicon-o-archive-box')
                                        ->url('/admin/purchase-receipts'),

                                    NavigationItem::make('WIP Valuation')
                                        ->icon('heroicon-o-currency-dollar')
                                        ->url('/admin/wip-valuation-report')
                                        ->isActiveWhen(fn () => request()->is('admin/wip-valuation-report*')),
                                ])
                        )

                    // Setup & Administration
                        ->group(
                            NavigationGroup::make('Setup')
//                            ->icon('heroicon-o-cog')
                                ->collapsible()
                                ->items([
                                    // Company Information
                                    NavigationItem::make('Company Information')
                                        ->icon('heroicon-o-building-office-2')
                                        ->url('/admin/company-information'),

                                    // Dimensions
                                    NavigationItem::make('Dimensions')
                                        ->icon('heroicon-o-square-3-stack-3d')
                                        ->url('/admin/dimensions'),

                                    // Payment Terms
                                    NavigationItem::make('Payment Terms')
                                        ->icon('heroicon-o-calendar-days')
                                        ->url('/admin/payment-terms'),

                                    // Shipping
                                    NavigationItem::make('Shipping Agents')
                                        ->icon('heroicon-o-truck')
                                        ->url('/admin/shipping-agents'),

                                    NavigationItem::make('Shipment Methods')
                                        ->icon('heroicon-o-paper-airplane')
                                        ->url('/admin/shipment-methods'),

                                    // Units of Measure
                                    NavigationItem::make('Units of Measure')
                                        ->icon('heroicon-o-scale')
                                        ->url('/admin/unit-of-measures'),

                                    // Number Series
                                    NavigationItem::make('Number Series')
                                        ->icon('heroicon-o-hashtag')
                                        ->url('/admin/number-series'),

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
