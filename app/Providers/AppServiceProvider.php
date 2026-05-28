<?php

namespace App\Providers;

use App\Models\ActualOverheadCost;
use App\Models\AttendanceLedgerEntry;
use App\Models\BankAccount;
use App\Models\BlanketOrder;
use App\Models\CurrencyAdjustmentLedger;
use App\Models\Customer;
use App\Models\CustomerLedgerEntry;
use App\Models\Employee;
use App\Models\GeneralJournalBatch;
use App\Models\GlEntry;
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
use App\Models\OverheadCostCategory;
use App\Models\PayCode;
use App\Models\Payment;
use App\Models\PayrollDocument;
use App\Models\PayrollPeriod;
use App\Models\PayrollPostingGroup;
use App\Models\PurchaseCreditMemo;
use App\Models\PurchaseInvoice;
use App\Models\PurchaseOrder;
use App\Models\PurchaseQuote;
use App\Models\PurchaseReceipt;
use App\Models\SalesCreditMemoLine;
use App\Models\SalesInvoice;
use App\Models\SalesOrder;
use App\Models\SalesQuote;
use App\Models\User;
use App\Models\Vendor;
use App\Models\WarehouseActivity;
use App\Models\WarehousePutaway;
use App\Models\WarehouseReceipt;
use App\Models\WarehouseShipment;
use App\Observers\GlEntryObserver;
use App\Observers\SalesCreditMemoLineObserver;
use App\Policies\ActualOverheadCostPolicy;
use App\Policies\AttendanceLedgerEntryPolicy;
use App\Policies\BankAccountPolicy;
use App\Policies\BlanketOrderPolicy;
use App\Policies\CapExProjectPolicy;
use App\Policies\CurrencyAdjustmentLedgerPolicy;
use App\Policies\CustomerLedgerEntryPolicy;
use App\Policies\CustomerPolicy;
use App\Policies\EmployeePolicy;
use App\Policies\GeneralJournalBatchPolicy;
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
use App\Policies\ProductionBomPolicy;
use App\Policies\ProductionBomVersionPolicy;
use App\Policies\ProductionOrderPolicy;
use App\Policies\PurchaseCreditMemoPolicy;
use App\Policies\PurchaseInvoicePolicy;
use App\Policies\PurchaseOrderPolicy;
use App\Policies\PurchaseQuotePolicy;
use App\Policies\PurchaseReceiptPolicy;
use App\Policies\RoutingPolicy;
use App\Policies\RoutingVersionPolicy;
use App\Policies\SalesInvoicePolicy;
use App\Policies\SalesOrderPolicy;
use App\Policies\SalesQuotePolicy;
use App\Policies\VendorPolicy;
use App\Policies\WarehouseActivityPolicy;
use App\Policies\WarehousePutawayPolicy;
use App\Policies\WarehouseReceiptPolicy;
use App\Policies\WarehouseShipmentPolicy;
use App\Policies\WorkCenterGroupPolicy;
use App\Policies\WorkCenterPolicy;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

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
        Gate::policy(Customer::class, CustomerPolicy::class);
        Gate::policy(Item::class, ItemPolicy::class);
        Gate::policy(Payment::class, PaymentPolicy::class);
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

        Relation::morphMap([
            'CUSTOMER' => Customer::class,
            'VENDOR' => Vendor::class,
        ]);

        SalesCreditMemoLine::observe(SalesCreditMemoLineObserver::class);
        GlEntry::observe(GlEntryObserver::class);
    }
}
