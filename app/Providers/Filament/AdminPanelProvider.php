<?php

declare(strict_types=1);

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
use App\Filament\Pages\RecruitmentDashboard;
use App\Filament\Pages\RecruitmentReports;
use App\Filament\Pages\SalesHistory;
use App\Filament\Pages\UserSecurity;
use App\Filament\Resources\AttendanceCorrectionRequests\AttendanceCorrectionRequestResource;
use App\Filament\Resources\AttendanceDevices\AttendanceDeviceResource;
use App\Filament\Resources\AttendanceLocations\AttendanceLocationResource;
use App\Filament\Resources\AttendancePayrollReviewBatches\AttendancePayrollReviewBatchResource;
use App\Filament\Resources\AttendancePayrollReviewBatchLines\AttendancePayrollReviewBatchLineResource;
use App\Filament\Resources\AttendancePayrollRules\AttendancePayrollRuleResource;
use App\Filament\Resources\AttendanceReviewItems\AttendanceReviewItemResource;
use App\Filament\Resources\AttendanceReviewPeriods\AttendanceReviewPeriodResource;
use App\Filament\Resources\EmployeeAttendanceDays\EmployeeAttendanceDayResource;
use App\Filament\Resources\EmployeeAttendanceEvents\EmployeeAttendanceEventResource;
use App\Filament\Resources\EmployeeConfirmationDecisions\EmployeeConfirmationDecisionResource;
use App\Filament\Resources\EmployeeIdCardHistories\EmployeeIdCardHistoryResource;
use App\Filament\Resources\EmployeeIdCardPrintBatches\EmployeeIdCardPrintBatchResource;
use App\Filament\Resources\EmployeeIdCards\EmployeeIdCardResource;
use App\Filament\Resources\EmployeeIdCardTemplates\EmployeeIdCardTemplateResource;
use App\Filament\Resources\EmployeeIdCardVerificationLogs\EmployeeIdCardVerificationLogResource;
use App\Filament\Resources\EmployeeLeaveEntitlements\EmployeeLeaveEntitlementResource;
use App\Filament\Resources\EmployeeLeaveLedgerEntries\EmployeeLeaveLedgerEntryResource;
use App\Filament\Resources\EmployeePayslipHistories\EmployeePayslipHistoryResource;
use App\Filament\Resources\EmployeePayslips\EmployeePayslipResource;
use App\Filament\Resources\EmployeeShifts\EmployeeShiftResource;
use App\Filament\Resources\EmployeeWorkAvailabilities\EmployeeWorkAvailabilityResource;
use App\Filament\Resources\EmployeeWorkScheduleAssignments\EmployeeWorkScheduleAssignmentResource;
use App\Filament\Resources\LeavePolicies\LeavePolicyResource;
use App\Filament\Resources\LeaveRequests\LeaveRequestResource;
use App\Filament\Resources\LeaveTypes\LeaveTypeResource;
use App\Filament\Resources\MaintenanceContractAssets\MaintenanceContractAssetResource;
use App\Filament\Resources\MaintenanceContractBillings\MaintenanceContractBillingResource;
use App\Filament\Resources\MaintenanceContracts\MaintenanceContractResource;
use App\Filament\Resources\MaintenanceContractSchedules\MaintenanceContractScheduleResource;
use App\Filament\Resources\OvertimeApprovals\OvertimeApprovalResource;
use App\Filament\Resources\PayCodes\PayCodeResource;
use App\Filament\Resources\PayrollDocuments\PayrollDocumentResource;
use App\Filament\Resources\PayrollPeriods\PayrollPeriodResource;
use App\Filament\Resources\PayrollPostingGroups\PayrollPostingGroupResource;
use App\Filament\Resources\PerformanceAppraisalCycles\PerformanceAppraisalCycleResource;
use App\Filament\Resources\PerformanceAppraisalDisputes\PerformanceAppraisalDisputeResource;
use App\Filament\Resources\PerformanceAppraisalHistories\PerformanceAppraisalHistoryResource;
use App\Filament\Resources\PerformanceAppraisalModerationSessions\PerformanceAppraisalModerationSessionResource;
use App\Filament\Resources\PerformanceAppraisalRecommendations\PerformanceAppraisalRecommendationResource;
use App\Filament\Resources\PerformanceAppraisals\PerformanceAppraisalResource;
use App\Filament\Resources\PerformanceAppraisalTemplates\PerformanceAppraisalTemplateResource;
use App\Filament\Resources\PerformanceCompetencies\PerformanceCompetencyResource;
use App\Filament\Resources\PerformanceCompetencyFrameworks\PerformanceCompetencyFrameworkResource;
use App\Filament\Resources\PerformanceDevelopmentPlans\PerformanceDevelopmentPlanResource;
use App\Filament\Resources\PerformanceGoalPlans\PerformanceGoalPlanResource;
use App\Filament\Resources\PerformanceGoals\PerformanceGoalResource;
use App\Filament\Resources\PerformanceImprovementPlans\PerformanceImprovementPlanResource;
use App\Filament\Resources\PerformancePositionCompetencies\PerformancePositionCompetencyResource;
use App\Filament\Resources\PerformanceProbationReviews\PerformanceProbationReviewResource;
use App\Filament\Resources\PerformanceRatingScales\PerformanceRatingScaleResource;
use App\Filament\Resources\RecruitmentApplications\RecruitmentApplicationResource;
use App\Filament\Resources\RecruitmentApplicationScreenings\RecruitmentApplicationScreeningResource;
use App\Filament\Resources\RecruitmentAssessments\RecruitmentAssessmentResource;
use App\Filament\Resources\RecruitmentCandidates\RecruitmentCandidateResource;
use App\Filament\Resources\RecruitmentHistories\RecruitmentHistoryResource;
use App\Filament\Resources\RecruitmentInterviewPanels\RecruitmentInterviewPanelResource;
use App\Filament\Resources\RecruitmentInterviews\RecruitmentInterviewResource;
use App\Filament\Resources\RecruitmentInterviewScorecardTemplates\RecruitmentInterviewScorecardTemplateResource;
use App\Filament\Resources\RecruitmentJobPostings\RecruitmentJobPostingResource;
use App\Filament\Resources\RecruitmentOffers\RecruitmentOfferResource;
use App\Filament\Resources\RecruitmentOnboardingPlans\RecruitmentOnboardingPlanResource;
use App\Filament\Resources\RecruitmentOnboardingTasks\RecruitmentOnboardingTaskResource;
use App\Filament\Resources\RecruitmentOnboardingTemplates\RecruitmentOnboardingTemplateResource;
use App\Filament\Resources\RecruitmentPreEmploymentChecks\RecruitmentPreEmploymentCheckResource;
use App\Filament\Resources\RecruitmentRequisitions\RecruitmentRequisitionResource;
use App\Filament\Resources\RecruitmentScreeningTemplates\RecruitmentScreeningTemplateResource;
use App\Filament\Resources\RecruitmentSelectionReviews\RecruitmentSelectionReviewResource;
use App\Filament\Resources\RecruitmentVacancies\RecruitmentVacancyResource;
use App\Filament\Resources\WorkforceRosterAssignments\WorkforceRosterAssignmentResource;
use App\Filament\Resources\WorkforceRosterHistories\WorkforceRosterHistoryResource;
use App\Filament\Resources\WorkforceRosterPeriods\WorkforceRosterPeriodResource;
use App\Filament\Resources\WorkforceRosterRoles\WorkforceRosterRoleResource;
use App\Filament\Resources\WorkforceRotationAssignments\WorkforceRotationAssignmentResource;
use App\Filament\Resources\WorkforceRotationTemplates\WorkforceRotationTemplateResource;
use App\Filament\Resources\WorkforceSchedulingRules\WorkforceSchedulingRuleResource;
use App\Filament\Resources\WorkforceShiftReplacements\WorkforceShiftReplacementResource;
use App\Filament\Resources\WorkforceShiftSwapRequests\WorkforceShiftSwapRequestResource;
use App\Filament\Resources\WorkforceStaffingRequirements\WorkforceStaffingRequirementResource;
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

                            ])
                    )
                    // Employee Identity
                    ->group(
                        NavigationGroup::make('Employee Identity')
                            ->collapsible()
                            ->items([
                                NavigationItem::make('Employee ID Cards')
                                    ->icon('heroicon-o-identification')
                                    ->visible(
                                        fn(): bool => EmployeeIdCardResource::canViewAny()
                                    )
                                    ->url(EmployeeIdCardResource::getUrl(panel: 'admin'))
                                    ->isActiveWhen(
                                        fn(): bool => request()->is('admin/employee-id-cards*')
                                    ),

                                NavigationItem::make('ID Card Templates')
                                    ->icon('heroicon-o-rectangle-stack')
                                    ->visible(
                                        fn(): bool => EmployeeIdCardTemplateResource::canViewAny()
                                    )
                                    ->url(EmployeeIdCardTemplateResource::getUrl(panel: 'admin'))
                                    ->isActiveWhen(
                                        fn(): bool => request()->is(
                                            'admin/employee-id-card-templates*'
                                        )
                                    ),

                                NavigationItem::make('ID Card Print Batches')
                                    ->icon('heroicon-o-printer')
                                    ->visible(
                                        fn(): bool => EmployeeIdCardPrintBatchResource::canViewAny()
                                    )
                                    ->url(
                                        EmployeeIdCardPrintBatchResource::getUrl(panel: 'admin')
                                    )
                                    ->isActiveWhen(
                                        fn(): bool => request()->is(
                                            'admin/employee-id-card-print-batches*'
                                        )
                                    ),

                                NavigationItem::make('ID Card History')
                                    ->icon('heroicon-o-clock')
                                    ->visible(
                                        fn(): bool => EmployeeIdCardHistoryResource::canViewAny()
                                    )
                                    ->url(EmployeeIdCardHistoryResource::getUrl(panel: 'admin'))
                                    ->isActiveWhen(
                                        fn(): bool => request()->is(
                                                'admin/employee-id-card-histories*'
                                            )
                                            || request()->is(
                                                'admin/employee-id-card-history*'
                                            )
                                    ),

                                NavigationItem::make('ID Card Verification Logs')
                                    ->icon('heroicon-o-shield-check')
                                    ->visible(
                                        fn(): bool => EmployeeIdCardVerificationLogResource::canViewAny()
                                    )
                                    ->url(
                                        EmployeeIdCardVerificationLogResource::getUrl(
                                            panel: 'admin'
                                        )
                                    )
                                    ->isActiveWhen(
                                        fn(): bool => request()->is(
                                            'admin/employee-id-card-verification-logs*'
                                        )
                                    ),
                            ])
                    )
                    ->group(
                        NavigationGroup::make('Leave & Attendance')
//                            ->icon('heroicon-o-cog-6-tooth')
                            ->items([
                                // Leave Management
                                NavigationItem::make('Leave Types')
                                    ->icon('heroicon-o-rectangle-stack')
                                    ->url(LeaveTypeResource::getUrl())
                                    ->isActiveWhen(fn() => request()->is('admin/leave-types*')),

                                NavigationItem::make('Leave Policies')
                                    ->icon('heroicon-o-clipboard-document-list')
                                    ->url(LeavePolicyResource::getUrl())
                                    ->isActiveWhen(fn() => request()->is('admin/leave-policies*')),

                                NavigationItem::make('Leave Entitlements')
                                    ->icon('heroicon-o-banknotes')
                                    ->url(EmployeeLeaveEntitlementResource::getUrl())
                                    ->isActiveWhen(fn() => request()->is('admin/employee-leave-entitlements*')),

                                NavigationItem::make('Leave Requests')
                                    ->icon('heroicon-o-calendar-days')
                                    ->url(LeaveRequestResource::getUrl())
                                    ->isActiveWhen(fn() => request()->is('admin/leave-requests*')),

                                NavigationItem::make('Leave Ledger')
                                    ->icon('heroicon-o-book-open')
                                    ->url(EmployeeLeaveLedgerEntryResource::getUrl())
                                    ->isActiveWhen(fn() => request()->is('admin/employee-leave-ledger-entries*')),

                                // Attendance Management
                                NavigationItem::make('Employee Attendances')
                                    ->icon('heroicon-o-clock')
                                    ->url('/admin/attendance-ledger-entries')
                                    ->isActiveWhen(fn() => request()->is('admin/attendance-ledger-entries*')),

                                NavigationItem::make('Attendance Locations')
                                    ->icon('heroicon-o-building-storefront')
                                    ->url(AttendanceLocationResource::getUrl())
                                    ->isActiveWhen(fn() => request()->is('admin/attendance-locations*')),

                                NavigationItem::make('Attendance Devices')
                                    ->icon('heroicon-o-building-office')
                                    ->url(AttendanceDeviceResource::getUrl())
                                    ->isActiveWhen(fn() => request()->is('admin/attendance-devices*')),

                                NavigationItem::make('Employee Shifts')
                                    ->icon('heroicon-o-user-group')
                                    ->url(EmployeeShiftResource::getUrl())
                                    ->isActiveWhen(fn() => request()->is('admin/employee-shifts*')),

                                NavigationItem::make('Employee Work Schedule Assignments')
                                    ->icon('heroicon-o-user-group')
                                    ->url(EmployeeWorkScheduleAssignmentResource::getUrl())
                                    ->isActiveWhen(fn() => request()->is('admin/employee-work-schedule-assignments*')),

                                NavigationItem::make('Employee Attendance Days')
                                    ->icon('heroicon-o-user-group')
                                    ->url(EmployeeAttendanceDayResource::getUrl())
                                    ->isActiveWhen(fn() => request()->is('admin/employee-attendance-days*')),

                                NavigationItem::make('Attendance Correct Reports')
                                    ->icon('heroicon-o-user-group')
                                    ->url(AttendanceCorrectionRequestResource::getUrl())
                                    ->isActiveWhen(fn() => request()->is('admin/attendance-correction-requests*')),

                                NavigationItem::make('Overtime Approvals')
                                    ->icon('heroicon-o-user-group')
                                    ->url(OvertimeApprovalResource::getUrl())
                                    ->isActiveWhen(fn() => request()->is('admin/overtime-approvals*')),

                                NavigationItem::make('Employee Attendance Events')
                                    ->icon('heroicon-o-user-group')
                                    ->url(EmployeeAttendanceEventResource::getUrl())
                                    ->isActiveWhen(fn() => request()->is('admin/employee-attendance-events*')),
                            ])
                    )

                    // Workforce Scheduling
                    ->group(
                        NavigationGroup::make('Workforce Scheduling')
                            ->collapsible()
                            ->items([
                                NavigationItem::make('Roster Periods')
                                    ->icon('heroicon-o-calendar-days')
                                    ->visible(fn(): bool => WorkforceRosterPeriodResource::canViewAny())
                                    ->url(WorkforceRosterPeriodResource::getUrl(panel: 'admin')),

                                NavigationItem::make('Shift Assignments')
                                    ->icon('heroicon-o-calendar')
                                    ->visible(fn(): bool => WorkforceRosterAssignmentResource::canViewAny())
                                    ->url(WorkforceRosterAssignmentResource::getUrl(panel: 'admin')),

                                NavigationItem::make('Rotation Templates')
                                    ->icon('heroicon-o-arrow-path-rounded-square')
                                    ->visible(fn(): bool => WorkforceRotationTemplateResource::canViewAny())
                                    ->url(WorkforceRotationTemplateResource::getUrl(panel: 'admin')),

                                NavigationItem::make('Rotation Assignments')
                                    ->icon('heroicon-o-arrows-right-left')
                                    ->visible(fn(): bool => WorkforceRotationAssignmentResource::canViewAny())
                                    ->url(WorkforceRotationAssignmentResource::getUrl(panel: 'admin')),

                                NavigationItem::make('Staffing Requirements')
                                    ->icon('heroicon-o-user-group')
                                    ->visible(fn(): bool => WorkforceStaffingRequirementResource::canViewAny())
                                    ->url(WorkforceStaffingRequirementResource::getUrl(panel: 'admin')),

                                NavigationItem::make('Roster Roles')
                                    ->icon('heroicon-o-identification')
                                    ->visible(fn(): bool => WorkforceRosterRoleResource::canViewAny())
                                    ->url(WorkforceRosterRoleResource::getUrl(panel: 'admin')),

                                NavigationItem::make('Employee Availability')
                                    ->icon('heroicon-o-clock')
                                    ->visible(fn(): bool => EmployeeWorkAvailabilityResource::canViewAny())
                                    ->url(EmployeeWorkAvailabilityResource::getUrl(panel: 'admin')),

                                NavigationItem::make('Shift Swaps')
                                    ->icon('heroicon-o-arrow-path')
                                    ->visible(fn(): bool => WorkforceShiftSwapRequestResource::canViewAny())
                                    ->url(WorkforceShiftSwapRequestResource::getUrl(panel: 'admin')),

                                NavigationItem::make('Shift Replacements')
                                    ->icon('heroicon-o-user-plus')
                                    ->visible(fn(): bool => WorkforceShiftReplacementResource::canViewAny())
                                    ->url(WorkforceShiftReplacementResource::getUrl(panel: 'admin')),

                                NavigationItem::make('Scheduling Rules')
                                    ->icon('heroicon-o-adjustments-horizontal')
                                    ->visible(fn(): bool => WorkforceSchedulingRuleResource::canViewAny())
                                    ->url(WorkforceSchedulingRuleResource::getUrl(panel: 'admin')),

                                NavigationItem::make('Roster History')
                                    ->icon('heroicon-o-clock')
                                    ->visible(fn(): bool => WorkforceRosterHistoryResource::canViewAny())
                                    ->url(WorkforceRosterHistoryResource::getUrl(panel: 'admin')),
                            ])
                    )

                    // Performance Management
                    ->group(
                        NavigationGroup::make('Performance Management')
                            ->collapsible()
                            ->items([
                                NavigationItem::make('Rating Scales')
                                    ->icon('heroicon-o-star')
                                    ->visible(fn(): bool => PerformanceRatingScaleResource::canViewAny())
                                    ->url(PerformanceRatingScaleResource::getUrl(panel: 'admin')),

                                NavigationItem::make('Appraisal Cycles')
                                    ->icon('heroicon-o-calendar-date-range')
                                    ->visible(fn(): bool => PerformanceAppraisalCycleResource::canViewAny())
                                    ->url(PerformanceAppraisalCycleResource::getUrl(panel: 'admin')),

                                NavigationItem::make('Appraisal Templates')
                                    ->icon('heroicon-o-document-duplicate')
                                    ->visible(fn(): bool => PerformanceAppraisalTemplateResource::canViewAny())
                                    ->url(PerformanceAppraisalTemplateResource::getUrl(panel: 'admin')),

                                NavigationItem::make('Competency Frameworks')
                                    ->icon('heroicon-o-squares-plus')
                                    ->visible(fn(): bool => PerformanceCompetencyFrameworkResource::canViewAny())
                                    ->url(PerformanceCompetencyFrameworkResource::getUrl(panel: 'admin')),

                                NavigationItem::make('Competencies')
                                    ->icon('heroicon-o-academic-cap')
                                    ->visible(fn(): bool => PerformanceCompetencyResource::canViewAny())
                                    ->url(PerformanceCompetencyResource::getUrl(panel: 'admin')),

                                NavigationItem::make('Position Competencies')
                                    ->icon('heroicon-o-briefcase')
                                    ->visible(fn(): bool => PerformancePositionCompetencyResource::canViewAny())
                                    ->url(PerformancePositionCompetencyResource::getUrl(panel: 'admin')),

                                NavigationItem::make('Goal Plans')
                                    ->icon('heroicon-o-flag')
                                    ->visible(fn(): bool => PerformanceGoalPlanResource::canViewAny())
                                    ->url(PerformanceGoalPlanResource::getUrl(panel: 'admin')),

                                NavigationItem::make('Employee Goals')
                                    ->icon('heroicon-o-flag')
                                    ->visible(fn(): bool => PerformanceGoalResource::canViewAny())
                                    ->url(PerformanceGoalResource::getUrl(panel: 'admin')),

                                NavigationItem::make('Appraisals')
                                    ->icon('heroicon-o-clipboard-document-check')
                                    ->visible(fn(): bool => PerformanceAppraisalResource::canViewAny())
                                    ->url(PerformanceAppraisalResource::getUrl(panel: 'admin')),

                                NavigationItem::make('Moderation Sessions')
                                    ->icon('heroicon-o-scale')
                                    ->visible(fn(): bool => PerformanceAppraisalModerationSessionResource::canViewAny())
                                    ->url(PerformanceAppraisalModerationSessionResource::getUrl(panel: 'admin')),

                                NavigationItem::make('Development Plans')
                                    ->icon('heroicon-o-arrow-trending-up')
                                    ->visible(fn(): bool => PerformanceDevelopmentPlanResource::canViewAny())
                                    ->url(PerformanceDevelopmentPlanResource::getUrl(panel: 'admin')),

                                NavigationItem::make('Performance Improvement Plans')
                                    ->icon('heroicon-o-wrench-screwdriver')
                                    ->visible(fn(): bool => PerformanceImprovementPlanResource::canViewAny())
                                    ->url(PerformanceImprovementPlanResource::getUrl(panel: 'admin')),

                                NavigationItem::make('Probation Reviews')
                                    ->icon('heroicon-o-check-badge')
                                    ->visible(fn(): bool => PerformanceProbationReviewResource::canViewAny())
                                    ->url(PerformanceProbationReviewResource::getUrl(panel: 'admin')),

                                NavigationItem::make('Appraisal Disputes')
                                    ->icon('heroicon-o-exclamation-triangle')
                                    ->visible(fn(): bool => PerformanceAppraisalDisputeResource::canViewAny())
                                    ->url(PerformanceAppraisalDisputeResource::getUrl(panel: 'admin')),

                                NavigationItem::make('Recommendations')
                                    ->icon('heroicon-o-light-bulb')
                                    ->visible(fn(): bool => PerformanceAppraisalRecommendationResource::canViewAny())
                                    ->url(PerformanceAppraisalRecommendationResource::getUrl(panel: 'admin')),

                                NavigationItem::make('Appraisal History')
                                    ->icon('heroicon-o-clock')
                                    ->visible(fn(): bool => PerformanceAppraisalHistoryResource::canViewAny())
                                    ->url(PerformanceAppraisalHistoryResource::getUrl(panel: 'admin')),
                            ])
                    )

                    // Recruitment & Onboarding
                    ->group(
                        NavigationGroup::make('Recruitment & Onboarding')
                            ->collapsible()
                            ->items([
                                NavigationItem::make('Recruitment Dashboard')
                                    ->icon('heroicon-o-chart-bar-square')
                                    ->visible(fn(): bool => auth()->user()?->can('hr.recruitment_report.view') ?? false)
                                    ->url(RecruitmentDashboard::getUrl(panel: 'admin')),

                                NavigationItem::make('Workforce Requisitions')
                                    ->icon('heroicon-o-clipboard-document-list')
                                    ->visible(fn(): bool => RecruitmentRequisitionResource::canViewAny())
                                    ->url(RecruitmentRequisitionResource::getUrl(panel: 'admin')),

                                NavigationItem::make('Vacancies')
                                    ->icon('heroicon-o-briefcase')
                                    ->visible(fn(): bool => RecruitmentVacancyResource::canViewAny())
                                    ->url(RecruitmentVacancyResource::getUrl(panel: 'admin')),

                                NavigationItem::make('Job Postings')
                                    ->icon('heroicon-o-megaphone')
                                    ->visible(fn(): bool => RecruitmentJobPostingResource::canViewAny())
                                    ->url(RecruitmentJobPostingResource::getUrl(panel: 'admin')),

                                NavigationItem::make('Candidates')
                                    ->icon('heroicon-o-users')
                                    ->visible(fn(): bool => RecruitmentCandidateResource::canViewAny())
                                    ->url(RecruitmentCandidateResource::getUrl(panel: 'admin')),

                                NavigationItem::make('Applications')
                                    ->icon('heroicon-o-document-text')
                                    ->visible(fn(): bool => RecruitmentApplicationResource::canViewAny())
                                    ->url(RecruitmentApplicationResource::getUrl(panel: 'admin')),

                                NavigationItem::make('Screening')
                                    ->icon('heroicon-o-funnel')
                                    ->visible(fn(): bool => RecruitmentApplicationScreeningResource::canViewAny())
                                    ->url(RecruitmentApplicationScreeningResource::getUrl(panel: 'admin')),

                                NavigationItem::make('Assessments')
                                    ->icon('heroicon-o-clipboard-document-check')
                                    ->visible(fn(): bool => RecruitmentAssessmentResource::canViewAny())
                                    ->url(RecruitmentAssessmentResource::getUrl(panel: 'admin')),

                                NavigationItem::make('Interviews')
                                    ->icon('heroicon-o-chat-bubble-left-right')
                                    ->visible(fn(): bool => RecruitmentInterviewResource::canViewAny())
                                    ->url(RecruitmentInterviewResource::getUrl(panel: 'admin')),

                                NavigationItem::make('Interview Panels')
                                    ->icon('heroicon-o-user-group')
                                    ->visible(fn(): bool => RecruitmentInterviewPanelResource::canViewAny())
                                    ->url(RecruitmentInterviewPanelResource::getUrl(panel: 'admin')),

                                NavigationItem::make('Selection Reviews')
                                    ->icon('heroicon-o-check-badge')
                                    ->visible(fn(): bool => RecruitmentSelectionReviewResource::canViewAny())
                                    ->url(RecruitmentSelectionReviewResource::getUrl(panel: 'admin')),

                                NavigationItem::make('Offers')
                                    ->icon('heroicon-o-envelope')
                                    ->visible(fn(): bool => RecruitmentOfferResource::canViewAny())
                                    ->url(RecruitmentOfferResource::getUrl(panel: 'admin')),

                                NavigationItem::make('Pre-Employment Checks')
                                    ->icon('heroicon-o-shield-check')
                                    ->visible(fn(): bool => RecruitmentPreEmploymentCheckResource::canViewAny())
                                    ->url(RecruitmentPreEmploymentCheckResource::getUrl(panel: 'admin')),

                                NavigationItem::make('Onboarding Plans')
                                    ->icon('heroicon-o-academic-cap')
                                    ->visible(fn(): bool => RecruitmentOnboardingPlanResource::canViewAny())
                                    ->url(RecruitmentOnboardingPlanResource::getUrl(panel: 'admin')),

                                NavigationItem::make('Onboarding Tasks')
                                    ->icon('heroicon-o-list-bullet')
                                    ->visible(fn(): bool => RecruitmentOnboardingTaskResource::canViewAny())
                                    ->url(RecruitmentOnboardingTaskResource::getUrl(panel: 'admin')),

                                NavigationItem::make('Confirmation Decisions')
                                    ->icon('heroicon-o-check-circle')
                                    ->visible(fn(): bool => EmployeeConfirmationDecisionResource::canViewAny())
                                    ->url(EmployeeConfirmationDecisionResource::getUrl(panel: 'admin')),

                                NavigationItem::make('Recruitment Reports')
                                    ->icon('heroicon-o-presentation-chart-line')
                                    ->visible(fn(): bool => auth()->user()?->can('hr.recruitment_report.view') ?? false)
                                    ->url(RecruitmentReports::getUrl(panel: 'admin')),

                                NavigationItem::make('Recruitment History')
                                    ->icon('heroicon-o-clock')
                                    ->visible(fn(): bool => RecruitmentHistoryResource::canViewAny())
                                    ->url(RecruitmentHistoryResource::getUrl(panel: 'admin')),
                            ])
                    )

                    // Recruitment Setup
                    ->group(
                        NavigationGroup::make('Recruitment Setup')
                            ->collapsed()
                            ->items([
                                NavigationItem::make('Screening Templates')
                                    ->icon('heroicon-o-adjustments-horizontal')
                                    ->visible(fn(): bool => RecruitmentScreeningTemplateResource::canViewAny())
                                    ->url(RecruitmentScreeningTemplateResource::getUrl(panel: 'admin')),

                                NavigationItem::make('Interview Scorecards')
                                    ->icon('heroicon-o-list-bullet')
                                    ->visible(fn(): bool => RecruitmentInterviewScorecardTemplateResource::canViewAny())
                                    ->url(RecruitmentInterviewScorecardTemplateResource::getUrl(panel: 'admin')),

                                NavigationItem::make('Onboarding Templates')
                                    ->icon('heroicon-o-document-duplicate')
                                    ->visible(fn(): bool => RecruitmentOnboardingTemplateResource::canViewAny())
                                    ->url(RecruitmentOnboardingTemplateResource::getUrl(panel: 'admin')),
                            ])
                    )

                    // Attendance Review & Payroll Integration
                    ->group(
                        NavigationGroup::make('Attendance Review')
                            ->collapsed()
                            ->items([
                                NavigationItem::make('Review Periods')
                                    ->icon('heroicon-o-calendar-date-range')
                                    ->visible(fn(): bool => AttendanceReviewPeriodResource::canViewAny())
                                    ->url(AttendanceReviewPeriodResource::getUrl(panel: 'admin')),

                                NavigationItem::make('Review Items')
                                    ->icon('heroicon-o-exclamation-circle')
                                    ->visible(fn(): bool => AttendanceReviewItemResource::canViewAny())
                                    ->url(AttendanceReviewItemResource::getUrl(panel: 'admin')),

                                NavigationItem::make('Payroll Review Batches')
                                    ->icon('heroicon-o-queue-list')
                                    ->visible(fn(): bool => AttendancePayrollReviewBatchResource::canViewAny())
                                    ->url(AttendancePayrollReviewBatchResource::getUrl(panel: 'admin')),

                                NavigationItem::make('Payroll Review Lines')
                                    ->icon('heroicon-o-list-bullet')
                                    ->visible(fn(): bool => AttendancePayrollReviewBatchLineResource::canViewAny())
                                    ->url(AttendancePayrollReviewBatchLineResource::getUrl(panel: 'admin')),

                                NavigationItem::make('Attendance Payroll Rules')
                                    ->icon('heroicon-o-calculator')
                                    ->visible(fn(): bool => AttendancePayrollRuleResource::canViewAny())
                                    ->url(AttendancePayrollRuleResource::getUrl(panel: 'admin')),
                            ])
                    )

                    // Payroll
                    ->group(
                        NavigationGroup::make('Payroll')
                            ->collapsible()
                            ->items([
                                NavigationItem::make('Payroll Periods')
                                    ->icon('heroicon-o-calendar-date-range')
                                    ->visible(fn(): bool => PayrollPeriodResource::canViewAny())
                                    ->url(PayrollPeriodResource::getUrl(panel: 'admin')),

                                NavigationItem::make('Payroll Documents')
                                    ->icon('heroicon-o-document-currency-dollar')
                                    ->visible(fn(): bool => PayrollDocumentResource::canViewAny())
                                    ->url(PayrollDocumentResource::getUrl(panel: 'admin')),

                                NavigationItem::make('Employee Payslips')
                                    ->icon('heroicon-o-document-text')
                                    ->visible(fn(): bool => EmployeePayslipResource::canViewAny())
                                    ->url(EmployeePayslipResource::getUrl(panel: 'admin')),

                                NavigationItem::make('Payslip History')
                                    ->icon('heroicon-o-clock')
                                    ->visible(fn(): bool => EmployeePayslipHistoryResource::canViewAny())
                                    ->url(EmployeePayslipHistoryResource::getUrl(panel: 'admin')),

                                NavigationItem::make('Pay Codes')
                                    ->icon('heroicon-o-banknotes')
                                    ->visible(fn(): bool => PayCodeResource::canViewAny())
                                    ->url(PayCodeResource::getUrl(panel: 'admin')),

                                NavigationItem::make('Payroll Posting Groups')
                                    ->icon('heroicon-o-squares-2x2')
                                    ->visible(fn(): bool => PayrollPostingGroupResource::canViewAny())
                                    ->url(PayrollPostingGroupResource::getUrl(panel: 'admin')),

                                NavigationItem::make('Tax Tables')
                                    ->icon('heroicon-o-table-cells')
                                    ->url('/admin/tax-tables')
                                    ->isActiveWhen(fn(): bool => request()->is('admin/tax-tables*')),

                                NavigationItem::make('Social Security Tiers')
                                    ->icon('heroicon-o-square-2-stack')
                                    ->url('/admin/social-security-tiers')
                                    ->isActiveWhen(fn(): bool => request()->is('admin/social-security-tiers*')),
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
