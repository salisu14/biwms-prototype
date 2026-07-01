<?php

use App\Enums\AccountCategory;
use App\Enums\BankAccountLedgerEntryStatus;
use App\Enums\BankAccountLedgerEntryType;
use App\Enums\IncomeBalanceType;
use App\Enums\SourceType;
use App\Models\BankAccount;
use App\Models\BankAccountLedgerEntry;
use App\Models\ChartOfAccount;
use App\Models\Customer;
use App\Models\CustomerLedgerEntry;
use App\Models\CustomerPostingGroup;
use App\Models\GlEntry;
use App\Models\InventoryPostingGroup;
use App\Models\InventoryPostingSetup;
use App\Models\User;
use App\Models\ValueEntry;
use App\Models\Vendor;
use App\Models\VendorLedgerEntry;
use App\Models\VendorPostingGroup;
use App\Services\Finance\BalanceSheetService;
use App\Services\Finance\GeneralLedgerService;
use App\Services\IncomeStatementService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;

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

    expect($report['customer_ledger_receivables_mismatches'][0]['classification'])->toBe('customer_ledger_receivables_mismatch')
        ->and($report['vendor_ledger_payables_mismatches'][0]['classification'])->toBe('vendor_ledger_payables_mismatch')
        ->and($report['bank_ledger_gl_mismatches'][0]['classification'])->toBe('bank_ledger_gl_mismatch')
        ->and($report['inventory_value_gl_mismatches'][0]['classification'])->toBe('inventory_value_gl_mismatch');
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
