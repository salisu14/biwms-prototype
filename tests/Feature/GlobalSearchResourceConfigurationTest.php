<?php

use App\Enums\ApprovalStatus;
use App\Enums\DepartmentStatus;
use App\Enums\DepartmentType;
use App\Enums\EmployeeAssignmentType;
use App\Enums\FAStatus;
use App\Enums\FixedAssetType;
use App\Enums\PayrollStatus;
use App\Enums\ProductionOrderStatus;
use App\Enums\PurchaseOrderStatus;
use App\Enums\PurchaseOrderType;
use App\Enums\WarehouseReceiptStatus;
use App\Filament\Resources\CapExProjects\CapExProjectResource;
use App\Filament\Resources\Customers\CustomerResource;
use App\Filament\Resources\Departments\DepartmentResource;
use App\Filament\Resources\Employees\EmployeeResource;
use App\Filament\Resources\ExpenseTransactions\ExpenseTransactionResource;
use App\Filament\Resources\FixedAssets\FixedAssetResource;
use App\Filament\Resources\GeneralPostingSetups\GeneralPostingSetupResource;
use App\Filament\Resources\InventoryPostingSetups\InventoryPostingSetupResource;
use App\Filament\Resources\Items\ItemResource;
use App\Filament\Resources\Payments\PaymentResource;
use App\Filament\Resources\PayrollDocuments\PayrollDocumentResource;
use App\Filament\Resources\PostedPurchaseCreditMemos\PostedPurchaseCreditMemoResource;
use App\Filament\Resources\ProductionOrders\FinishedProductionOrderResource;
use App\Filament\Resources\ProductionOrders\ProductionOrderResource;
use App\Filament\Resources\ProductionOrders\ReleasedProductionOrderResource;
use App\Filament\Resources\PurchaseInvoices\PurchaseInvoiceResource;
use App\Filament\Resources\PurchaseOrders\PurchaseOrderResource;
use App\Filament\Resources\PurchaseReceipts\PurchaseReceiptResource;
use App\Filament\Resources\SalesInvoices\SalesInvoiceResource;
use App\Filament\Resources\SalesOrders\SalesOrderResource;
use App\Filament\Resources\Users\UserResource;
use App\Filament\Resources\Vendors\VendorResource;
use App\Filament\Resources\WarehouseReceipts\WarehouseReceiptResource;
use App\Filament\Resources\WarehouseShipments\WarehouseShipmentResource;
use App\Models\ChartOfAccount;
use App\Models\Customer;
use App\Models\Department;
use App\Models\Employee;
use App\Models\ExpenseTransaction;
use App\Models\FAClass;
use App\Models\FixedAsset;
use App\Models\GeneralBusinessPostingGroup;
use App\Models\GeneralPostingSetup;
use App\Models\GeneralProductPostingGroup;
use App\Models\InventoryPostingGroup;
use App\Models\InventoryPostingSetup;
use App\Models\Item;
use App\Models\Location;
use App\Models\Manufacturing\CapExProject;
use App\Models\Manufacturing\ProductionOrder;
use App\Models\Payment;
use App\Models\PayrollDocument;
use App\Models\PostedPurchaseCreditMemo;
use App\Models\PurchaseInvoice;
use App\Models\PurchaseOrder;
use App\Models\PurchaseReceipt;
use App\Models\SalesInvoice;
use App\Models\SalesOrder;
use App\Models\User;
use App\Models\Vendor;
use App\Models\WarehouseReceipt;
use App\Models\WarehouseShipment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

it('searches setup resources by their real combination keys', function () {
    $businessPostingGroup = GeneralBusinessPostingGroup::factory()->create([
        'code' => 'SO-BUS',
    ]);

    $productPostingGroup = GeneralProductPostingGroup::query()->create([
        'code' => 'SO-PROD',
        'description' => 'SO Product Group',
        'default_vat_product_posting_group_id' => null,
        'auto_create_vat_prod_posting_group' => false,
        'blocked' => false,
    ]);

    GeneralPostingSetup::query()->create([
        'general_business_posting_group_id' => $businessPostingGroup->id,
        'general_product_posting_group_id' => $productPostingGroup->id,
        'blocked' => false,
    ]);

    $location = Location::factory()->create([
        'code' => 'MAIN',
        'name' => 'Main Warehouse',
    ]);

    $inventoryPostingGroup = InventoryPostingGroup::query()->create([
        'code' => 'FG',
        'description' => 'Finished Goods',
        'blocked' => false,
    ]);

    $inventoryAccount = ChartOfAccount::factory()->create([
        'account_number' => '1400',
        'name' => 'Inventory FG',
    ]);

    InventoryPostingSetup::query()->create([
        'location_id' => $location->id,
        'inventory_posting_group_id' => $inventoryPostingGroup->id,
        'inventory_account_id' => $inventoryAccount->id,
    ]);

    $generalResults = GeneralPostingSetupResource::getGlobalSearchResults('SO-BUS');
    $inventoryResults = InventoryPostingSetupResource::getGlobalSearchResults('MAIN');

    expect($generalResults)->toHaveCount(1)
        ->and($generalResults->first()->title)->toBe('SO-BUS / SO-PROD')
        ->and($inventoryResults)->toHaveCount(1)
        ->and($inventoryResults->first()->title)->toBe('MAIN / FG');
});

it('exposes richer search titles details and attributes for key records', function () {
    $user = User::factory()->create([
        'name' => 'Search Admin',
        'email' => 'search.admin@example.com',
    ]);

    Role::findOrCreate('super_admin', 'web');
    $user->assignRole('super_admin');
    $this->actingAs($user);

    $item = Item::factory()->create([
        'item_code' => 'FG-1000',
        'description' => 'Premium Chair',
        'sku' => 'SKU-CHAIR',
        'unit_price' => 125000,
    ]);

    $customer = Customer::factory()->create([
        'customer_number' => 'CUST-1000',
        'name' => 'Acme Retail',
        'email' => 'buyer@acme.test',
        'phone' => '08000000000',
    ]);
    $customer->contact?->forceFill([
        'name' => 'Acme Retail',
        'full_name' => 'Acme Retail',
        'company_name' => 'Acme Retail',
    ])->saveQuietly();
    $customer->refresh();

    $salesOrder = SalesOrder::query()->create([
        'order_number' => 'SO-2026-000002',
        'order_type' => 'SALES_ORDER',
        'customer_id' => $customer->id,
        'customer_name' => $customer->name,
        'order_date' => now()->toDateString(),
        'posting_date' => now()->toDateString(),
        'requested_delivery_date' => now()->toDateString(),
        'promised_delivery_date' => now()->toDateString(),
        'shipment_date' => now()->toDateString(),
        'currency_code' => 'NGN',
        'status' => 'DRAFT',
    ]);

    $salesInvoice = SalesInvoice::query()->create([
        'invoice_number' => 'SI-2026-000002',
        'customer_id' => $customer->id,
        'sales_order_id' => $salesOrder->id,
        'total_amount' => 250000,
        'currency_code' => 'NGN',
        'status' => ApprovalStatus::POSTED,
        'invoice_date' => now()->toDateString(),
        'due_date' => now()->addDays(7)->toDateString(),
    ]);

    $warehouseLocation = Location::factory()->create([
        'code' => 'OPS',
        'name' => 'Operations Bay',
    ]);

    $warehouseReceipt = new WarehouseReceipt([
        'document_number' => 'WR-2026-000002',
        'source_document' => 'PURCHASE_ORDER',
        'source_document_number' => 'PO-2026-000002',
        'status' => WarehouseReceiptStatus::RELEASED,
    ]);
    $warehouseReceipt->setRelation('vendor', null);
    $warehouseReceipt->setRelation('location', $warehouseLocation);

    $warehouseShipment = new WarehouseShipment([
        'document_number' => 'WS-2026-000002',
        'source_document' => 'SALES_ORDER',
        'source_document_number' => 'SO-2026-000002',
        'external_document_number' => 'CUST-PO-2',
        'shipping_agent_code' => 'DHL',
        'status' => 'RELEASED',
    ]);
    $warehouseShipment->setRelation('customer', $customer);
    $warehouseShipment->setRelation('location', $warehouseLocation);

    $plannedProductionOrder = new ProductionOrder([
        'document_number' => 'PROD-2026-000002',
        'source_no' => 'SO-2026-000002',
        'description' => 'Premium Chair Build',
        'quantity' => 25,
        'unit_of_measure_code' => 'PCS',
        'status' => ProductionOrderStatus::PLANNED,
        'location_code' => 'OPS',
    ]);
    $plannedProductionOrder->setRelation('item', $item);
    $plannedProductionOrder->setRelation('location', $warehouseLocation);

    $releasedProductionOrder = new ProductionOrder([
        'document_number' => 'PROD-2026-000003',
        'source_no' => 'SO-2026-000002',
        'description' => 'Premium Chair Release',
        'quantity' => 10,
        'unit_of_measure_code' => 'PCS',
        'status' => ProductionOrderStatus::RELEASED,
        'location_code' => 'OPS',
    ]);
    $releasedProductionOrder->setRelation('item', $item);
    $releasedProductionOrder->setRelation('location', $warehouseLocation);

    $finishedProductionOrder = new ProductionOrder([
        'document_number' => 'PROD-2026-000004',
        'source_no' => 'SO-2026-000002',
        'description' => 'Premium Chair Finish',
        'quantity' => 8,
        'unit_of_measure_code' => 'PCS',
        'status' => ProductionOrderStatus::FINISHED,
        'location_code' => 'OPS',
    ]);
    $finishedProductionOrder->setRelation('item', $item);
    $finishedProductionOrder->setRelation('location', $warehouseLocation);

    $vendor = Vendor::factory()->create([
        'vendor_name' => 'Supply House',
    ]);
    $vendor->contact?->forceFill([
        'name' => 'Supply House',
        'full_name' => 'Supply House',
        'company_name' => 'Supply House',
    ])->saveQuietly();
    $vendor->refresh();

    $purchaseLocation = Location::factory()->create([
        'code' => 'PURCH',
        'name' => 'Purchasing Store',
    ]);

    $purchaseOrder = PurchaseOrder::query()->create([
        'order_number' => 'PO-2026-000002',
        'order_type' => PurchaseOrderType::PURCHASE_ORDER,
        'status' => PurchaseOrderStatus::APPROVED,
        'vendor_id' => $vendor->id,
        'vendor_name' => $vendor->vendor_name,
        'order_date' => now()->toDateString(),
        'location_id' => $purchaseLocation->id,
        'posting_date' => now()->toDateString(),
        'due_date' => now()->addDays(5)->toDateString(),
        'delivery_date' => now()->addDays(5)->toDateString(),
        'currency_code' => 'NGN',
        'grand_total' => 300000,
        'payment_terms' => 'NET30',
        'created_by' => $user->id,
    ]);

    $payment = Payment::factory()->make([
        'payment_number' => 'PAY-2026-000002',
        'external_reference' => 'BANK-REF-02',
        'party_name' => 'Supply House',
        'payment_direction' => 'DISBURSEMENT',
        'status' => 'POSTED',
        'payment_amount' => 85000,
        'currency_code' => 'NGN',
    ]);

    $purchaseInvoice = new PurchaseInvoice([
        'document_number' => 'PI-2026-000002',
        'external_document_number' => 'VEN-INV-22',
        'order_number' => 'PO-2026-000002',
        'vendor_name' => 'Supply House',
        'status' => ApprovalStatus::POSTED,
        'grand_total' => 300000,
        'currency_code' => 'NGN',
    ]);
    $purchaseInvoice->setRelation('vendor', $vendor);

    $postedPurchaseCreditMemo = new PostedPurchaseCreditMemo([
        'document_number' => 'PCM-2026-000002',
        'external_document_number' => 'PCM-EXT-02',
        'vendor_invoice_number' => 'VEN-CM-02',
        'vendor_name' => 'Supply House',
        'corrects_invoice_number' => 'PI-2026-000002',
        'posted' => true,
        'grand_total' => 55000,
        'currency_code' => 'NGN',
    ]);
    $postedPurchaseCreditMemo->setRelation('vendor', $vendor);

    $purchaseReceipt = new PurchaseReceipt([
        'document_number' => 'PR-2026-000002',
        'external_document_no' => 'EXT-REC-02',
        'purchase_order_no' => 'PO-2026-000002',
        'vendor_shipment_no' => 'SHIP-02',
        'buy_from_vendor_name' => 'Supply House',
        'status' => 'POSTED',
        'location_code' => 'PURCH',
    ]);
    $purchaseReceipt->setRelation('receivingLocation', $purchaseLocation);

    $expenseTransaction = new ExpenseTransaction([
        'document_no' => 'EXPT-00002',
        'description' => 'Vehicle maintenance',
        'invoice_no' => 'VEN-INV-22',
        'purchase_order_no' => 'PO-2026-000002',
        'status' => 'posted',
        'amount' => 45000,
        'currency_code' => 'NGN',
    ]);
    $expenseTransaction->setRelation('vendor', $vendor);

    $department = Department::query()->create([
        'department_code' => 'HR',
        'name' => 'Human Resources',
        'search_name' => 'Human Resources',
        'type' => DepartmentType::HR,
        'status' => DepartmentStatus::ACTIVE,
    ]);

    $employee = Employee::factory()->create([
        'employee_number' => 'EMP-1000',
        'first_name' => 'Ada',
        'last_name' => 'Lovace',
        'email' => 'ada@example.com',
        'job_title' => 'HR Manager',
        'assignment_type' => EmployeeAssignmentType::Corporate,
        'department_id' => $department->id,
        'department_code' => $department->department_code,
    ]);

    $department->forceFill([
        'manager_id' => $employee->id,
        'location_code' => 'HQ',
    ])->saveQuietly();
    $department->refresh();
    $department->setRelation(
        'manager',
        $employee->forceFill(['full_name' => 'Ada Lovace'])
    );

    $user->forceFill([
        'employee_id' => $employee->id,
    ])->saveQuietly();

    $payrollDocument = new PayrollDocument([
        'document_number' => 'PAY-2026-000002',
        'period_start' => now()->startOfMonth(),
        'period_end' => now()->endOfMonth(),
        'status' => PayrollStatus::POSTED,
        'remarks' => 'Monthly payroll',
        'total_net_pay' => 525000,
    ]);

    $capExProject = new CapExProject([
        'project_number' => 'CAPEX-2026-000002',
        'description' => 'New Mixer Installation',
        'status' => 'IN_PROGRESS',
        'budget_amount' => 2500000,
    ]);
    $capExProject->setRelation('projectManager', $user);

    $fixedAssetClass = FAClass::query()->create([
        'code' => 'MACH',
        'name' => 'Machinery',
        'fa_type' => FixedAssetType::TANGIBLE,
        'default_posting_group_id' => null,
        'is_active' => true,
    ]);

    $fixedAsset = new FixedAsset([
        'fa_no' => 'FA-2026-000002',
        'description' => 'Industrial Mixer',
        'search_description' => 'Industrial Mixer',
        'serial_no' => 'SER-FA-0002',
        'book_value' => 850000,
        'accumulated_depreciation' => 125000,
        'status' => FAStatus::ACTIVE,
    ]);
    $fixedAsset->setRelation('faClass', $fixedAssetClass);
    $fixedAsset->setRelation('vendor', $vendor);

    expect(ItemResource::getGlobalSearchResultTitle($item))->toBe('FG-1000 - Premium Chair')
        ->and(ItemResource::getGloballySearchableAttributes())->toContain('item_code', 'sku', 'primaryCategory.category_name')
        ->and(CustomerResource::getGlobalSearchResultTitle($customer))->toBe('CUST-1000 - Acme Retail')
        ->and(CustomerResource::getGloballySearchableAttributes())->toContain('customer_number', 'group.code')
        ->and(VendorResource::getGlobalSearchResultTitle($vendor))->toBe($vendor->vendor_code.' - Supply House')
        ->and(VendorResource::getGloballySearchableAttributes())->toContain('vendor_code', 'vendorPostingGroup.code')
        ->and(SalesInvoiceResource::getGlobalSearchResultDetails($salesInvoice))->toMatchArray([
            'Customer' => 'CUST-1000 - Acme Retail',
            'Sales Order' => 'SO-2026-000002',
            'Status' => 'posted',
            'Total' => '250,000.00 NGN',
        ])
        ->and(SalesInvoiceResource::getGloballySearchableAttributes())->toContain('salesOrder.order_number')
        ->and(SalesOrderResource::getGlobalSearchResultTitle($salesOrder))->toBe('SO-2026-000002 - Acme Retail')
        ->and(SalesOrderResource::getGlobalSearchResultDetails($salesOrder))->toMatchArray([
            'Customer' => 'CUST-1000 - Acme Retail',
            'External Doc' => '—',
            'Status' => 'DRAFT',
            'Total' => '0.00 NGN',
        ])
        ->and(SalesOrderResource::getGloballySearchableAttributes())->toContain('external_document_number', 'ship_to_name')
        ->and(WarehouseReceiptResource::getGlobalSearchResultTitle($warehouseReceipt))->toBe('WR-2026-000002')
        ->and(WarehouseReceiptResource::getGlobalSearchResultDetails($warehouseReceipt))->toMatchArray([
            'Vendor' => '—',
            'Source Doc' => 'PO-2026-000002',
            'Location' => 'Operations Bay',
            'Status' => 'RELEASED',
        ])
        ->and(WarehouseReceiptResource::getGloballySearchableAttributes())->toContain('source_document_number', 'vendor.vendor_code')
        ->and(WarehouseShipmentResource::getGlobalSearchResultTitle($warehouseShipment))->toBe('WS-2026-000002')
        ->and(WarehouseShipmentResource::getGlobalSearchResultDetails($warehouseShipment))->toMatchArray([
            'Customer' => 'Acme Retail',
            'Source Doc' => 'SO-2026-000002',
            'Location' => 'Operations Bay',
            'Status' => 'RELEASED',
        ])
        ->and(WarehouseShipmentResource::getGloballySearchableAttributes())->toContain('external_document_number', 'customer.customer_number')
        ->and(PaymentResource::getGlobalSearchResultTitle($payment))->toBe('PAY-2026-000002')
        ->and(PaymentResource::getGlobalSearchResultDetails($payment))->toMatchArray([
            'Counterparty' => 'Supply House',
            'Direction' => 'DISBURSEMENT',
            'Status' => 'POSTED',
            'Amount' => '85,000.00 NGN',
        ])
        ->and(PaymentResource::getGloballySearchableAttributes())->toContain('external_reference', 'bankAccount.account_name')
        ->and(PurchaseOrderResource::getGlobalSearchResultTitle($purchaseOrder))->toBe('PO-2026-000002 - Supply House')
        ->and(PurchaseOrderResource::getGlobalSearchResultDetails($purchaseOrder))->toMatchArray([
            'Vendor' => 'Supply House',
            'Status' => 'APPROVED',
            'Order Type' => 'purchase_order',
            'Total' => "NGN\u{00A0}300,000.00",
        ])
        ->and(PurchaseInvoiceResource::getGlobalSearchResultTitle($purchaseInvoice))->toBe('PI-2026-000002 - Supply House')
        ->and(PurchaseInvoiceResource::getGlobalSearchResultDetails($purchaseInvoice))->toMatchArray([
            'Vendor' => $vendor->vendor_code.' - Supply House',
            'Purchase Order' => 'PO-2026-000002',
            'Status' => 'posted',
            'Total' => "NGN\u{00A0}300,000.00",
        ])
        ->and(PurchaseInvoiceResource::getGloballySearchableAttributes())->toContain('order_number', 'vendor.vendor_code')
        ->and(PostedPurchaseCreditMemoResource::getGlobalSearchResultTitle($postedPurchaseCreditMemo))->toBe('PCM-2026-000002 - Supply House')
        ->and(PostedPurchaseCreditMemoResource::getGlobalSearchResultDetails($postedPurchaseCreditMemo))->toMatchArray([
            'Vendor' => 'Supply House',
            'Corrects Invoice' => 'PI-2026-000002',
            'Posted' => 'Yes',
            'Total' => "NGN\u{00A0}55,000.00",
        ])
        ->and(PurchaseReceiptResource::getGlobalSearchResultTitle($purchaseReceipt))->toBe('PR-2026-000002 - Supply House')
        ->and(PurchaseReceiptResource::getGlobalSearchResultDetails($purchaseReceipt))->toMatchArray([
            'Vendor' => 'Supply House',
            'Purchase Order' => 'PO-2026-000002',
            'Status' => 'Open',
            'Location' => 'PURCH - Purchasing Store',
        ])
        ->and(ExpenseTransactionResource::getGlobalSearchResultTitle($expenseTransaction))->toBe('EXPT-00002')
        ->and(ExpenseTransactionResource::getGlobalSearchResultDetails($expenseTransaction))->toMatchArray([
            'Description' => 'Vehicle maintenance',
            'Vendor' => 'Supply House',
            'Status' => 'posted',
            'Amount' => '45,000.00 NGN',
        ])
        ->and(ExpenseTransactionResource::getGloballySearchableAttributes())->toContain('purchase_order_no', 'expenseCategory.description')
        ->and(ProductionOrderResource::getGlobalSearchResultTitle($plannedProductionOrder))->toBe('PROD-2026-000002')
        ->and(ProductionOrderResource::getGlobalSearchResultDetails($plannedProductionOrder))->toMatchArray([
            'Item' => 'Premium Chair',
            'Source No.' => 'SO-2026-000002',
            'Status' => 'PLANNED',
            'Quantity' => '25.00 PCS',
        ])
        ->and(ProductionOrderResource::getGloballySearchableAttributes())->toContain('item.item_code', 'location.name')
        ->and(ReleasedProductionOrderResource::getGlobalSearchResultDetails($releasedProductionOrder))->toMatchArray([
            'Item' => 'Premium Chair',
            'Source No.' => 'SO-2026-000002',
            'Status' => 'RELEASED',
            'Quantity' => '10.00 PCS',
        ])
        ->and(FinishedProductionOrderResource::getGlobalSearchResultDetails($finishedProductionOrder))->toMatchArray([
            'Item' => 'Premium Chair',
            'Source No.' => 'SO-2026-000002',
            'Status' => 'FINISHED',
            'Quantity' => '8.00 PCS',
        ])
        ->and(PayrollDocumentResource::getGlobalSearchResultTitle($payrollDocument))->toBe('PAY-2026-000002')
        ->and(PayrollDocumentResource::getGlobalSearchResultDetails($payrollDocument))->toMatchArray([
            'Status' => 'POSTED',
            'Net Pay' => '525,000.00',
        ])
        ->and(PayrollDocumentResource::getGloballySearchableAttributes())->toContain('document_number', 'remarks')
        ->and(CapExProjectResource::getGlobalSearchResultTitle($capExProject))->toBe('CAPEX-2026-000002 - New Mixer Installation')
        ->and(CapExProjectResource::getGlobalSearchResultDetails($capExProject))->toMatchArray([
            'Status' => 'IN_PROGRESS',
            'Project Manager' => 'Search Admin',
            'Budget' => '2,500,000.00',
        ])
        ->and(CapExProjectResource::getGloballySearchableAttributes())->toContain('project_number', 'projectManager.name')
        ->and(FixedAssetResource::getGlobalSearchResultTitle($fixedAsset))->toBe('FA-2026-000002 - Industrial Mixer')
        ->and(FixedAssetResource::getGlobalSearchResultDetails($fixedAsset))->toMatchArray([
            'Class' => 'Machinery',
            'Status' => 'active',
            'Serial No.' => 'SER-FA-0002',
            'Net Book Value' => '725,000.00',
        ])
        ->and(FixedAssetResource::getGloballySearchableAttributes())->toContain('fa_no', 'vendor.vendor_name', 'faClass.name')
        ->and(DepartmentResource::getGlobalSearchResultTitle($department))->toBe('HR - Human Resources')
        ->and(DepartmentResource::getGlobalSearchResultDetails($department))->toMatchArray([
            'Type' => 'human_resources',
            'Status' => 'active',
            'Manager' => 'EMP-1000 - Ada Lovace',
            'Location' => 'HQ',
        ])
        ->and(DepartmentResource::getGloballySearchableAttributes())->toContain('department_code', 'manager.first_name')
        ->and(UserResource::getGlobalSearchResultDetails($user))->toMatchArray([
            'Email' => 'search.admin@example.com',
            'Employee' => 'EMP-1000',
            'Roles' => 'super_admin',
        ])
        ->and(UserResource::getGloballySearchableAttributes())->toContain('employee.full_name', 'roles.name')
        ->and(EmployeeResource::getGlobalSearchResultTitle($employee))->toBe('EMP-1000 - Ada Lovace')
        ->and(EmployeeResource::getGloballySearchableAttributes())->toContain('department.name', 'job_title');
});
