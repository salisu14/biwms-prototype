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
use App\Models\AttendanceLedgerEntry;
use App\Models\AuditTrail;
use App\Models\BankAccount;
use App\Models\BlanketOrder;
use App\Models\ChartOfAccount;
use App\Models\CurrencyAdjustmentLedger;
use App\Models\Customer;
use App\Models\CustomerLedgerEntry;
use App\Models\CustomerPostingGroup;
use App\Models\Employee;
use App\Models\EmployeePostingGroup;
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
use App\Models\Manufacturing\WorkCenterGroup;
use App\Models\NumberSeries;
use App\Models\OverheadCostCategory;
use App\Models\PayCode;
use App\Models\Payment;
use App\Models\PayrollDocument;
use App\Models\PayrollPeriod;
use App\Models\PayrollPostingGroup;
use App\Models\Permission;
use App\Models\PettyCashFund;
use App\Models\PettyCashVoucher;
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
use App\Observers\GlEntryObserver;
use App\Observers\SalesCreditMemoLineObserver;
use App\Observers\SensitiveSetupAuditObserver;
use App\Policies\ActualOverheadCostPolicy;
use App\Policies\AttendanceLedgerEntryPolicy;
use App\Policies\AuditTrailPolicy;
use App\Policies\BankAccountPolicy;
use App\Policies\BlanketOrderPolicy;
use App\Policies\CapExProjectPolicy;
use App\Policies\CurrencyAdjustmentLedgerPolicy;
use App\Policies\CustomerLedgerEntryPolicy;
use App\Policies\CustomerPolicy;
use App\Policies\EmployeePolicy;
use App\Policies\FixedAssetPolicy;
use App\Policies\GeneralJournalBatchPolicy;
use App\Policies\GenericFilamentPolicy;
use App\Policies\ItemPolicy;
use App\Policies\MachineCenterPolicy;
use App\Policies\MaintenanceContractPolicy;
use App\Policies\MaintenanceContractSchedulePolicy;
use App\Policies\OverheadCostCategoryPolicy;
use App\Policies\PayCodePolicy;
use App\Policies\PaymentPolicy;
use App\Policies\PayrollDocumentPolicy;
use App\Policies\PayrollPeriodPolicy;
use App\Policies\PayrollPostingGroupPolicy;
use App\Policies\PettyCashVoucherPolicy;
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
use App\Policies\UserPolicy;
use App\Policies\VendorPolicy;
use App\Policies\WarehouseActivityPolicy;
use App\Policies\WarehousePutawayPolicy;
use App\Policies\WarehouseReceiptPolicy;
use App\Policies\WarehouseShipmentPolicy;
use App\Policies\WorkCenterGroupPolicy;
use App\Policies\WorkCenterPolicy;
use App\Support\FilamentPermissionRegistry;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Spatie\Permission\Events\PermissionAttachedEvent;
use Spatie\Permission\Events\PermissionDetachedEvent;
use Spatie\Permission\Events\RoleAttachedEvent;
use Spatie\Permission\Events\RoleDetachedEvent;

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
        Gate::before(function (User $user): ?bool {
            return $user->hasRole('super_admin') ? true : null;
        });
        Gate::policy(SalesQuote::class, SalesQuotePolicy::class);
        Gate::policy(SalesOrder::class, SalesOrderPolicy::class);
        Gate::policy(SalesInvoice::class, SalesInvoicePolicy::class);
        Gate::policy(SalesCreditMemo::class, SalesCreditMemoPolicy::class);
        Gate::policy(Customer::class, CustomerPolicy::class);
        Gate::policy(AuditTrail::class, AuditTrailPolicy::class);
        Gate::policy(Item::class, ItemPolicy::class);
        Gate::policy(Payment::class, PaymentPolicy::class);
        Gate::policy(PettyCashVoucher::class, PettyCashVoucherPolicy::class);
        Gate::policy(OverheadCostCategory::class, OverheadCostCategoryPolicy::class);
        Gate::policy(ActualOverheadCost::class, ActualOverheadCostPolicy::class);
        Gate::policy(BankAccount::class, BankAccountPolicy::class);
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
        Gate::policy(WorkCenterGroup::class, WorkCenterGroupPolicy::class);
        Gate::policy(Employee::class, EmployeePolicy::class);
        Gate::policy(FixedAsset::class, FixedAssetPolicy::class);
        Gate::policy(AttendanceLedgerEntry::class, AttendanceLedgerEntryPolicy::class);
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

        Relation::morphMap([
            'CUSTOMER' => Customer::class,
            'VENDOR' => Vendor::class,
        ]);

        SalesCreditMemoLine::observe(SalesCreditMemoLineObserver::class);
        GlEntry::observe(GlEntryObserver::class);

        $this->registerAuditTrailListeners();
        $this->registerSensitiveSetupObservers();
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
