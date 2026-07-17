<?php

namespace App\Providers;

use App\Events\FixedAssetPosted;
use App\Events\PaymentApplied;
use App\Events\PaymentUnapplied;
use App\Events\PayrollPosted;
use App\Events\PayrollSalaryPaid;
use App\Events\ProductionOrderStatusChanged;
use App\Listeners\RecordAuditTrailForDomainEvent;
use App\Models\AccountingPeriod;
use App\Models\ActualOverheadCost;
use App\Models\AttendanceCorrectionRequest;
use App\Models\AttendanceDevice;
use App\Models\AttendanceLedgerEntry;
use App\Models\AttendanceLocation;
use App\Models\AttendancePayrollReviewBatch;
use App\Models\AttendancePayrollReviewBatchLine;
use App\Models\AttendancePayrollRule;
use App\Models\AttendanceReviewItem;
use App\Models\AttendanceReviewPeriod;
use App\Models\AuditTrail;
use App\Models\BankAccount;
use App\Models\BlanketOrder;
use App\Models\Business;
use App\Models\ChartOfAccount;
use App\Models\CurrencyAdjustmentLedger;
use App\Models\Customer;
use App\Models\CustomerLedgerEntry;
use App\Models\CustomerPostingGroup;
use App\Models\Employee;
use App\Models\EmployeeAttendanceDay;
use App\Models\EmployeeAttendanceEvent;
use App\Models\EmployeeIdCard;
use App\Models\EmployeeIdCardHistory;
use App\Models\EmployeeIdCardPrintBatch;
use App\Models\EmployeeIdCardTemplate;
use App\Models\EmployeeIdCardVerificationLog;
use App\Models\EmployeeLeaveEntitlement;
use App\Models\EmployeeLeaveLedgerEntry;
use App\Models\EmployeePayslip;
use App\Models\EmployeePayslipHistory;
use App\Models\EmployeePostingGroup;
use App\Models\EmployeeShift;
use App\Models\EmployeeWorkAvailability;
use App\Models\EmployeeWorkScheduleAssignment;
use App\Models\FAPostingGroup;
use App\Models\FixedAsset;
use App\Models\GeneralBusinessPostingGroup;
use App\Models\GeneralJournalBatch;
use App\Models\GeneralLedgerSetup;
use App\Models\GeneralPostingSetup;
use App\Models\GeneralPostingSetupLine;
use App\Models\GeneralProductPostingGroup;
use App\Models\GlEntry;
use App\Models\InventoryPostingGroup;
use App\Models\InventoryPostingSetup;
use App\Models\Item;
use App\Models\LeavePolicy;
use App\Models\LeaveRequest;
use App\Models\LeaveType;
use App\Models\MaintenanceContract;
use App\Models\MaintenanceContractSchedule;
use App\Models\Manufacturing\CapExProject;
use App\Models\Manufacturing\MachineCenter;
use App\Models\Manufacturing\ProductionBom;
use App\Models\Manufacturing\ProductionBomVersion;
use App\Models\Manufacturing\ProductionOrder;
use App\Models\Manufacturing\Routing;
use App\Models\Manufacturing\RoutingVersion;
use App\Models\Manufacturing\WorkCenter;
use App\Models\Manufacturing\WorkCenterCalendar;
use App\Models\Manufacturing\WorkCenterGroup;
use App\Models\NumberSeries;
use App\Models\OverheadCostCategory;
use App\Models\OvertimeApproval;
use App\Models\PayCode;
use App\Models\Payment;
use App\Models\PayrollDocument;
use App\Models\PayrollPeriod;
use App\Models\PayrollPostingGroup;
use App\Models\PerformanceAppraisal;
use App\Models\PerformanceAppraisalDispute;
use App\Models\PerformanceAppraisalHistory;
use App\Models\PerformanceDevelopmentPlan;
use App\Models\PerformanceGoal;
use App\Models\PerformanceGoalPlan;
use App\Models\PerformanceImprovementPlan;
use App\Models\PerformanceProbationReview;
use App\Models\Permission;
use App\Models\PettyCashFund;
use App\Models\PettyCashVoucher;
use App\Models\PostedSalesInvoice;
use App\Models\PurchaseCreditMemo;
use App\Models\PurchaseInvoice;
use App\Models\PurchaseOrder;
use App\Models\PurchaseQuote;
use App\Models\PurchaseReceipt;
use App\Models\Role;
use App\Models\SalesCreditMemo;
use App\Models\SalesCreditMemoLine;
use App\Models\SalesInvoice;
use App\Models\SalesOrder;
use App\Models\SalesQuote;
use App\Models\UnitOfMeasure;
use App\Models\User;
use App\Models\VatBusinessPostingGroup;
use App\Models\VatPostingSetup;
use App\Models\VatProductPostingGroup;
use App\Models\Vendor;
use App\Models\VendorPostingGroup;
use App\Models\WarehouseActivity;
use App\Models\WarehousePutaway;
use App\Models\WarehouseReceipt;
use App\Models\WarehouseShipment;
use App\Models\WorkforceRosterAssignment;
use App\Models\WorkforceRosterHistory;
use App\Models\WorkforceRosterPeriod;
use App\Models\WorkforceRosterRole;
use App\Models\WorkforceRotationAssignment;
use App\Models\WorkforceRotationTemplate;
use App\Models\WorkforceSchedulingRule;
use App\Models\WorkforceShiftReplacement;
use App\Models\WorkforceShiftSwapRequest;
use App\Models\WorkforceStaffingRequirement;
use App\Observers\GlEntryObserver;
use App\Observers\SalesCreditMemoLineObserver;
use App\Observers\SensitiveSetupAuditObserver;
use App\Observers\UserAuditObserver;
use App\Policies\ActualOverheadCostPolicy;
use App\Policies\AttendanceCorrectionRequestPolicy;
use App\Policies\AttendanceDevicePolicy;
use App\Policies\AttendanceLedgerEntryPolicy;
use App\Policies\AttendanceLocationPolicy;
use App\Policies\AttendancePayrollReviewBatchLinePolicy;
use App\Policies\AttendancePayrollReviewBatchPolicy;
use App\Policies\AttendancePayrollRulePolicy;
use App\Policies\AttendanceReviewItemPolicy;
use App\Policies\AttendanceReviewPeriodPolicy;
use App\Policies\AuditTrailPolicy;
use App\Policies\BankAccountPolicy;
use App\Policies\BlanketOrderPolicy;
use App\Policies\BusinessPolicy;
use App\Policies\CapExProjectPolicy;
use App\Policies\CurrencyAdjustmentLedgerPolicy;
use App\Policies\CustomerLedgerEntryPolicy;
use App\Policies\CustomerPolicy;
use App\Policies\EmployeeAttendanceDayPolicy;
use App\Policies\EmployeeAttendanceEventPolicy;
use App\Policies\EmployeeIdCardHistoryPolicy;
use App\Policies\EmployeeIdCardPolicy;
use App\Policies\EmployeeIdCardPrintBatchPolicy;
use App\Policies\EmployeeIdCardTemplatePolicy;
use App\Policies\EmployeeIdCardVerificationLogPolicy;
use App\Policies\EmployeeLeaveEntitlementPolicy;
use App\Policies\EmployeeLeaveLedgerEntryPolicy;
use App\Policies\EmployeePayslipHistoryPolicy;
use App\Policies\EmployeePayslipPolicy;
use App\Policies\EmployeePolicy;
use App\Policies\EmployeeShiftPolicy;
use App\Policies\EmployeeWorkAvailabilityPolicy;
use App\Policies\EmployeeWorkScheduleAssignmentPolicy;
use App\Policies\FixedAssetPolicy;
use App\Policies\GeneralJournalBatchPolicy;
use App\Policies\GenericFilamentPolicy;
use App\Policies\ItemPolicy;
use App\Policies\LeavePolicyPolicy;
use App\Policies\LeaveRequestPolicy;
use App\Policies\LeaveTypePolicy;
use App\Policies\MachineCenterPolicy;
use App\Policies\MaintenanceContractPolicy;
use App\Policies\MaintenanceContractSchedulePolicy;
use App\Policies\OverheadCostCategoryPolicy;
use App\Policies\OvertimeApprovalPolicy;
use App\Policies\PayCodePolicy;
use App\Policies\PaymentPolicy;
use App\Policies\PayrollDocumentPolicy;
use App\Policies\PayrollPeriodPolicy;
use App\Policies\PayrollPostingGroupPolicy;
use App\Policies\PerformanceAppraisalDisputePolicy;
use App\Policies\PerformanceAppraisalHistoryPolicy;
use App\Policies\PerformanceAppraisalPolicy;
use App\Policies\PerformanceDevelopmentPlanPolicy;
use App\Policies\PerformanceGoalPlanPolicy;
use App\Policies\PerformanceGoalPolicy;
use App\Policies\PerformanceImprovementPlanPolicy;
use App\Policies\PerformanceProbationReviewPolicy;
use App\Policies\PettyCashVoucherPolicy;
use App\Policies\PostedSalesInvoicePolicy;
use App\Policies\ProductionBomPolicy;
use App\Policies\ProductionBomVersionPolicy;
use App\Policies\ProductionOrderPolicy;
use App\Policies\PurchaseCreditMemoPolicy;
use App\Policies\PurchaseInvoicePolicy;
use App\Policies\PurchaseOrderPolicy;
use App\Policies\PurchaseQuotePolicy;
use App\Policies\PurchaseReceiptPolicy;
use App\Policies\RolePolicy;
use App\Policies\RoutingPolicy;
use App\Policies\RoutingVersionPolicy;
use App\Policies\SalesCreditMemoPolicy;
use App\Policies\SalesInvoicePolicy;
use App\Policies\SalesOrderPolicy;
use App\Policies\SalesQuotePolicy;
use App\Policies\UnitOfMeasurePolicy;
use App\Policies\UserPolicy;
use App\Policies\VendorPolicy;
use App\Policies\WarehouseActivityPolicy;
use App\Policies\WarehousePutawayPolicy;
use App\Policies\WarehouseReceiptPolicy;
use App\Policies\WarehouseShipmentPolicy;
use App\Policies\WorkCenterCalendarPolicy;
use App\Policies\WorkCenterGroupPolicy;
use App\Policies\WorkCenterPolicy;
use App\Policies\WorkforceRosterAssignmentPolicy;
use App\Policies\WorkforceRosterHistoryPolicy;
use App\Policies\WorkforceRosterPeriodPolicy;
use App\Policies\WorkforceRosterRolePolicy;
use App\Policies\WorkforceRotationAssignmentPolicy;
use App\Policies\WorkforceRotationTemplatePolicy;
use App\Policies\WorkforceSchedulingRulePolicy;
use App\Policies\WorkforceShiftReplacementPolicy;
use App\Policies\WorkforceShiftSwapRequestPolicy;
use App\Policies\WorkforceStaffingRequirementPolicy;
use App\Services\AuditTrailService;
use App\Support\Filament\SensitiveActionPasswordConfirmation;
use App\Support\FilamentPermissionRegistry;
use Filament\Actions\Action;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Spatie\Permission\Events\PermissionAttachedEvent;
use Spatie\Permission\Events\PermissionDetachedEvent;
use Spatie\Permission\Events\RoleAttachedEvent;
use Spatie\Permission\Events\RoleDetachedEvent;
use Spatie\Permission\Exceptions\PermissionDoesNotExist;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Gate::before(function (User $user, string $ability, array $arguments): ?bool {
            if ($user->hasRole('super_admin')) {
                return true;
            }

            $modelClass = $arguments[0] ?? null;

            if (! is_string($modelClass)) {
                return null;
            }

            $policy = Gate::getPolicyFor($modelClass);

            if (! $policy instanceof GenericFilamentPolicy) {
                return null;
            }

            $action = Str::snake($ability);
            $classLevelActions = [
                'view_any',
                'create',
                'delete_any',
                'restore_any',
                'force_delete_any',
            ];

            if (! in_array($action, $classLevelActions, true)) {
                return null;
            }

            $parts = app(FilamentPermissionRegistry::class)->permissionPartsForModel($modelClass);

            if ($parts === null) {
                return null;
            }

            $permissionName = "{$parts['module']}.{$parts['resource']}.{$action}";
            try {
                return $user->hasPermissionTo($permissionName, config('auth.defaults.guard', 'web'));
            } catch (PermissionDoesNotExist) {
                return false;
            }
        });
        Gate::policy(SalesQuote::class, SalesQuotePolicy::class);
        Gate::policy(SalesOrder::class, SalesOrderPolicy::class);
        Gate::policy(SalesInvoice::class, SalesInvoicePolicy::class);
        Gate::policy(PostedSalesInvoice::class, PostedSalesInvoicePolicy::class);
        Gate::policy(SalesCreditMemo::class, SalesCreditMemoPolicy::class);
        Gate::policy(Customer::class, CustomerPolicy::class);
        Gate::policy(AuditTrail::class, AuditTrailPolicy::class);
        Gate::policy(Item::class, ItemPolicy::class);
        Gate::policy(Payment::class, PaymentPolicy::class);
        Gate::policy(PettyCashVoucher::class, PettyCashVoucherPolicy::class);
        Gate::policy(OverheadCostCategory::class, OverheadCostCategoryPolicy::class);
        Gate::policy(ActualOverheadCost::class, ActualOverheadCostPolicy::class);
        Gate::policy(BankAccount::class, BankAccountPolicy::class);
        Gate::policy(Business::class, BusinessPolicy::class);
        Gate::policy(GeneralJournalBatch::class, GeneralJournalBatchPolicy::class);
        Gate::policy(CurrencyAdjustmentLedger::class, CurrencyAdjustmentLedgerPolicy::class);
        Gate::policy(CustomerLedgerEntry::class, CustomerLedgerEntryPolicy::class);
        Gate::policy(WarehouseReceipt::class, WarehouseReceiptPolicy::class);
        Gate::policy(WarehouseActivity::class, WarehouseActivityPolicy::class);
        Gate::policy(WarehousePutaway::class, WarehousePutawayPolicy::class);
        Gate::policy(WarehouseShipment::class, WarehouseShipmentPolicy::class);
        Gate::policy(ProductionOrder::class, ProductionOrderPolicy::class);
        Gate::policy(ProductionBom::class, ProductionBomPolicy::class);
        Gate::policy(ProductionBomVersion::class, ProductionBomVersionPolicy::class);
        Gate::policy(Routing::class, RoutingPolicy::class);
        Gate::policy(RoutingVersion::class, RoutingVersionPolicy::class);
        Gate::policy(MachineCenter::class, MachineCenterPolicy::class);
        Gate::policy(WorkCenter::class, WorkCenterPolicy::class);
        Gate::policy(WorkCenterCalendar::class, WorkCenterCalendarPolicy::class);
        Gate::policy(WorkCenterGroup::class, WorkCenterGroupPolicy::class);
        Gate::policy(Employee::class, EmployeePolicy::class);
        Gate::policy(EmployeeIdCard::class, EmployeeIdCardPolicy::class);
        Gate::policy(EmployeeIdCardTemplate::class, EmployeeIdCardTemplatePolicy::class);
        Gate::policy(EmployeeIdCardPrintBatch::class, EmployeeIdCardPrintBatchPolicy::class);
        Gate::policy(EmployeeIdCardHistory::class, EmployeeIdCardHistoryPolicy::class);
        Gate::policy(EmployeeIdCardVerificationLog::class, EmployeeIdCardVerificationLogPolicy::class);
        Gate::policy(EmployeePayslip::class, EmployeePayslipPolicy::class);
        Gate::policy(EmployeePayslipHistory::class, EmployeePayslipHistoryPolicy::class);
        Gate::policy(LeaveType::class, LeaveTypePolicy::class);
        Gate::policy(LeavePolicy::class, LeavePolicyPolicy::class);
        Gate::policy(EmployeeLeaveEntitlement::class, EmployeeLeaveEntitlementPolicy::class);
        Gate::policy(LeaveRequest::class, LeaveRequestPolicy::class);
        Gate::policy(EmployeeLeaveLedgerEntry::class, EmployeeLeaveLedgerEntryPolicy::class);
        Gate::policy(FixedAsset::class, FixedAssetPolicy::class);
        Gate::policy(AttendanceLocation::class, AttendanceLocationPolicy::class);
        Gate::policy(AttendanceDevice::class, AttendanceDevicePolicy::class);
        Gate::policy(EmployeeShift::class, EmployeeShiftPolicy::class);
        Gate::policy(EmployeeWorkScheduleAssignment::class, EmployeeWorkScheduleAssignmentPolicy::class);
        Gate::policy(EmployeeWorkAvailability::class, EmployeeWorkAvailabilityPolicy::class);
        Gate::policy(EmployeeAttendanceEvent::class, EmployeeAttendanceEventPolicy::class);
        Gate::policy(EmployeeAttendanceDay::class, EmployeeAttendanceDayPolicy::class);
        Gate::policy(AttendanceCorrectionRequest::class, AttendanceCorrectionRequestPolicy::class);
        Gate::policy(OvertimeApproval::class, OvertimeApprovalPolicy::class);
        Gate::policy(AttendanceLedgerEntry::class, AttendanceLedgerEntryPolicy::class);
        Gate::policy(AttendanceReviewPeriod::class, AttendanceReviewPeriodPolicy::class);
        Gate::policy(AttendanceReviewItem::class, AttendanceReviewItemPolicy::class);
        Gate::policy(AttendancePayrollReviewBatch::class, AttendancePayrollReviewBatchPolicy::class);
        Gate::policy(AttendancePayrollReviewBatchLine::class, AttendancePayrollReviewBatchLinePolicy::class);
        Gate::policy(AttendancePayrollRule::class, AttendancePayrollRulePolicy::class);
        Gate::policy(WorkforceRosterPeriod::class, WorkforceRosterPeriodPolicy::class);
        Gate::policy(WorkforceRosterAssignment::class, WorkforceRosterAssignmentPolicy::class);
        Gate::policy(WorkforceRosterRole::class, WorkforceRosterRolePolicy::class);
        Gate::policy(WorkforceRotationTemplate::class, WorkforceRotationTemplatePolicy::class);
        Gate::policy(WorkforceRotationAssignment::class, WorkforceRotationAssignmentPolicy::class);
        Gate::policy(WorkforceStaffingRequirement::class, WorkforceStaffingRequirementPolicy::class);
        Gate::policy(WorkforceShiftSwapRequest::class, WorkforceShiftSwapRequestPolicy::class);
        Gate::policy(WorkforceShiftReplacement::class, WorkforceShiftReplacementPolicy::class);
        Gate::policy(WorkforceSchedulingRule::class, WorkforceSchedulingRulePolicy::class);
        Gate::policy(WorkforceRosterHistory::class, WorkforceRosterHistoryPolicy::class);
        Gate::policy(PerformanceGoalPlan::class, PerformanceGoalPlanPolicy::class);
        Gate::policy(PerformanceGoal::class, PerformanceGoalPolicy::class);
        Gate::policy(PerformanceAppraisal::class, PerformanceAppraisalPolicy::class);
        Gate::policy(PerformanceAppraisalDispute::class, PerformanceAppraisalDisputePolicy::class);
        Gate::policy(PerformanceDevelopmentPlan::class, PerformanceDevelopmentPlanPolicy::class);
        Gate::policy(PerformanceImprovementPlan::class, PerformanceImprovementPlanPolicy::class);
        Gate::policy(PerformanceProbationReview::class, PerformanceProbationReviewPolicy::class);
        Gate::policy(PerformanceAppraisalHistory::class, PerformanceAppraisalHistoryPolicy::class);
        Gate::policy(PayrollDocument::class, PayrollDocumentPolicy::class);
        Gate::policy(PayrollPeriod::class, PayrollPeriodPolicy::class);
        Gate::policy(PayrollPostingGroup::class, PayrollPostingGroupPolicy::class);
        Gate::policy(PayCode::class, PayCodePolicy::class);
        Gate::policy(Vendor::class, VendorPolicy::class);
        Gate::policy(PurchaseQuote::class, PurchaseQuotePolicy::class);
        Gate::policy(PurchaseOrder::class, PurchaseOrderPolicy::class);
        Gate::policy(PurchaseReceipt::class, PurchaseReceiptPolicy::class);
        Gate::policy(PurchaseInvoice::class, PurchaseInvoicePolicy::class);
        Gate::policy(PurchaseCreditMemo::class, PurchaseCreditMemoPolicy::class);
        Gate::policy(BlanketOrder::class, BlanketOrderPolicy::class);
        Gate::policy(CapExProject::class, CapExProjectPolicy::class);
        Gate::policy(MaintenanceContract::class, MaintenanceContractPolicy::class);
        Gate::policy(MaintenanceContractSchedule::class, MaintenanceContractSchedulePolicy::class);
        Gate::policy(UnitOfMeasure::class, UnitOfMeasurePolicy::class);
        Gate::policy(User::class, UserPolicy::class);
        Gate::policy(Role::class, RolePolicy::class);

        foreach (app(FilamentPermissionRegistry::class)->resources() as $resourceClass) {
            if (! method_exists($resourceClass, 'getModel')) {
                continue;
            }

            $modelClass = $resourceClass::getModel();

            if (Gate::getPolicyFor($modelClass) === null) {
                Gate::policy($modelClass, GenericFilamentPolicy::class);
            }
        }

        $this->registerGenericPoliciesForRelationModels();

        Relation::morphMap([
            'CUSTOMER' => Customer::class,
            'VENDOR' => Vendor::class,
        ]);

        SalesCreditMemoLine::observe(SalesCreditMemoLineObserver::class);
        GlEntry::observe(GlEntryObserver::class);
        User::observe(UserAuditObserver::class);

        $this->registerAuthenticationAuditListeners();
        $this->registerAuditTrailListeners();
        $this->registerSensitiveSetupObservers();
        $this->registerSensitiveFilamentActionConfirmation();
    }

    private function registerSensitiveFilamentActionConfirmation(): void
    {
        Action::configureUsing(
            function (Action $action): void {
                SensitiveActionPasswordConfirmation::configureAction($action);
            },
            isImportant: true,
        );
    }

    private function registerGenericPoliciesForRelationModels(): void
    {
        foreach (File::allFiles(app_path('Models')) as $file) {
            $modelClass = 'App\\Models\\'.str_replace(['/', '.php'], ['\\', ''], $file->getRelativePathname());

            if (
                ! class_exists($modelClass)
                || ! is_subclass_of($modelClass, Model::class)
                || Gate::getPolicyFor($modelClass) !== null
            ) {
                continue;
            }

            Gate::policy($modelClass, GenericFilamentPolicy::class);
        }
    }

    private function registerAuthenticationAuditListeners(): void
    {
        Event::listen(Login::class, function (Login $event): void {
            app(AuditTrailService::class)->recordGeneric(
                eventType: 'auth',
                action: 'login',
                auditable: $event->user,
                userId: $event->user->getAuthIdentifier(),
                description: 'User logged in',
                metadata: [
                    'guard' => $event->guard,
                    'remember' => $event->remember,
                ],
            );
        });

        Event::listen(Failed::class, function (Failed $event): void {
            app(AuditTrailService::class)->recordGeneric(
                eventType: 'auth',
                action: 'failed_login',
                auditable: $event->user,
                userId: $event->user?->getAuthIdentifier(),
                description: 'Failed login attempt',
                metadata: [
                    'guard' => $event->guard,
                    'email' => $event->credentials['email'] ?? null,
                ],
            );
        });

        Event::listen(Logout::class, function (Logout $event): void {
            app(AuditTrailService::class)->recordGeneric(
                eventType: 'auth',
                action: 'logout',
                auditable: $event->user,
                userId: $event->user?->getAuthIdentifier(),
                description: 'User logged out',
                metadata: [
                    'guard' => $event->guard,
                ],
            );
        });

        Event::listen(PasswordReset::class, function (PasswordReset $event): void {
            app(AuditTrailService::class)->recordGeneric(
                eventType: 'security',
                action: 'password_reset',
                auditable: $event->user,
                userId: $event->user->getAuthIdentifier(),
                description: 'User password reset completed',
            );
        });
    }

    private function registerAuditTrailListeners(): void
    {
        Event::listen(PaymentApplied::class, [RecordAuditTrailForDomainEvent::class, 'handlePaymentApplied']);
        Event::listen(PaymentUnapplied::class, [RecordAuditTrailForDomainEvent::class, 'handlePaymentUnapplied']);
        Event::listen(PayrollPosted::class, [RecordAuditTrailForDomainEvent::class, 'handlePayrollPosted']);
        Event::listen(PayrollSalaryPaid::class, [RecordAuditTrailForDomainEvent::class, 'handlePayrollSalaryPaid']);
        Event::listen(FixedAssetPosted::class, [RecordAuditTrailForDomainEvent::class, 'handleFixedAssetPosted']);
        Event::listen(ProductionOrderStatusChanged::class, [RecordAuditTrailForDomainEvent::class, 'handleProductionOrderStatusChanged']);
        Event::listen(PermissionAttachedEvent::class, [RecordAuditTrailForDomainEvent::class, 'handlePermissionAttached']);
        Event::listen(PermissionDetachedEvent::class, [RecordAuditTrailForDomainEvent::class, 'handlePermissionDetached']);
        Event::listen(RoleAttachedEvent::class, [RecordAuditTrailForDomainEvent::class, 'handleRoleAttached']);
        Event::listen(RoleDetachedEvent::class, [RecordAuditTrailForDomainEvent::class, 'handleRoleDetached']);
    }

    private function registerSensitiveSetupObservers(): void
    {
        foreach ([
            AccountingPeriod::class,
            BankAccount::class,
            ChartOfAccount::class,
            CustomerPostingGroup::class,
            EmployeePostingGroup::class,
            FAPostingGroup::class,
            GeneralBusinessPostingGroup::class,
            GeneralLedgerSetup::class,
            GeneralPostingSetup::class,
            GeneralPostingSetupLine::class,
            GeneralProductPostingGroup::class,
            InventoryPostingGroup::class,
            InventoryPostingSetup::class,
            NumberSeries::class,
            Permission::class,
            PayrollPostingGroup::class,
            PettyCashFund::class,
            Role::class,
            VatBusinessPostingGroup::class,
            VatPostingSetup::class,
            VatProductPostingGroup::class,
            VendorPostingGroup::class,
        ] as $setupModel) {
            if (class_exists($setupModel)) {
                $setupModel::observe(SensitiveSetupAuditObserver::class);
            }
        }
    }
}
