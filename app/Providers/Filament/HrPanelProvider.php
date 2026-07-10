<?php

namespace App\Providers\Filament;

use App\Filament\Hr\Widgets\HrStatsOverview;
use App\Filament\Pages\Hr\LeaveApprovalsPage;
use App\Filament\Pages\Hr\LeaveCalendarPage;
use App\Filament\Pages\Hr\MyLeaveBalancesPage;
use App\Filament\Pages\Hr\MyLeaveRequestsPage;
use App\Filament\Pages\MyAttendance;
use App\Filament\Resources\AttendanceLedgerEntries\AttendanceLedgerEntryResource;
use App\Filament\Resources\EmployeeIdCardHistories\EmployeeIdCardHistoryResource;
use App\Filament\Resources\EmployeeIdCardPrintBatches\EmployeeIdCardPrintBatchResource;
use App\Filament\Resources\EmployeeIdCards\EmployeeIdCardResource;
use App\Filament\Resources\EmployeeIdCardTemplates\EmployeeIdCardTemplateResource;
use App\Filament\Resources\EmployeeIdCardVerificationLogs\EmployeeIdCardVerificationLogResource;
use App\Filament\Resources\EmployeeLeaveEntitlements\EmployeeLeaveEntitlementResource;
use App\Filament\Resources\EmployeeLeaveLedgerEntries\EmployeeLeaveLedgerEntryResource;
use App\Filament\Resources\EmployeePayslipHistories\EmployeePayslipHistoryResource;
use App\Filament\Resources\EmployeePayslips\EmployeePayslipResource;
use App\Filament\Resources\Employees\EmployeeResource;
use App\Filament\Resources\LeavePolicies\LeavePolicyResource;
use App\Filament\Resources\LeaveRequests\LeaveRequestResource;
use App\Filament\Resources\LeaveTypes\LeaveTypeResource;
use App\Filament\Resources\PayCodes\PayCodeResource;
use App\Filament\Resources\PayrollDocuments\PayrollDocumentResource;
use App\Filament\Resources\PayrollPeriods\PayrollPeriodResource;
use App\Filament\Resources\PayrollPostingGroups\PayrollPostingGroupResource;
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
            ->spa(hasPrefetching: true)
            ->sidebarCollapsibleOnDesktop()
            ->globalSearch()
            ->globalSearchKeyBindings(['command+k', 'ctrl+k'])
            ->globalSearchFieldKeyBindingSuffix()
            ->brandName('BIFLI Globals - HR Role Center')
            ->favicon(asset('favicon.ico'))
            ->renderHook(
                PanelsRenderHook::STYLES_AFTER,
                fn (): string => <<<'HTML'
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
                EmployeeResource::class,
                EmployeeIdCardResource::class,
                EmployeeIdCardTemplateResource::class,
                EmployeeIdCardPrintBatchResource::class,
                EmployeeIdCardHistoryResource::class,
                EmployeeIdCardVerificationLogResource::class,
                AttendanceLedgerEntryResource::class,
                LeaveTypeResource::class,
                LeavePolicyResource::class,
                EmployeeLeaveEntitlementResource::class,
                LeaveRequestResource::class,
                EmployeeLeaveLedgerEntryResource::class,
                PayrollDocumentResource::class,
                EmployeePayslipResource::class,
                EmployeePayslipHistoryResource::class,
                PayrollPeriodResource::class,
                PayrollPostingGroupResource::class,
                PayCodeResource::class,
            ])
            ->pages([
                Dashboard::class,
                MyAttendance::class,
                MyLeaveRequestsPage::class,
                MyLeaveBalancesPage::class,
                LeaveApprovalsPage::class,
                LeaveCalendarPage::class,
            ])
            ->widgets([
                HrStatsOverview::class,
                AccountWidget::class,
            ])
            ->navigationGroups([
                'Human Resources',
                'Employee Identity',
                'Payroll',
                'Leave Management',
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
                'hr',
            ]);
    }
}
