<?php

use App\Enums\AccountCategory;
use App\Enums\BankAccountLedgerEntryStatus;
use App\Enums\BankAccountLedgerEntryType;
use App\Enums\IncomeBalanceType;
use App\Enums\ItemLedgerEntryType;
use App\Enums\ItemType;
use App\Enums\ProductionOrderSourceType;
use App\Enums\ProductionOrderStatus;
use App\Enums\PurchaseLineType;
use App\Enums\SourceType;
use App\Models\BankAccount;
use App\Models\BankAccountLedgerEntry;
use App\Models\ChartOfAccount;
use App\Models\Customer;
use App\Models\CustomerLedgerEntry;
use App\Models\GeneralProductPostingGroup;
use App\Models\GlEntry;
use App\Models\InventoryPostingGroup;
use App\Models\InventoryPostingSetup;
use App\Models\Item;
use App\Models\ItemLedgerEntry;
use App\Models\Location;
use App\Models\Manufacturing\ProductionOrder;
use App\Models\Manufacturing\ProductionOrderComponent;
use App\Models\PostedPurchaseInvoice;
use App\Models\PostedSalesInvoice;
use App\Models\PostedSalesInvoiceLine;
use App\Models\PurchaseReceipt;
use App\Models\PurchaseReceiptLine;
use App\Models\UnitOfMeasure;
use App\Models\User;
use App\Models\ValueEntry;
use App\Models\Vendor;
use App\Models\VendorLedgerEntry;
use App\Services\Dashboard\FinanceDashboardService;
use App\Services\Dashboard\InventoryDashboardService;
use App\Services\Dashboard\ManufacturingDashboardService;
use App\Services\Dashboard\PurchaseDashboardService;
use App\Services\Dashboard\ReconciliationWarningService;
use App\Services\Dashboard\SalesDashboardService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    Cache::flush();
});

it('calculates finance dashboard values from ledgers and g l entries', function (): void {
    $user = User::factory()->create();
    $baseline = app(FinanceDashboardService::class)->summary(Carbon::parse('2026-06-01'), Carbon::parse('2026-06-30'));
    $bankGlAccount = dashboardAccount('10101', 'Dashboard Bank G/L', AccountCategory::LIQUID_ASSET);
    $bankAccount = BankAccount::query()->create([
        'account_code' => 'BANK-DASH',
        'account_name' => 'Dashboard Bank',
        'bank_name' => 'Dashboard Bank',
        'account_number' => 'BANK-DASH-001',
        'gl_account_id' => $bankGlAccount->id,
        'current_balance' => 999999,
        'available_balance' => 999999,
        'active' => true,
        'allow_payments' => true,
        'allow_receipts' => true,
    ]);

    BankAccountLedgerEntry::query()->create([
        'entry_number' => dashboardNextBankLedgerEntryNumber(),
        'bank_account_id' => $bankAccount->id,
        'bank_account_no' => $bankAccount->account_number,
        'posting_date' => '2026-06-10',
        'document_date' => '2026-06-10',
        'document_type' => 'deposit',
        'document_no' => 'BANK-DASH-DEP',
        'description' => 'Dashboard deposit',
        'entry_type' => BankAccountLedgerEntryType::DEPOSIT,
        'amount' => 300,
        'amount_lcy' => 300,
        'debit_amount' => 300,
        'credit_amount' => 0,
        'currency_factor' => 1,
        'balance' => 300,
        'balance_lcy' => 300,
        'status' => BankAccountLedgerEntryStatus::OPEN,
        'open' => true,
        'user_id' => $user->id,
    ]);

    BankAccountLedgerEntry::query()->create([
        'entry_number' => dashboardNextBankLedgerEntryNumber(),
        'bank_account_id' => $bankAccount->id,
        'bank_account_no' => $bankAccount->account_number,
        'posting_date' => '2026-06-11',
        'document_date' => '2026-06-11',
        'document_type' => 'payment',
        'document_no' => 'BANK-DASH-PAY',
        'description' => 'Dashboard payment',
        'entry_type' => BankAccountLedgerEntryType::WITHDRAWAL,
        'amount' => -50,
        'amount_lcy' => -50,
        'debit_amount' => 0,
        'credit_amount' => 50,
        'currency_factor' => 1,
        'balance' => 250,
        'balance_lcy' => 250,
        'status' => BankAccountLedgerEntryStatus::OPEN,
        'open' => true,
        'user_id' => $user->id,
    ]);

    $customer = Customer::factory()->create();
    CustomerLedgerEntry::query()->create([
        'entry_number' => dashboardNextCustomerLedgerEntryNumber(),
        'customer_id' => $customer->id,
        'document_type' => 'SALES_INVOICE',
        'document_number' => 'SI-DASH-001',
        'description' => 'Dashboard invoice',
        'posting_date' => '2026-06-12',
        'document_date' => '2026-06-12',
        'debit_amount' => 150,
        'credit_amount' => 0,
        'amount' => 150,
        'running_balance' => 150,
        'remaining_amount' => 90,
        'open' => true,
        'fully_applied' => false,
        'currency_code' => 'NGN',
        'original_debit_amount' => 150,
        'original_credit_amount' => 0,
        'currency_factor' => 1,
        'general_business_posting_group_id' => $customer->general_business_posting_group_id,
        'customer_posting_group_id' => $customer->customer_posting_group_id,
        'created_by' => $user->id,
    ]);

    $vendor = Vendor::factory()->create();
    VendorLedgerEntry::query()->create([
        'entry_number' => dashboardNextVendorLedgerEntryNumber(),
        'vendor_id' => $vendor->id,
        'document_type' => 'PURCHASE_INVOICE',
        'document_number' => 'PI-DASH-001',
        'description' => 'Dashboard purchase invoice',
        'posting_date' => '2026-06-12',
        'document_date' => '2026-06-12',
        'debit_amount' => 0,
        'credit_amount' => 80,
        'amount' => -80,
        'running_balance' => -80,
        'remaining_amount' => 40,
        'open' => true,
        'fully_applied' => false,
        'currency_code' => 'NGN',
        'original_debit_amount' => 0,
        'original_credit_amount' => 80,
        'currency_factor' => 1,
        'general_business_posting_group_id' => $vendor->general_business_posting_group_id,
        'vendor_posting_group_id' => $vendor->vendor_posting_group_id,
        'created_by' => $user->id,
    ]);

    $revenue = dashboardAccount('41010', 'Dashboard Revenue', AccountCategory::REVENUE);
    $cogs = dashboardAccount('51010', 'Dashboard COGS', AccountCategory::COGS);
    $offset = dashboardAccount('99910', 'Dashboard Offset', AccountCategory::EQUITY);

    dashboardGl('DASH-GL', [
        [$revenue, 0, 500, 'Revenue'],
        [$cogs, 200, 0, 'COGS'],
        [$offset, 300, 0, 'Offset'],
    ]);

    $summary = app(FinanceDashboardService::class)->summary(Carbon::parse('2026-06-01'), Carbon::parse('2026-06-30'));

    expect($summary['cash_bank_balance'])->toBe(round((float) $baseline['cash_bank_balance'] + 250, 2))
        ->and($summary['receivables'])->toBe(round((float) $baseline['receivables'] + 90, 2))
        ->and($summary['payables'])->toBe(round((float) $baseline['payables'] + 40, 2))
        ->and($summary['revenue'])->toBe(round((float) $baseline['revenue'] + 500, 2))
        ->and($summary['cogs'])->toBe(round((float) $baseline['cogs'] + 200, 2))
        ->and($summary['gross_profit'])->toBe(round((float) $baseline['gross_profit'] + 300, 2))
        ->and($summary['trial_balance']['difference'])->toBe($baseline['trial_balance']['difference']);
});

it('calculates inventory dashboard quantity and value from item and value entries', function (): void {
    $item = dashboardItem('ITEM-DASH', reorderPoint: 3);
    $location = Location::factory()->create();

    ItemLedgerEntry::query()->create([
        'entry_type' => ItemLedgerEntryType::PURCHASE,
        'document_type' => 'PURCHASE_RECEIPT',
        'document_number' => 'PR-DASH-001',
        'document_line_number' => 10000,
        'item_id' => $item->id,
        'location_id' => $location->id,
        'quantity' => 5,
        'remaining_quantity' => 5,
        'cost_amount_actual' => 100,
        'general_product_posting_group_id' => $item->general_product_posting_group_id,
        'inventory_posting_group_id' => $item->inventory_posting_group_id,
        'posting_date' => '2026-06-10',
        'entry_date' => now(),
        'open' => true,
    ]);

    ItemLedgerEntry::query()->create([
        'entry_type' => ItemLedgerEntryType::SALE,
        'document_type' => 'SALES_SHIPMENT',
        'document_number' => 'SS-DASH-001',
        'document_line_number' => 10000,
        'item_id' => $item->id,
        'location_id' => $location->id,
        'quantity' => -3,
        'remaining_quantity' => 0,
        'cost_amount_actual' => 60,
        'general_product_posting_group_id' => $item->general_product_posting_group_id,
        'inventory_posting_group_id' => $item->inventory_posting_group_id,
        'posting_date' => '2026-06-11',
        'entry_date' => now(),
        'open' => false,
    ]);

    $summary = app(InventoryDashboardService::class)->summary(Carbon::parse('2026-06-01'), Carbon::parse('2026-06-30'));

    expect($summary['stock_quantity'])->toBe(2.0)
        ->and($summary['stock_value'])->toBe(40.0)
        ->and($summary['negative_stock_count'])->toBe(0)
        ->and($summary['low_stock_items'])->toHaveCount(1)
        ->and($summary['top_moving_items'][0]['item_code'])->toBe('ITEM-DASH');
});

it('calculates sales dashboard from posted sales documents and customer ledger entries', function (): void {
    $user = User::factory()->create();
    $customer = Customer::factory()->create();
    $item = dashboardItem('SALES-DASH');

    $invoice = PostedSalesInvoice::query()->create([
        'document_number' => 'PSI-DASH-001',
        'customer_id' => $customer->id,
        'customer_name' => $customer->name,
        'general_business_posting_group_id' => $customer->general_business_posting_group_id,
        'customer_posting_group_id' => $customer->customer_posting_group_id,
        'posting_date' => '2026-06-15',
        'document_date' => '2026-06-15',
        'due_date' => '2026-07-15',
        'total_amount' => 500,
        'grand_total' => 500,
        'remaining_amount' => 300,
        'paid_in_full' => false,
        'posted_by' => $user->id,
        'posted_at' => now(),
        'cancelled' => false,
    ]);

    PostedSalesInvoiceLine::query()->create([
        'posted_sales_invoice_id' => $invoice->id,
        'line_number' => 10000,
        'item_id' => $item->id,
        'item_code' => $item->item_code,
        'item_description' => $item->description,
        'posting_date' => '2026-06-15',
        'quantity' => 2,
        'quantity_base' => 2,
        'unit_of_measure_code' => 'PCS',
        'qty_per_unit_of_measure' => 1,
        'unit_price' => 250,
        'unit_cost' => 100,
        'line_total' => 500,
        'line_amount' => 500,
        'amount_including_vat' => 500,
        'cost_amount' => 200,
        'profit_amount' => 300,
    ]);

    CustomerLedgerEntry::query()->create([
        'entry_number' => dashboardNextCustomerLedgerEntryNumber(),
        'customer_id' => $customer->id,
        'document_type' => 'SALES_INVOICE',
        'document_number' => 'PSI-DASH-001',
        'description' => 'Dashboard posted invoice',
        'posting_date' => '2026-06-15',
        'document_date' => '2026-06-15',
        'debit_amount' => 500,
        'credit_amount' => 0,
        'amount' => 500,
        'running_balance' => 500,
        'remaining_amount' => 300,
        'open' => true,
        'fully_applied' => false,
        'currency_code' => 'NGN',
        'original_debit_amount' => 500,
        'original_credit_amount' => 0,
        'currency_factor' => 1,
        'general_business_posting_group_id' => $customer->general_business_posting_group_id,
        'customer_posting_group_id' => $customer->customer_posting_group_id,
        'created_by' => $user->id,
    ]);

    CustomerLedgerEntry::query()->create([
        'entry_number' => dashboardNextCustomerLedgerEntryNumber(),
        'customer_id' => $customer->id,
        'document_type' => 'PAYMENT',
        'document_number' => 'PAY-DASH-001',
        'description' => 'Dashboard payment',
        'posting_date' => '2026-06-16',
        'document_date' => '2026-06-16',
        'debit_amount' => 0,
        'credit_amount' => 200,
        'amount' => -200,
        'running_balance' => 300,
        'remaining_amount' => 0,
        'open' => false,
        'fully_applied' => true,
        'currency_code' => 'NGN',
        'original_debit_amount' => 0,
        'original_credit_amount' => 200,
        'currency_factor' => 1,
        'general_business_posting_group_id' => $customer->general_business_posting_group_id,
        'customer_posting_group_id' => $customer->customer_posting_group_id,
        'created_by' => $user->id,
    ]);

    $summary = app(SalesDashboardService::class)->summary(Carbon::parse('2026-06-01'), Carbon::parse('2026-06-30'));

    expect($summary['posted_invoices']['amount'])->toBe(500.0)
        ->and($summary['payments']['amount'])->toBe(200.0)
        ->and($summary['outstanding_receivables'])->toBe(300.0)
        ->and($summary['sales_by_customer'][0]['customer_name'])->toBe($customer->name)
        ->and($summary['sales_by_item'][0]['item_code'])->toBe('SALES-DASH');
});

it('calculates purchase dashboard from posted purchase documents and vendor ledger entries', function (): void {
    $user = User::factory()->create();
    $vendor = Vendor::factory()->create(['vendor_name' => 'Dashboard Vendor']);
    $item = dashboardItem('PUR-DASH');
    $location = Location::factory()->create();

    PostedPurchaseInvoice::query()->create([
        'document_number' => 'PPI-DASH-001',
        'vendor_id' => $vendor->id,
        'vendor_name' => $vendor->vendor_name,
        'general_business_posting_group_id' => $vendor->general_business_posting_group_id,
        'vendor_posting_group_id' => $vendor->vendor_posting_group_id,
        'posting_date' => '2026-06-15',
        'document_date' => '2026-06-15',
        'due_date' => '2026-07-15',
        'grand_total' => 800,
        'remaining_amount' => 250,
        'paid_in_full' => false,
        'posted_by' => $user->id,
        'posted_at' => now(),
        'cancelled' => false,
    ]);

    $baselinePayables = app(PurchaseDashboardService::class)
        ->summary(Carbon::parse('2026-06-01'), Carbon::parse('2026-06-30'))['outstanding_payables'];

    VendorLedgerEntry::query()->create([
        'entry_number' => dashboardNextVendorLedgerEntryNumber(),
        'vendor_id' => $vendor->id,
        'document_type' => 'PURCHASE_INVOICE',
        'document_number' => 'PPI-DASH-001',
        'description' => 'Dashboard purchase invoice',
        'posting_date' => '2026-06-15',
        'document_date' => '2026-06-15',
        'debit_amount' => 0,
        'credit_amount' => 800,
        'amount' => -800,
        'running_balance' => -800,
        'remaining_amount' => 250,
        'open' => true,
        'fully_applied' => false,
        'currency_code' => 'NGN',
        'original_debit_amount' => 0,
        'original_credit_amount' => 800,
        'currency_factor' => 1,
        'general_business_posting_group_id' => $vendor->general_business_posting_group_id,
        'vendor_posting_group_id' => $vendor->vendor_posting_group_id,
        'created_by' => $user->id,
    ]);

    $receipt = PurchaseReceipt::query()->create([
        'document_number' => 'PR-DASH-OPEN',
        'vendor_id' => $vendor->id,
        'vendor_name' => $vendor->vendor_name,
        'location_id' => $location->id,
        'posting_date' => '2026-06-14',
        'document_date' => '2026-06-14',
        'status' => 'POSTED',
    ]);

    PurchaseReceiptLine::query()->create([
        'purchase_receipt_id' => $receipt->id,
        'line_number' => 10000,
        'type' => PurchaseLineType::ITEM,
        'no' => $item->item_code,
        'description' => $item->description,
        'quantity' => 5,
        'quantity_received' => 5,
        'quantity_invoiced' => 2,
        'unit_of_measure_code' => 'PCS',
        'qty_per_unit_of_measure' => 1,
        'quantity_base' => 5,
        'qty_received_base' => 5,
        'qty_invoiced_base' => 2,
        'direct_unit_cost' => 10,
        'line_amount' => 50,
    ]);

    $summary = app(PurchaseDashboardService::class)->summary(Carbon::parse('2026-06-01'), Carbon::parse('2026-06-30'));

    expect($summary['purchases_by_vendor'][0]['vendor_name'])->toBe('Dashboard Vendor')
        ->and($summary['purchases_by_vendor'][0]['amount'])->toBe(800.0)
        ->and($summary['outstanding_payables'])->toBe(round((float) $baselinePayables + 250, 2))
        ->and($summary['invoices_not_paid']['amount'])->toBe(250.0)
        ->and($summary['receipts_not_invoiced']['quantity'])->toBe(3.0)
        ->and($summary['receipts_not_invoiced']['amount'])->toBe(30.0);
});

it('reports manufacturing dashboard and reconciliation warning signals', function (): void {
    $user = User::factory()->create();
    $this->actingAs($user);
    $item = dashboardItem('FG-DASH');
    $component = dashboardItem('RM-DASH');
    $location = Location::factory()->create();

    $order = ProductionOrder::query()->create([
        'document_number' => 'PO-DASH-001',
        'status' => ProductionOrderStatus::RELEASED,
        'source_type' => ProductionOrderSourceType::ITEM,
        'source_no' => $item->item_code,
        'item_id' => $item->id,
        'description' => 'Dashboard production order',
        'quantity' => 1,
        'unit_of_measure_code' => 'PCS',
        'quantity_base' => 1,
        'location_code' => $location->code,
        'created_by' => $user->id,
    ]);

    ProductionOrderComponent::query()->create([
        'production_order_id' => $order->id,
        'line_number' => 10000,
        'item_id' => $component->id,
        'description' => $component->description,
        'unit_of_measure_code' => 'PCS',
        'quantity_per' => 1,
        'expected_quantity' => 10,
        'expected_quantity_base' => 10,
        'remaining_quantity' => 10,
        'unit_cost' => 2,
        'total_cost' => 20,
    ]);

    ItemLedgerEntry::query()->create([
        'entry_type' => ItemLedgerEntryType::OUTPUT,
        'document_type' => 'PRODUCTION_ORDER',
        'document_number' => 'PO-DASH-001',
        'document_line_number' => 10000,
        'item_id' => $item->id,
        'location_id' => $location->id,
        'quantity' => 4,
        'remaining_quantity' => 4,
        'cost_amount_actual' => 80,
        'general_product_posting_group_id' => $item->general_product_posting_group_id,
        'inventory_posting_group_id' => $item->inventory_posting_group_id,
        'posting_date' => '2026-06-20',
        'entry_date' => now(),
        'open' => true,
    ]);

    ValueEntry::query()->create([
        'entry_no' => 200,
        'item_ledger_entry_no' => 200,
        'item_ledger_entry_type' => 6,
        'item_no' => $component->item_code,
        'location_code' => $location->code,
        'posting_date' => '2026-06-19',
        'document_type' => 'PRODUCTION_ORDER',
        'document_no' => 'PO-DASH-001',
        'quantity' => -10,
        'invoiced_quantity' => -10,
        'cost_amount_actual' => 100,
        'variance_amount' => 5,
        'production_order_no' => 'PO-DASH-001',
    ]);

    ValueEntry::query()->create([
        'entry_no' => 201,
        'item_ledger_entry_no' => 201,
        'item_ledger_entry_type' => 7,
        'item_no' => $item->item_code,
        'location_code' => $location->code,
        'posting_date' => '2026-06-20',
        'document_type' => 'PRODUCTION_ORDER',
        'document_no' => 'PO-DASH-001',
        'quantity' => 4,
        'invoiced_quantity' => 4,
        'cost_amount_actual' => 80,
        'variance_amount' => 3,
        'production_order_no' => 'PO-DASH-001',
    ]);

    $manufacturing = app(ManufacturingDashboardService::class)->summary(Carbon::parse('2026-06-01'), Carbon::parse('2026-06-30'));

    expect($manufacturing['open_production_orders'])->toBe(1)
        ->and($manufacturing['wip_value'])->toBe(20.0)
        ->and($manufacturing['output_quantity'])->toBe(4.0)
        ->and($manufacturing['component_shortages'])->toHaveCount(1)
        ->and($manufacturing['production_variance'])->toBe(8.0);

    $bankAccount = dashboardAccount('10990', 'Warning Bank G/L', AccountCategory::LIQUID_ASSET);
    BankAccount::query()->create([
        'account_code' => 'WARN-BANK',
        'account_name' => 'Warning Bank',
        'bank_name' => 'Warning Bank',
        'account_number' => 'WARN-BANK-001',
        'gl_account_id' => $bankAccount->id,
        'current_balance' => 0,
        'available_balance' => 0,
        'active' => true,
        'allow_payments' => true,
        'allow_receipts' => true,
    ]);

    dashboardGl('WARN-BANK-DOC', [
        [$bankAccount, 100, 0, 'Bank G/L without bank ledger'],
    ]);

    Cache::flush();
    $warnings = app(ReconciliationWarningService::class)->financeWarnings();

    expect($warnings['total'])->toBeGreaterThan(0)
        ->and($warnings['critical'])->toBeGreaterThan(0)
        ->and($warnings['sections']['missing_control_account_entries'])->toBeGreaterThan(0);
});

function dashboardAccount(string $number, string $name, AccountCategory $category): ChartOfAccount
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

function dashboardItem(string $itemCode, float $reorderPoint = 0): Item
{
    $productGroup = GeneralProductPostingGroup::query()->create([
        'code' => $itemCode.'-GPG',
        'description' => $itemCode.' product group',
        'blocked' => false,
    ]);
    $inventoryGroup = InventoryPostingGroup::query()->create([
        'code' => $itemCode.'-IPG',
        'description' => $itemCode.' inventory group',
        'blocked' => false,
    ]);
    $uom = UnitOfMeasure::query()->firstOrCreate(
        ['uom_code' => 'PCS'],
        [
            'description' => 'Pieces',
            'is_base_uom' => true,
        ]
    );

    InventoryPostingSetup::query()->create([
        'inventory_posting_group_id' => $inventoryGroup->id,
        'inventory_account_id' => dashboardAccount($itemCode.'-INV', $itemCode.' Inventory', AccountCategory::INVENTORY)->id,
    ]);

    return Item::query()->create([
        'item_code' => $itemCode,
        'description' => $itemCode.' item',
        'item_type' => ItemType::FINISHED_GOOD,
        'base_uom_id' => $uom->id,
        'uom_id' => $uom->id,
        'unit_cost' => 10,
        'unit_price' => 25,
        'inventory' => 999999,
        'reorder_point' => $reorderPoint > 0 ? $reorderPoint : null,
        'general_product_posting_group_id' => $productGroup->id,
        'inventory_posting_group_id' => $inventoryGroup->id,
    ]);
}

/**
 * @param  array<int, array{0: ChartOfAccount, 1: float|int, 2: float|int, 3: string}>  $lines
 */
function dashboardGl(string $documentNumber, array $lines): void
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
            'document_type' => 'DASHBOARD_TEST',
            'document_number' => $documentNumber,
            'document_date' => '2026-06-15',
            'posting_date' => '2026-06-15',
            'description' => $description,
        ]);
    }
}

function dashboardNextBankLedgerEntryNumber(): int
{
    return ((int) BankAccountLedgerEntry::query()->max('entry_number')) + 1;
}

function dashboardNextCustomerLedgerEntryNumber(): int
{
    return ((int) CustomerLedgerEntry::query()->max('entry_number')) + 1;
}

function dashboardNextVendorLedgerEntryNumber(): int
{
    return ((int) VendorLedgerEntry::query()->max('entry_number')) + 1;
}
