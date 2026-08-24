<?php

use App\Enums\ApprovalStatus;
use App\Enums\IncomeBalanceType;
use App\Enums\ItemLedgerEntryType;
use App\Enums\ItemType;
use App\Models\AccountingPeriod;
use App\Models\BankAccount;
use App\Models\ChartOfAccount;
use App\Models\Customer;
use App\Models\CustomerLedgerEntry;
use App\Models\CustomerPostingGroup;
use App\Models\GeneralBusinessPostingGroup;
use App\Models\GeneralLedgerSetup;
use App\Models\GeneralPostingSetup;
use App\Models\GeneralProductPostingGroup;
use App\Models\GlEntry;
use App\Models\InventoryPostingGroup;
use App\Models\InventoryPostingSetup;
use App\Models\Item;
use App\Models\ItemLedgerEntry;
use App\Models\ItemUomAssignment;
use App\Models\NumberSeries;
use App\Models\NumberSeriesLine;
use App\Models\Payment;
use App\Models\Permission;
use App\Models\PostedSalesCreditMemo;
use App\Models\PostedSalesInvoice;
use App\Models\PostedSalesInvoiceLine;
use App\Models\SalesCreditMemo;
use App\Models\SalesInvoice;
use App\Models\UnitOfMeasure;
use App\Models\User;
use App\Models\ValueEntry;
use App\Services\Finance\PaymentService;
use App\Services\Sales\SalesCreditMemoService;
use App\Services\Sales\SalesInvoiceService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Validation\ValidationException;

uses(RefreshDatabase::class);

test('sales invoice posting creates traceable item, value, customer, and balanced gl entries using base quantity', function () {
    $fixture = salesPostingFixture();
    $this->actingAs($fixture['user']);

    $invoice = SalesInvoice::query()->create([
        'invoice_number' => 'SI-TRACE-001',
        'customer_id' => $fixture['customer']->id,
        'status' => ApprovalStatus::APPROVED,
        'invoice_date' => now()->toDateString(),
        'due_date' => now()->addDays(7)->toDateString(),
        'currency_code' => 'NGN',
        'approved_by' => $fixture['user']->id,
        'approved_at' => now(),
    ]);

    $invoice->lines()->create([
        'item_id' => $fixture['item']->id,
        'description' => 'One carton sale',
        'quantity' => 1,
        'unit_of_measure' => 'CT',
        'unit_price' => 1000,
    ]);

    app(SalesInvoiceService::class)->post($invoice);

    $invoice->refresh();
    $itemLedgerEntry = ItemLedgerEntry::query()
        ->where('document_type', 'SALES_INVOICE')
        ->where('document_number', 'SI-TRACE-001')
        ->firstOrFail();

    expect($invoice->status)->toBe(ApprovalStatus::POSTED)
        ->and((float) $itemLedgerEntry->quantity)->toBe(-288.0)
        ->and($itemLedgerEntry->entry_type)->toBe(ItemLedgerEntryType::SALE)
        ->and((float) $itemLedgerEntry->cost_amount_actual)->toBe(2880.0)
        ->and((float) $fixture['item']->fresh()->inventory)->toBe(0.0);

    expect(ValueEntry::query()
        ->where('item_ledger_entry_no', $itemLedgerEntry->entry_number)
        ->where('document_no', 'SI-TRACE-001')
        ->where('quantity', -288)
        ->where('cost_amount_actual', 2880)
        ->exists())->toBeTrue();

    expect(PostedSalesInvoice::query()->where('document_number', 'SI-TRACE-001')->exists())->toBeTrue()
        ->and(CustomerLedgerEntry::query()->where('document_number', 'SI-TRACE-001')->exists())->toBeTrue();

    $postedLine = PostedSalesInvoice::query()->where('document_number', 'SI-TRACE-001')->firstOrFail()->lines()->firstOrFail();
    expect((float) $postedLine->quantity)->toBe(1.0)
        ->and((float) $postedLine->quantity_base)->toBe(288.0)
        ->and((float) $postedLine->qty_per_unit_of_measure)->toBe(288.0)
        ->and($postedLine->item_ledger_entry_id)->toBe($itemLedgerEntry->id);

    $glEntries = GlEntry::query()->where('document_number', 'SI-TRACE-001')->get();
    $receivablesAccount = ChartOfAccount::query()->where('account_number', '1100')->firstOrFail();
    expect(round((float) $glEntries->sum('debit_amount'), 2))
        ->toBe(round((float) $glEntries->sum('credit_amount'), 2))
        ->and((float) $receivablesAccount->fresh()->balance)->toBe(1000.0)
        ->and((float) GlEntry::query()->where('chart_of_account_id', $receivablesAccount->id)->sum('amount'))->toBe(1000.0);

    Permission::query()->firstOrCreate(['name' => 'finance.payment.post', 'guard_name' => 'web']);
    $fixture['user']->givePermissionTo('finance.payment.post');
    salesPostingEnsureBankLedgerNumberSeries();

    $bankAccount = BankAccount::factory()->receiptOnly()->create([
        'current_balance' => 0,
        'available_balance' => 0,
    ]);
    $payment = Payment::factory()->customerReceipt()->create([
        'party_id' => $fixture['customer']->id,
        'party_name' => $fixture['customer']->name,
        'bank_account_id' => $bankAccount->id,
        'payment_amount' => 1000,
        'payment_amount_lcy' => 1000,
        'applied_amount' => 0,
        'unapplied_amount' => 1000,
        'status' => 'APPROVED',
        'created_by' => $fixture['user']->id,
    ]);

    app(PaymentService::class)->post($payment, $fixture['user']->id);

    expect((float) $receivablesAccount->fresh()->balance)->toBe(0.0)
        ->and((float) GlEntry::query()->where('chart_of_account_id', $receivablesAccount->id)->sum('amount'))->toBe(0.0);

    expect(Artisan::call('biwms:finance-reconcile', ['--json' => true]))->toBe(0);
    $financeReconcile = json_decode(trim(Artisan::output()), true);

    expect($financeReconcile['customer_ledger_receivables_mismatches'])->toBeEmpty()
        ->and($financeReconcile['customer_ledger_missing_posting_groups'])->toBeEmpty();

    $this->expectExceptionMessage('Invoice already posted');
    app(SalesInvoiceService::class)->post($invoice->fresh());
});

test('sales invoice posting rejects missing exact posting setup and rolls back ledger creation', function () {
    $fixture = salesPostingFixture(createGeneralPostingSetup: false);
    $this->actingAs($fixture['user']);

    $invoice = SalesInvoice::query()->create([
        'invoice_number' => 'SI-MISSING-SETUP',
        'customer_id' => $fixture['customer']->id,
        'status' => ApprovalStatus::APPROVED,
        'invoice_date' => now()->toDateString(),
        'due_date' => now()->addDays(7)->toDateString(),
        'currency_code' => 'NGN',
        'approved_by' => $fixture['user']->id,
        'approved_at' => now(),
    ]);

    $invoice->lines()->create([
        'item_id' => $fixture['item']->id,
        'description' => 'One carton sale',
        'quantity' => 1,
        'unit_of_measure' => 'CT',
        'unit_price' => 1000,
    ]);

    expect(fn () => app(SalesInvoiceService::class)->post($invoice))
        ->toThrow(Exception::class, 'General posting setup missing');

    expect($invoice->fresh()->status)->toBe(ApprovalStatus::APPROVED)
        ->and(ItemLedgerEntry::query()->where('document_number', 'SI-MISSING-SETUP')->exists())->toBeFalse()
        ->and(ValueEntry::query()->where('document_no', 'SI-MISSING-SETUP')->exists())->toBeFalse()
        ->and(GlEntry::query()->where('document_number', 'SI-MISSING-SETUP')->exists())->toBeFalse();
});

test('sales credit memo reverses inventory, value, customer, and gl entries using base quantity', function () {
    $fixture = salesPostingFixture();
    grantSalesCreditMemoPostPermission($fixture['user']);
    $this->actingAs($fixture['user']);

    $creditMemo = SalesCreditMemo::query()->create([
        'memo_number' => 'SCM-TRACE-001',
        'customer_id' => $fixture['customer']->id,
        'sales_invoice_id' => null,
        'total_amount' => 1000,
        'status' => ApprovalStatus::APPROVED,
        'reason' => 'Return',
        'effective_date' => now()->toDateString(),
        'currency_code' => 'NGN',
    ]);

    $creditMemo->items()->create([
        'item_id' => $fixture['item']->id,
        'quantity' => 1,
        'unit_of_measure_code' => 'CT',
        'unit_price' => 1000,
    ]);

    app(SalesCreditMemoService::class)->post($creditMemo);

    $itemLedgerEntry = ItemLedgerEntry::query()
        ->where('document_type', 'SALES_CREDIT_MEMO')
        ->where('document_number', 'SCM-TRACE-001')
        ->firstOrFail();

    expect($creditMemo->fresh()->status)->toBe(ApprovalStatus::POSTED)
        ->and($creditMemo->fresh()->posted_by)->toBe($fixture['user']->id)
        ->and($creditMemo->fresh()->posted_at)->not->toBeNull()
        ->and((float) $itemLedgerEntry->quantity)->toBe(288.0)
        ->and($itemLedgerEntry->entry_type)->toBe(ItemLedgerEntryType::SALE)
        ->and((float) $fixture['item']->fresh()->inventory)->toBe(576.0);

    expect(ValueEntry::query()
        ->where('item_ledger_entry_no', $itemLedgerEntry->entry_number)
        ->where('document_no', 'SCM-TRACE-001')
        ->where('quantity', 288)
        ->exists())->toBeTrue();

    expect(PostedSalesCreditMemo::query()->where('document_number', 'SCM-TRACE-001')->exists())->toBeTrue()
        ->and(CustomerLedgerEntry::query()->where('document_type', 'SALES_CREDIT_MEMO')->where('document_number', 'SCM-TRACE-001')->exists())->toBeTrue();

    $glEntries = GlEntry::query()->where('document_number', 'SCM-TRACE-001')->get();
    expect(round((float) $glEntries->sum('debit_amount'), 2))
        ->toBe(round((float) $glEntries->sum('credit_amount'), 2));

    $originalPostedAt = $creditMemo->fresh()->posted_at;

    expect(fn () => app(SalesCreditMemoService::class)->post($creditMemo->fresh()))
        ->toThrow(Exception::class, 'Sales credit memo is already posted.');

    expect($creditMemo->fresh()->posted_at->equalTo($originalPostedAt))->toBeTrue();
});

test('linked sales credit memo reduces receivable returns stock and blocks over-crediting', function () {
    $fixture = salesPostingFixture();
    grantSalesCreditMemoPostPermission($fixture['user']);
    $this->actingAs($fixture['user']);

    $invoice = SalesInvoice::query()->create([
        'invoice_number' => 'SI-RETURN-001',
        'customer_id' => $fixture['customer']->id,
        'status' => ApprovalStatus::APPROVED,
        'invoice_date' => now()->toDateString(),
        'due_date' => now()->addDays(7)->toDateString(),
        'currency_code' => 'NGN',
        'approved_by' => $fixture['user']->id,
        'approved_at' => now(),
    ]);

    $invoice->lines()->create([
        'item_id' => $fixture['item']->id,
        'description' => 'One carton sale',
        'quantity' => 1,
        'unit_of_measure' => 'CT',
        'unit_price' => 1000,
    ]);

    app(SalesInvoiceService::class)->post($invoice);

    $postedInvoice = PostedSalesInvoice::query()->where('document_number', 'SI-RETURN-001')->firstOrFail();

    expect((float) $fixture['item']->fresh()->inventory)->toBe(0.0)
        ->and((float) CustomerLedgerEntry::query()->where('customer_id', $fixture['customer']->id)->sum('amount'))->toBe(1000.0);

    $creditMemo = SalesCreditMemo::query()->create([
        'memo_number' => 'SCM-RETURN-001',
        'customer_id' => $fixture['customer']->id,
        'sales_invoice_id' => $invoice->id,
        'total_amount' => 1000,
        'status' => ApprovalStatus::APPROVED,
        'reason' => 'Return',
        'effective_date' => now()->toDateString(),
        'currency_code' => 'NGN',
    ]);

    $creditMemo->items()->create([
        'item_id' => $fixture['item']->id,
        'quantity' => 1,
        'unit_of_measure_code' => 'CT',
        'unit_price' => 1000,
    ]);

    app(SalesCreditMemoService::class)->post($creditMemo);

    $postedMemo = PostedSalesCreditMemo::query()->where('document_number', 'SCM-RETURN-001')->firstOrFail();

    expect((float) $fixture['item']->fresh()->inventory)->toBe(288.0)
        ->and((float) CustomerLedgerEntry::query()->where('customer_id', $fixture['customer']->id)->sum('amount'))->toBe(0.0)
        ->and($postedMemo->corrected_invoice_id)->toBe($postedInvoice->id)
        ->and($postedMemo->corrected_invoice_number)->toBe('SI-RETURN-001')
        ->and(ItemLedgerEntry::query()
            ->where('document_type', 'SALES_CREDIT_MEMO')
            ->where('document_number', 'SCM-RETURN-001')
            ->where('quantity', 288)
            ->exists())->toBeTrue();

    $overCreditMemo = SalesCreditMemo::query()->create([
        'memo_number' => 'SCM-OVER-001',
        'customer_id' => $fixture['customer']->id,
        'sales_invoice_id' => $invoice->id,
        'total_amount' => 1000,
        'status' => ApprovalStatus::APPROVED,
        'reason' => 'Return again',
        'effective_date' => now()->toDateString(),
        'currency_code' => 'NGN',
    ]);

    $overCreditMemo->items()->create([
        'item_id' => $fixture['item']->id,
        'quantity' => 1,
        'unit_of_measure_code' => 'CT',
        'unit_price' => 1000,
    ]);

    expect(fn () => app(SalesCreditMemoService::class)->post($overCreditMemo))
        ->toThrow(ValidationException::class, 'exceeds invoiced quantity');
});

test('posted paid sales invoice remains eligible for linked sales credit memo returns with exact line lineage', function () {
    $fixture = salesPostingFixture();
    grantSalesCreditMemoPostPermission($fixture['user']);
    $this->actingAs($fixture['user']);

    $invoice = createApprovedSalesInvoiceForReturn($fixture, 'SI-PAID-RETURN-001', 1);
    app(SalesInvoiceService::class)->post($invoice);

    $postedInvoice = PostedSalesInvoice::query()
        ->where('document_number', 'SI-PAID-RETURN-001')
        ->firstOrFail();
    $postedInvoice->forceFill([
        'paid_in_full' => true,
        'remaining_amount' => 0,
        'paid_in_full_date' => now(),
    ])->save();

    $postedInvoiceLine = $postedInvoice->lines()->firstOrFail();
    $originalOutboundEntryId = $postedInvoiceLine->item_ledger_entry_id;

    $creditMemo = createApprovedLinkedSalesCreditMemo(
        $fixture,
        'SCM-PAID-RETURN-001',
        $postedInvoice,
        $postedInvoiceLine,
        0.25,
    );

    app(SalesCreditMemoService::class)->post($creditMemo);

    $postedMemo = PostedSalesCreditMemo::query()
        ->where('document_number', 'SCM-PAID-RETURN-001')
        ->firstOrFail();
    $postedMemoLine = $postedMemo->lines()->firstOrFail();
    $returnEntry = ItemLedgerEntry::query()
        ->where('document_type', 'SALES_CREDIT_MEMO')
        ->where('document_number', 'SCM-PAID-RETURN-001')
        ->firstOrFail();
    $valueEntry = ValueEntry::query()
        ->where('item_ledger_entry_no', $returnEntry->entry_number)
        ->where('document_no', 'SCM-PAID-RETURN-001')
        ->firstOrFail();

    expect($postedInvoice->fresh()->status)->toBe('PAID')
        ->and($postedMemo->corrected_invoice_id)->toBe($postedInvoice->id)
        ->and($postedMemo->corrected_invoice_number)->toBe('SI-PAID-RETURN-001')
        ->and($postedMemoLine->corrected_invoice_line_id)->toBe($postedInvoiceLine->id)
        ->and((float) $postedMemoLine->quantity)->toBe(-0.25)
        ->and((float) $returnEntry->quantity)->toBe(72.0)
        ->and((float) $fixture['item']->fresh()->inventory)->toBe(72.0)
        ->and($valueEntry->accounting_metadata['original_outbound_item_ledger_entry_id'] ?? null)->toBe($originalOutboundEntryId);

    $glEntries = GlEntry::query()->where('document_number', 'SCM-PAID-RETURN-001')->get();
    expect(round((float) $glEntries->sum('debit_amount'), 2))
        ->toBe(round((float) $glEntries->sum('credit_amount'), 2));
});

test('prior linked sales credit memo returns reduce remaining returnable posted invoice line quantity', function () {
    $fixture = salesPostingFixture();
    grantSalesCreditMemoPostPermission($fixture['user']);
    $this->actingAs($fixture['user']);

    $invoice = createApprovedSalesInvoiceForReturn($fixture, 'SI-PART-RET-001', 1);
    app(SalesInvoiceService::class)->post($invoice);

    $postedInvoice = PostedSalesInvoice::query()
        ->where('document_number', 'SI-PART-RET-001')
        ->firstOrFail();
    $postedInvoiceLine = $postedInvoice->lines()->firstOrFail();

    app(SalesCreditMemoService::class)->post(createApprovedLinkedSalesCreditMemo(
        $fixture,
        'SCM-PART-RET-001',
        $postedInvoice,
        $postedInvoiceLine,
        0.25,
    ));

    app(SalesCreditMemoService::class)->post(createApprovedLinkedSalesCreditMemo(
        $fixture,
        'SCM-PART-RET-002',
        $postedInvoice,
        $postedInvoiceLine,
        0.75,
    ));

    $overReturnMemo = createApprovedLinkedSalesCreditMemo(
        $fixture,
        'SCM-PART-RET-003',
        $postedInvoice,
        $postedInvoiceLine,
        0.01,
    );

    expect(fn () => app(SalesCreditMemoService::class)->post($overReturnMemo))
        ->toThrow(ValidationException::class, 'exceeds remaining returnable quantity');

    expect((float) $fixture['item']->fresh()->inventory)->toBe(288.0)
        ->and((float) PostedSalesCreditMemo::query()
            ->where('corrected_invoice_id', $postedInvoice->id)
            ->with('lines')
            ->get()
            ->flatMap->lines
            ->sum(fn ($line): float => abs((float) $line->quantity))
        )->toBe(1.0);
});

test('sales credit memo posting requires permission and rolls back on missing setup', function () {
    $fixture = salesPostingFixture(createGeneralPostingSetup: false);
    $this->actingAs($fixture['user']);

    $creditMemo = SalesCreditMemo::query()->create([
        'memo_number' => 'SCM-MISSING-SETUP',
        'customer_id' => $fixture['customer']->id,
        'sales_invoice_id' => null,
        'total_amount' => 1000,
        'status' => ApprovalStatus::APPROVED,
        'reason' => 'Return',
        'effective_date' => now()->toDateString(),
        'currency_code' => 'NGN',
    ]);

    $creditMemo->items()->create([
        'item_id' => $fixture['item']->id,
        'quantity' => 1,
        'unit_of_measure_code' => 'CT',
        'unit_price' => 1000,
    ]);

    expect(fn () => app(SalesCreditMemoService::class)->post($creditMemo))
        ->toThrow(AuthorizationException::class);

    grantSalesCreditMemoPostPermission($fixture['user']);

    expect(fn () => app(SalesCreditMemoService::class)->post($creditMemo->fresh()))
        ->toThrow(Exception::class, 'General posting setup missing');

    expect($creditMemo->fresh()->status)->toBe(ApprovalStatus::APPROVED)
        ->and($creditMemo->fresh()->posted_at)->toBeNull()
        ->and(ItemLedgerEntry::query()->where('document_number', 'SCM-MISSING-SETUP')->exists())->toBeFalse()
        ->and(ValueEntry::query()->where('document_no', 'SCM-MISSING-SETUP')->exists())->toBeFalse()
        ->and(GlEntry::query()->where('document_number', 'SCM-MISSING-SETUP')->exists())->toBeFalse();
});

/**
 * @return array{user: User, customer: Customer, item: Item}
 */
function salesPostingFixture(bool $createGeneralPostingSetup = true): array
{
    GeneralLedgerSetup::query()->updateOrCreate(
        ['company_name' => 'Default Company'],
        [
            'allow_posting_from' => '2026-01-01',
            'allow_posting_to' => '2026-12-31',
        ],
    );

    AccountingPeriod::query()->firstOrCreate(
        ['name' => 'FY2026'],
        [
            'start_date' => '2026-01-01',
            'end_date' => '2026-12-31',
            'is_closed' => false,
        ],
    );

    $user = User::factory()->create();

    $receivablesAccount = postingTestAccount('1100', 'Accounts Receivable', 'receivable', IncomeBalanceType::BALANCE_SHEET);
    $inventoryAccount = postingTestAccount('1200', 'Inventory', 'inventory', IncomeBalanceType::BALANCE_SHEET);
    $revenueAccount = postingTestAccount('4000', 'Sales Revenue', 'revenue', IncomeBalanceType::INCOME_STATEMENT);
    $cogsAccount = postingTestAccount('5000', 'Cost of Goods Sold', 'cogs', IncomeBalanceType::INCOME_STATEMENT);

    $businessGroup = GeneralBusinessPostingGroup::query()->create([
        'code' => 'DOMESTIC',
        'description' => 'Domestic',
        'blocked' => false,
    ]);
    $productGroup = GeneralProductPostingGroup::query()->create([
        'code' => 'FINISHED',
        'description' => 'Finished Goods',
        'blocked' => false,
    ]);
    $inventoryGroup = InventoryPostingGroup::query()->create([
        'code' => 'FINISHED',
        'description' => 'Finished Goods',
        'blocked' => false,
    ]);
    $customerPostingGroup = CustomerPostingGroup::query()->create([
        'code' => 'DOMESTIC',
        'description' => 'Domestic Customers',
        'receivables_account_id' => $receivablesAccount->id,
        'blocked' => false,
    ]);

    InventoryPostingSetup::query()->create([
        'inventory_posting_group_id' => $inventoryGroup->id,
        'location_id' => null,
        'inventory_account_id' => $inventoryAccount->id,
    ]);

    if ($createGeneralPostingSetup) {
        GeneralPostingSetup::query()->create([
            'general_business_posting_group_id' => $businessGroup->id,
            'general_product_posting_group_id' => $productGroup->id,
            'sales_account_id' => $revenueAccount->id,
            'cogs_account_id' => $cogsAccount->id,
            'blocked' => false,
        ]);
    }

    $baseUom = UnitOfMeasure::query()->create([
        'uom_code' => 'PCS',
        'description' => 'Pieces',
        'is_base_uom' => true,
    ]);
    $cartonUom = UnitOfMeasure::query()->create([
        'uom_code' => 'CT',
        'description' => 'Carton',
        'is_base_uom' => false,
    ]);

    $item = Item::query()->create([
        'item_code' => 'FG-CT',
        'description' => 'Finished Carton Item',
        'item_type' => ItemType::FINISHED_GOOD,
        'base_uom_id' => $baseUom->id,
        'unit_cost' => 10,
        'inventory' => 288,
        'general_product_posting_group_id' => $productGroup->id,
        'inventory_posting_group_id' => $inventoryGroup->id,
    ]);

    ItemUomAssignment::query()->create([
        'item_id' => $item->id,
        'uom_id' => $cartonUom->id,
        'uom_type' => 'SALES',
        'conversion_factor' => 288,
        'is_default' => true,
    ]);

    $customer = Customer::factory()->create([
        'general_business_posting_group_id' => $businessGroup->id,
        'customer_posting_group_id' => $customerPostingGroup->id,
        'vat_bus_posting_group' => null,
    ]);

    return compact('user', 'customer', 'item');
}

function postingTestAccount(
    string $number,
    string $name,
    string $category,
    IncomeBalanceType $incomeBalance,
): ChartOfAccount {
    return ChartOfAccount::query()->create([
        'account_number' => $number,
        'name' => $name,
        'account_category' => $category,
        'income_balance' => $incomeBalance,
        'direct_posting' => true,
        'blocked' => false,
    ]);
}

function grantSalesCreditMemoPostPermission(User $user): void
{
    Permission::query()->firstOrCreate([
        'name' => 'sales.credit_memo.post',
        'guard_name' => 'web',
    ]);

    $user->givePermissionTo('sales.credit_memo.post');
}

function salesPostingEnsureBankLedgerNumberSeries(): void
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

function createApprovedSalesInvoiceForReturn(array $fixture, string $invoiceNumber, float $quantity): SalesInvoice
{
    $invoice = SalesInvoice::query()->create([
        'invoice_number' => $invoiceNumber,
        'customer_id' => $fixture['customer']->id,
        'status' => ApprovalStatus::APPROVED,
        'invoice_date' => now()->toDateString(),
        'due_date' => now()->addDays(7)->toDateString(),
        'currency_code' => 'NGN',
        'approved_by' => $fixture['user']->id,
        'approved_at' => now(),
    ]);

    $invoice->lines()->create([
        'item_id' => $fixture['item']->id,
        'description' => 'One carton sale',
        'quantity' => $quantity,
        'unit_of_measure' => 'CT',
        'unit_price' => 1000,
    ]);

    return $invoice;
}

function createApprovedLinkedSalesCreditMemo(
    array $fixture,
    string $memoNumber,
    PostedSalesInvoice $postedInvoice,
    PostedSalesInvoiceLine $postedInvoiceLine,
    float $quantity,
): SalesCreditMemo {
    $creditMemo = SalesCreditMemo::query()->create([
        'memo_number' => $memoNumber,
        'customer_id' => $fixture['customer']->id,
        'sales_invoice_id' => null,
        'posted_sales_invoice_id' => $postedInvoice->id,
        'total_amount' => 1000,
        'status' => ApprovalStatus::APPROVED,
        'reason' => 'Return',
        'effective_date' => now()->toDateString(),
        'currency_code' => 'NGN',
    ]);

    $creditMemo->items()->create([
        'item_id' => $fixture['item']->id,
        'posted_sales_invoice_line_id' => $postedInvoiceLine->id,
        'quantity' => $quantity,
        'unit_of_measure_code' => 'CT',
        'unit_price' => 1000,
    ]);

    return $creditMemo;
}
