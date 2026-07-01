<?php

use App\Enums\AccountCategory;
use App\Enums\BankAccountLedgerEntryStatus;
use App\Enums\BankAccountLedgerEntryType;
use App\Enums\IncomeBalanceType;
use App\Enums\ItemType;
use App\Enums\SalesOrderStatus;
use App\Enums\SourceType;
use App\Models\BankAccount;
use App\Models\BankAccountLedgerEntry;
use App\Models\ChartOfAccount;
use App\Models\Customer;
use App\Models\CustomerLedgerEntry;
use App\Models\CustomerPostingGroup;
use App\Models\GeneralBusinessPostingGroup;
use App\Models\GeneralPostingSetup;
use App\Models\GeneralProductPostingGroup;
use App\Models\GlEntry;
use App\Models\InventoryPostingGroup;
use App\Models\InventoryPostingSetup;
use App\Models\Item;
use App\Models\ItemLedgerEntry;
use App\Models\Location;
use App\Models\NumberSeries;
use App\Models\NumberSeriesLine;
use App\Models\Payment;
use App\Models\Permission;
use App\Models\SalesOrder;
use App\Models\SalesOrderLine;
use App\Models\UnitOfMeasure;
use App\Models\User;
use App\Models\ValueEntry;
use App\Models\Vendor;
use App\Models\VendorLedgerEntry;
use App\Models\VendorPostingGroup;
use App\Services\Finance\BalanceSheetService;
use App\Services\Finance\GeneralLedgerService;
use App\Services\Finance\PaymentService;
use App\Services\IncomeStatementService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;

uses(RefreshDatabase::class);

it('creates a balanced trial balance from general ledger entries only', function (): void {
    $cash = financeAccount('11000', 'Cash', AccountCategory::LIQUID_ASSET);
    $equity = financeAccount('30000', 'Capital', AccountCategory::EQUITY);

    app(GeneralLedgerService::class)->post([
        ['account_id' => $cash->id, 'debit' => 1000, 'credit' => 0],
        ['account_id' => $equity->id, 'debit' => 0, 'credit' => 1000],
    ], [
        'posting_date' => '2026-01-15',
        'document_number' => 'OPEN-001',
        'document_date' => '2026-01-15',
        'document_type' => 'OPENING',
        'description' => 'Opening balance',
    ]);

    $trialBalance = app(GeneralLedgerService::class)->trialBalance(
        now()->parse('2026-01-01'),
        now()->parse('2026-01-31'),
    );

    expect($trialBalance['is_balanced'])->toBeTrue()
        ->and($trialBalance['totals']['debit'])->toBe(1000.0)
        ->and($trialBalance['totals']['credit'])->toBe(1000.0)
        ->and($trialBalance['accounts'])->toHaveCount(2);
});

it('reports sales invoice revenue receivables cogs and inventory from general ledger entries', function (): void {
    $receivables = financeAccount('11100', 'Receivables', AccountCategory::RECEIVABLE);
    $inventory = financeAccount('13000', 'Inventory', AccountCategory::INVENTORY);
    $revenue = financeAccount('41000', 'Sales Revenue', AccountCategory::REVENUE);
    $cogs = financeAccount('51000', 'Cost of Goods Sold', AccountCategory::COGS);

    postGl('SI-GL-001', 'SALES_INVOICE', '2026-02-10', [
        [$receivables, 1000, 0, 'Customer receivable'],
        [$revenue, 0, 1000, 'Sales revenue'],
        [$cogs, 400, 0, 'COGS'],
        [$inventory, 0, 400, 'Inventory relief'],
    ]);

    $trialBalance = app(GeneralLedgerService::class)->trialBalance(
        now()->parse('2026-02-01'),
        now()->parse('2026-02-28'),
    );
    $incomeStatement = app(IncomeStatementService::class)
        ->generate(now()->parse('2026-02-01'), now()->parse('2026-02-28'))
        ->toArray();
    $balanceSheet = app(BalanceSheetService::class)->generate(now()->parse('2026-02-28'));

    expect($trialBalance['is_balanced'])->toBeTrue()
        ->and(financeTrialRow($trialBalance, '11100')['debit'])->toBe(1000.0)
        ->and(financeTrialRow($trialBalance, '41000')['credit'])->toBe(1000.0)
        ->and(financeTrialRow($trialBalance, '51000')['debit'])->toBe(400.0)
        ->and(financeTrialRow($trialBalance, '13000')['credit'])->toBe(400.0)
        ->and(round((float) $incomeStatement['summary']['total_revenue'], 2))->toBe(1000.0)
        ->and(round((float) $incomeStatement['summary']['total_cogs'], 2))->toBe(400.0)
        ->and(round((float) $incomeStatement['summary']['gross_profit'], 2))->toBe(600.0)
        ->and(round((float) $balanceSheet['totals']['assets'], 2))->toBe(600.0);
});

it('reports purchase invoice inventory expense and payables from general ledger entries', function (): void {
    $inventory = financeAccount('13010', 'Raw Material Inventory', AccountCategory::INVENTORY);
    $expense = financeAccount('61000', 'Purchase Expense', AccountCategory::OPERATING_EXPENSE);
    $payables = financeAccount('21100', 'Trade Payables', AccountCategory::PAYABLE);

    postGl('PI-GL-001', 'PURCHASE_INVOICE', '2026-03-05', [
        [$inventory, 700, 0, 'Inventory receipt'],
        [$expense, 30, 0, 'Freight expense'],
        [$payables, 0, 730, 'Vendor payable'],
    ]);

    $trialBalance = app(GeneralLedgerService::class)->trialBalance(
        now()->parse('2026-03-01'),
        now()->parse('2026-03-31'),
    );

    expect($trialBalance['is_balanced'])->toBeTrue()
        ->and(financeTrialRow($trialBalance, '13010')['debit'])->toBe(700.0)
        ->and(financeTrialRow($trialBalance, '61000')['debit'])->toBe(30.0)
        ->and(financeTrialRow($trialBalance, '21100')['credit'])->toBe(730.0);
});

it('reports customer and vendor payments through bank receivable and payable general ledger accounts', function (): void {
    $bank = financeAccount('10100', 'Operating Bank', AccountCategory::LIQUID_ASSET);
    $receivables = financeAccount('11110', 'Customer Receivables', AccountCategory::RECEIVABLE);
    $payables = financeAccount('21110', 'Vendor Payables', AccountCategory::PAYABLE);

    postGl('PAY-CUST-001', 'PAYMENT', '2026-04-10', [
        [$bank, 500, 0, 'Customer receipt'],
        [$receivables, 0, 500, 'Clear receivable'],
    ]);

    postGl('PAY-VEND-001', 'PAYMENT', '2026-04-11', [
        [$payables, 300, 0, 'Clear payable'],
        [$bank, 0, 300, 'Vendor disbursement'],
    ]);

    $generalLedger = app(GeneralLedgerService::class)->generalLedgerReport(
        now()->parse('2026-04-01'),
        now()->parse('2026-04-30'),
    );

    expect(financeLedgerSection($generalLedger, '10100')['closing_balance'])->toBe(200.0)
        ->and(financeLedgerSection($generalLedger, '11110')['closing_balance'])->toBe(-500.0)
        ->and(financeLedgerSection($generalLedger, '21110')['closing_balance'])->toBe(300.0)
        ->and(financeLedgerSection($generalLedger, '10100')['entries'][0]['document_number'])->toBe('PAY-CUST-001')
        ->and(financeLedgerSection($generalLedger, '10100')['entries'][0]['description'])->toBe('Customer receipt');
});

it('applies trial balance date account and dimension filters', function (): void {
    $cash = financeAccount('10200', 'Dimension Bank', AccountCategory::LIQUID_ASSET);
    $revenue = financeAccount('42000', 'Dimension Revenue', AccountCategory::REVENUE);

    postGl('DIM-OLD-001', 'SALES_INVOICE', '2025-12-31', [
        [$cash, 200, 0, 'Old receipt'],
        [$revenue, 0, 200, 'Old sale'],
    ], 'OLD');

    postGl('DIM-NEW-001', 'SALES_INVOICE', '2026-05-01', [
        [$cash, 300, 0, 'New receipt'],
        [$revenue, 0, 300, 'New sale'],
    ], 'SALES');

    $trialBalance = app(GeneralLedgerService::class)->trialBalance(
        now()->parse('2026-05-01'),
        now()->parse('2026-05-31'),
        [
            'account_id' => $cash->id,
            'shortcut_dimension_1_code' => 'SALES',
            'dimensions' => ['business' => 'NORTH'],
        ],
    );

    expect($trialBalance['accounts'])->toHaveCount(1)
        ->and($trialBalance['totals']['debit'])->toBe(300.0)
        ->and($trialBalance['totals']['credit'])->toBe(0.0)
        ->and($trialBalance['is_balanced'])->toBeFalse();
});

it('reports finance subledger to general ledger reconciliation mismatches', function (): void {
    $receivables = financeAccount('11200', 'Mismatch Receivables', AccountCategory::RECEIVABLE);
    $payables = financeAccount('21200', 'Mismatch Payables', AccountCategory::PAYABLE);
    $bankGl = financeAccount('10300', 'Mismatch Bank', AccountCategory::LIQUID_ASSET);
    $inventoryGl = financeAccount('13100', 'Mismatch Inventory', AccountCategory::INVENTORY);
    $offset = financeAccount('39999', 'Offset', AccountCategory::EQUITY);
    $user = User::factory()->create();

    $customerPostingGroup = CustomerPostingGroup::factory()->create(['receivables_account_id' => $receivables->id]);
    $vendorPostingGroup = VendorPostingGroup::factory()->create(['payables_account_id' => $payables->id]);
    $customer = Customer::factory()->create(['customer_posting_group_id' => $customerPostingGroup->id]);
    $vendor = Vendor::factory()->create(['vendor_posting_group_id' => $vendorPostingGroup->id]);
    $bankAccount = BankAccount::factory()->create(['gl_account_id' => $bankGl->id]);
    $inventoryPostingGroup = InventoryPostingGroup::query()->firstOrCreate(['code' => 'FIN-INV'], ['description' => 'Finance Inventory']);
    InventoryPostingSetup::query()->create([
        'inventory_posting_group_id' => $inventoryPostingGroup->id,
        'inventory_account_id' => $inventoryGl->id,
    ]);

    CustomerLedgerEntry::query()->create([
        'entry_number' => 9001,
        'customer_id' => $customer->id,
        'customer_posting_group_id' => $customerPostingGroup->id,
        'document_type' => 'SALES_INVOICE',
        'document_number' => 'AR-MISMATCH',
        'description' => 'Receivables subledger mismatch',
        'posting_date' => '2026-06-01',
        'document_date' => '2026-06-01',
        'debit_amount' => 100,
        'credit_amount' => 0,
        'amount' => 100,
        'remaining_amount' => 100,
        'open' => true,
        'fully_applied' => false,
        'reversed' => false,
        'created_by' => $user->id,
    ]);

    VendorLedgerEntry::query()->create([
        'entry_number' => 9002,
        'vendor_id' => $vendor->id,
        'vendor_posting_group_id' => $vendorPostingGroup->id,
        'document_type' => 'PURCHASE_INVOICE',
        'document_number' => 'AP-MISMATCH',
        'description' => 'Payables subledger mismatch',
        'posting_date' => '2026-06-01',
        'document_date' => '2026-06-01',
        'debit_amount' => 0,
        'credit_amount' => 90,
        'amount' => -90,
        'remaining_amount' => 90,
        'open' => true,
        'fully_applied' => false,
        'reversed' => false,
        'created_by' => $user->id,
    ]);

    BankAccountLedgerEntry::query()->create([
        'entry_number' => 9003,
        'bank_account_id' => $bankAccount->id,
        'bank_account_no' => $bankAccount->account_code,
        'posting_date' => '2026-06-01',
        'document_type' => 'PAYMENT',
        'document_no' => 'BANK-MISMATCH',
        'description' => 'Bank subledger mismatch',
        'entry_type' => BankAccountLedgerEntryType::DEPOSIT,
        'amount' => 75,
        'balance' => 75,
        'balance_lcy' => 75,
        'status' => BankAccountLedgerEntryStatus::OPEN,
        'open' => true,
        'user_id' => $user->id,
    ]);

    ValueEntry::query()->create([
        'item_ledger_entry_no' => 9004,
        'item_ledger_entry_type' => 0,
        'item_no' => 'FIN-ITEM',
        'location_code' => 'MAIN',
        'posting_date' => '2026-06-01',
        'document_type' => 'PURCHASE',
        'document_no' => 'INV-MISMATCH',
        'description' => 'Inventory value mismatch',
        'cost_amount_actual' => 120,
        'cost_amount_expected' => 0,
        'quantity' => 1,
        'invoiced_quantity' => 1,
        'entry_type' => 'Direct Cost',
    ]);

    postGl('AR-MISMATCH', 'SALES_INVOICE', '2026-06-01', [
        [$receivables, 80, 0, 'Receivables G/L mismatch'],
        [$offset, 0, 80, 'Offset'],
    ]);
    postGl('AP-MISMATCH', 'PURCHASE_INVOICE', '2026-06-01', [
        [$offset, 70, 0, 'Offset'],
        [$payables, 0, 70, 'Payables G/L mismatch'],
    ]);
    postGl('BANK-MISMATCH', 'PAYMENT', '2026-06-01', [
        [$bankGl, 50, 0, 'Bank G/L mismatch'],
        [$offset, 0, 50, 'Offset'],
    ]);
    postGl('INV-MISMATCH', 'PURCHASE', '2026-06-01', [
        [$inventoryGl, 110, 0, 'Inventory G/L mismatch'],
        [$offset, 0, 110, 'Offset'],
    ]);

    expect(Artisan::call('biwms:finance-reconcile', ['--json' => true]))->toBe(0);

    $report = json_decode(trim(Artisan::output()), true);

    expect($report['customer_ledger_receivables_mismatches'][0]['classification'])->toBe('customer_ledger_gl_mismatch')
        ->and($report['vendor_ledger_payables_mismatches'][0]['classification'])->toBe('vendor_ledger_gl_mismatch')
        ->and($report['bank_ledger_gl_mismatches'][0]['classification'])->toBe('bank_ledger_gl_mismatch')
        ->and($report['inventory_value_gl_mismatches'][0]['classification'])->toBe('inventory_value_gl_mismatch')
        ->and($report['customer_ledger_receivables_mismatches'][0]['severity'])->toBe('critical')
        ->and($report['customer_ledger_receivables_mismatches'][0]['suggested_remediation'])->not->toBeEmpty();
});

it('exports finance reconcile diagnostics with severity and remediation', function (): void {
    $bankGl = financeAccount('10400', 'Export Bank', AccountCategory::LIQUID_ASSET);
    $offset = financeAccount('39998', 'Export Offset', AccountCategory::EQUITY);
    BankAccount::factory()->create(['gl_account_id' => $bankGl->id]);

    postGl('BANK-EXPORT-MISSING', 'PAYMENT', '2026-06-15', [
        [$bankGl, 45, 0, 'Bank control without bank ledger'],
        [$offset, 0, 45, 'Offset'],
    ]);

    $exportPath = 'storage/app/reports/finance-reconcile-test.json';
    File::delete(base_path($exportPath));

    expect(Artisan::call('biwms:finance-reconcile', [
        '--details' => true,
        '--export' => $exportPath,
    ]))->toBe(0);

    $report = json_decode(File::get(base_path($exportPath)), true);

    expect($report['missing_control_account_entries'])->not->toBeEmpty()
        ->and($report['missing_control_account_entries'][0]['classification'])->toBe('missing_control_account_entry')
        ->and($report['missing_control_account_entries'][0]['severity'])->toBe('critical')
        ->and($report['missing_control_account_entries'][0]['suggested_remediation'])->not->toBeEmpty();
});

it('keeps posted bank payment general ledger in agreement with bank ledger', function (): void {
    financeEnsureBankLedgerNumberSeries();

    $user = User::factory()->create();
    financeGrantPaymentPostingPermission($user);

    $bankGl = financeAccount('10500', 'Payment Bank', AccountCategory::LIQUID_ASSET);
    $receivables = financeAccount('11210', 'Payment Receivables', AccountCategory::RECEIVABLE);
    $customerPostingGroup = CustomerPostingGroup::factory()->create(['receivables_account_id' => $receivables->id]);
    $customer = Customer::factory()->create(['customer_posting_group_id' => $customerPostingGroup->id]);
    $bankAccount = BankAccount::factory()->receiptOnly()->create([
        'gl_account_id' => $bankGl->id,
        'current_balance' => 0,
        'available_balance' => 0,
    ]);

    $payment = Payment::factory()->customerReceipt()->create([
        'party_id' => $customer->id,
        'party_name' => $customer->name,
        'bank_account_id' => $bankAccount->id,
        'payment_amount' => 325,
        'payment_amount_lcy' => 325,
        'applied_amount' => 0,
        'unapplied_amount' => 325,
        'status' => 'APPROVED',
        'created_by' => $user->id,
    ]);

    app(PaymentService::class)->post($payment, $user->id);

    expect(Artisan::call('biwms:finance-reconcile', ['--json' => true]))->toBe(0);

    $report = json_decode(trim(Artisan::output()), true);

    expect($report['bank_ledger_gl_mismatches'])->toBeEmpty()
        ->and($report['missing_control_account_entries'])->toBeEmpty();
});

it('keeps inventory value entries in agreement with inventory general ledger after purchase and sale value movement', function (): void {
    $inventory = financeAccount('13200', 'Reconcile Inventory', AccountCategory::INVENTORY);
    $offset = financeAccount('39997', 'Inventory Offset', AccountCategory::EQUITY);
    $inventoryPostingGroup = InventoryPostingGroup::query()->firstOrCreate(['code' => 'FIN-RECON'], ['description' => 'Finance Reconcile Inventory']);
    InventoryPostingSetup::query()->create([
        'inventory_posting_group_id' => $inventoryPostingGroup->id,
        'inventory_account_id' => $inventory->id,
    ]);

    financeValueEntry('PURCHASE_INVOICE', 'PI-VALUE-OK', 100);
    financeValueEntry('SALES_INVOICE', 'SI-VALUE-OK', -40);

    postGl('PI-VALUE-OK', 'PURCHASE_INVOICE', '2026-06-20', [
        [$inventory, 100, 0, 'Inventory purchase value'],
        [$offset, 0, 100, 'Offset'],
    ]);
    postGl('SI-VALUE-OK', 'SALES_INVOICE', '2026-06-21', [
        [$offset, 40, 0, 'COGS offset'],
        [$inventory, 0, 40, 'Inventory sale value'],
    ]);

    expect(Artisan::call('biwms:finance-reconcile', ['--json' => true]))->toBe(0);

    $report = json_decode(trim(Artisan::output()), true);

    expect($report['inventory_value_gl_mismatches'])->toBeEmpty()
        ->and($report['missing_control_account_entries'])->toBeEmpty();
});

it('keeps sales order ship and invoice inventory value movement in agreement with general ledger', function (): void {
    $user = User::factory()->create();
    $this->actingAs($user);

    $receivablesAccount = financeAccount('11300', 'Sales Order Receivables', AccountCategory::RECEIVABLE);
    $inventoryAccount = financeAccount('12300', 'Sales Order Inventory', AccountCategory::INVENTORY);
    $revenueAccount = financeAccount('41300', 'Sales Order Revenue', AccountCategory::REVENUE);
    $cogsAccount = financeAccount('51300', 'Sales Order COGS', AccountCategory::COGS);

    $businessGroup = GeneralBusinessPostingGroup::query()->create([
        'code' => 'SO-DOM',
        'description' => 'Sales Order Domestic',
        'blocked' => false,
    ]);
    $productGroup = GeneralProductPostingGroup::query()->create([
        'code' => 'SO-FG',
        'description' => 'Sales Order Finished Goods',
        'blocked' => false,
    ]);
    $inventoryGroup = InventoryPostingGroup::query()->create([
        'code' => 'SO-FG',
        'description' => 'Sales Order Finished Goods',
        'blocked' => false,
    ]);
    $customerPostingGroup = CustomerPostingGroup::query()->create([
        'code' => 'SO-DOM',
        'description' => 'Sales Order Customers',
        'receivables_account_id' => $receivablesAccount->id,
        'blocked' => false,
    ]);
    $location = Location::factory()->create();

    InventoryPostingSetup::query()->create([
        'inventory_posting_group_id' => $inventoryGroup->id,
        'location_id' => $location->id,
        'inventory_account_id' => $inventoryAccount->id,
    ]);

    GeneralPostingSetup::query()->create([
        'general_business_posting_group_id' => $businessGroup->id,
        'general_product_posting_group_id' => $productGroup->id,
        'sales_account_id' => $revenueAccount->id,
        'cogs_account_id' => $cogsAccount->id,
        'blocked' => false,
    ]);

    $baseUom = UnitOfMeasure::query()->create([
        'uom_code' => 'PCS',
        'description' => 'Pieces',
        'is_base_uom' => true,
    ]);

    $item = Item::query()->create([
        'item_code' => 'SO-FG-001',
        'description' => 'Sales Order Finished Good',
        'item_type' => ItemType::FINISHED_GOOD,
        'base_uom_id' => $baseUom->id,
        'unit_price' => 100,
        'unit_cost' => 10,
        'inventory' => 5,
        'general_product_posting_group_id' => $productGroup->id,
        'inventory_posting_group_id' => $inventoryGroup->id,
        'location_id' => $location->id,
    ]);

    $customer = Customer::factory()->create([
        'general_business_posting_group_id' => $businessGroup->id,
        'customer_posting_group_id' => $customerPostingGroup->id,
        'vat_bus_posting_group' => null,
        'location_id' => $location->id,
    ]);

    financeEnsureSalesInvoiceNumberSeries();

    $order = SalesOrder::query()->create([
        'order_number' => 'SO-FIN-001',
        'order_type' => 'SALES_ORDER',
        'status' => SalesOrderStatus::APPROVED,
        'customer_id' => $customer->id,
        'customer_name' => $customer->name,
        'customer_address' => $customer->address,
        'ship_to_name' => $customer->name,
        'ship_to_address' => $customer->address,
        'order_date' => '2026-06-25',
        'posting_date' => '2026-06-25',
        'shipment_date' => '2026-06-25',
        'general_business_posting_group_id' => $businessGroup->id,
        'customer_posting_group_id' => $customerPostingGroup->id,
        'location_id' => $location->id,
        'currency_code' => 'NGN',
        'currency_factor' => 1,
        'created_by' => $user->id,
    ]);

    SalesOrderLine::query()->create([
        'sales_order_id' => $order->id,
        'line_number' => 10000,
        'item_id' => $item->id,
        'item_code' => $item->item_code,
        'description' => $item->description,
        'quantity' => 2,
        'unit_of_measure_code' => 'PCS',
        'qty_per_unit_of_measure' => 1,
        'quantity_base' => 2,
        'unit_price' => 100,
        'unit_cost' => 10,
        'location_id' => $location->id,
        'general_product_posting_group_id' => $productGroup->id,
        'inventory_posting_group_id' => $inventoryGroup->id,
    ]);

    $order->postShipment();
    $postedInvoice = $order->fresh()->postInvoice();
    $shipmentDocumentNo = "SS-{$order->order_number}";

    $itemLedgerEntry = ItemLedgerEntry::query()
        ->where('document_type', 'SALES_ORDER_SHIPMENT')
        ->where('document_number', $shipmentDocumentNo)
        ->firstOrFail();

    expect(ValueEntry::query()
        ->where('item_ledger_entry_no', $itemLedgerEntry->entry_number)
        ->where('document_no', $shipmentDocumentNo)
        ->where('cost_amount_actual', 20)
        ->where('gl_posted', true)
        ->exists())->toBeTrue();

    expect(GlEntry::query()
        ->where('document_type', 'SALES_ORDER_SHIPMENT')
        ->where('document_number', $shipmentDocumentNo)
        ->where('chart_of_account_id', $cogsAccount->id)
        ->where('item_ledger_entry_id', $itemLedgerEntry->id)
        ->sum('debit_amount'))->toBe('20.00')
        ->and(GlEntry::query()
            ->where('document_type', 'SALES_ORDER_SHIPMENT')
            ->where('document_number', $shipmentDocumentNo)
            ->where('chart_of_account_id', $inventoryAccount->id)
            ->where('item_ledger_entry_id', $itemLedgerEntry->id)
            ->sum('credit_amount'))->toBe('20.00')
        ->and(GlEntry::query()
            ->where('document_type', 'SALES_INVOICE')
            ->where('document_number', $postedInvoice->document_number)
            ->whereIn('chart_of_account_id', [$cogsAccount->id, $inventoryAccount->id])
            ->exists())->toBeFalse();

    expect(Artisan::call('biwms:finance-reconcile', ['--json' => true]))->toBe(0);
    $report = json_decode(trim(Artisan::output()), true);

    expect($report['inventory_value_gl_mismatches'])->toBeEmpty()
        ->and($report['missing_control_account_entries'])->toBeEmpty();
});

function financeAccount(string $number, string $name, AccountCategory $category): ChartOfAccount
{
    return ChartOfAccount::factory()->create([
        'account_number' => $number,
        'name' => $name,
        'account_category' => $category,
        'account_type' => match ($category) {
            AccountCategory::PAYABLE, AccountCategory::LIABILITY => 'LIABILITY',
            AccountCategory::EQUITY => 'EQUITY',
            AccountCategory::REVENUE => 'REVENUE',
            AccountCategory::COGS => 'COGS',
            AccountCategory::DIRECT_EXPENSE,
            AccountCategory::INDIRECT_EXPENSE,
            AccountCategory::OPERATING_EXPENSE,
            AccountCategory::OTHER_INCOME_EXPENSE => 'EXPENSE',
            default => 'ASSET',
        },
        'income_balance' => $category->isIncomeStatement()
            ? IncomeBalanceType::INCOME_STATEMENT
            : IncomeBalanceType::BALANCE_SHEET,
        'blocked' => false,
    ]);
}

/**
 * @param  array<int, array{0: ChartOfAccount, 1: float|int, 2: float|int, 3: string}>  $lines
 */
function postGl(string $documentNumber, string $documentType, string $postingDate, array $lines, ?string $dimension1 = null): void
{
    $transactionNumber = ((int) GlEntry::query()->max('transaction_number')) + 1;

    foreach ($lines as [$account, $debit, $credit, $description]) {
        GlEntry::query()->create([
            'entry_number' => ((int) GlEntry::query()->max('entry_number')) + 1,
            'transaction_number' => $transactionNumber,
            'chart_of_account_id' => $account->id,
            'debit_amount' => $debit,
            'credit_amount' => $credit,
            'amount' => $debit - $credit,
            'source_type' => SourceType::GENERAL_JOURNAL,
            'source_number' => $documentNumber,
            'document_type' => $documentType,
            'document_number' => $documentNumber,
            'document_date' => $postingDate,
            'posting_date' => $postingDate,
            'description' => $description,
            'shortcut_dimension_1_code' => $dimension1,
            'dimensions' => ['business' => 'NORTH'],
        ]);
    }
}

/**
 * @return array<string, mixed>
 */
function financeTrialRow(array $trialBalance, string $accountNumber): array
{
    return collect($trialBalance['accounts'])->firstWhere('account_number', $accountNumber);
}

/**
 * @return array<string, mixed>
 */
function financeLedgerSection(array $generalLedger, string $accountNumber): array
{
    return collect($generalLedger['accounts'])->firstWhere('account_number', $accountNumber);
}

function financeGrantPaymentPostingPermission(User $user): void
{
    Permission::query()->firstOrCreate([
        'name' => 'finance.payment.post',
        'guard_name' => 'web',
    ]);

    $user->givePermissionTo('finance.payment.post');
}

function financeEnsureBankLedgerNumberSeries(): void
{
    $series = NumberSeries::query()->firstOrCreate(
        ['code' => 'BANK-LEDGER'],
        [
            'description' => 'Bank Ledger Entries',
            'prefix' => '',
            'starting_number' => 1,
            'ending_number' => null,
            'current_number' => 0,
            'year' => 2026,
            'is_active' => true,
            'allow_manual' => false,
            'module' => 'finance',
        ]
    );

    NumberSeriesLine::query()->firstOrCreate(
        ['number_series_id' => $series->id, 'starting_date' => now()->startOfYear()->toDateString()],
        [
            'prefix' => '',
            'suffix' => '',
            'starting_no' => 0,
            'ending_no' => null,
            'increment_by' => 1,
            'last_no_used' => 0,
            'no_of_digits' => 6,
            'blocked' => false,
        ]
    );
}

function financeEnsureSalesInvoiceNumberSeries(): void
{
    $series = NumberSeries::query()->firstOrCreate(
        ['code' => 'S-INV'],
        [
            'description' => 'Sales Invoice',
            'prefix' => 'SI-',
            'starting_number' => 1,
            'ending_number' => null,
            'current_number' => 0,
            'year' => 2026,
            'is_active' => true,
            'allow_manual' => false,
            'module' => 'sales',
        ]
    );

    NumberSeriesLine::query()->firstOrCreate(
        ['number_series_id' => $series->id, 'starting_date' => now()->startOfYear()->toDateString()],
        [
            'prefix' => 'SI-',
            'suffix' => '',
            'starting_no' => 0,
            'ending_no' => null,
            'increment_by' => 1,
            'last_no_used' => 0,
            'no_of_digits' => 6,
            'blocked' => false,
        ]
    );
}

function financeValueEntry(string $documentType, string $documentNumber, float $costAmount): ValueEntry
{
    return ValueEntry::query()->create([
        'item_ledger_entry_no' => ((int) ValueEntry::query()->max('item_ledger_entry_no')) + 1,
        'item_ledger_entry_type' => $costAmount >= 0 ? 1 : 2,
        'item_no' => 'FIN-ITEM',
        'location_code' => 'MAIN',
        'posting_date' => '2026-06-20',
        'document_type' => $documentType,
        'document_no' => $documentNumber,
        'description' => "Finance value {$documentNumber}",
        'cost_amount_actual' => $costAmount,
        'cost_amount_expected' => 0,
        'quantity' => $costAmount >= 0 ? 1 : -1,
        'invoiced_quantity' => $costAmount >= 0 ? 1 : -1,
        'entry_type' => 'Direct Cost',
    ]);
}
