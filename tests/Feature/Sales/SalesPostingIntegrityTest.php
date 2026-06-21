<?php

use App\Enums\ApprovalStatus;
use App\Enums\IncomeBalanceType;
use App\Enums\ItemLedgerEntryType;
use App\Enums\ItemType;
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
use App\Models\ItemUomAssignment;
use App\Models\Permission;
use App\Models\PostedSalesCreditMemo;
use App\Models\PostedSalesInvoice;
use App\Models\SalesCreditMemo;
use App\Models\SalesInvoice;
use App\Models\UnitOfMeasure;
use App\Models\User;
use App\Models\ValueEntry;
use App\Services\Sales\SalesCreditMemoService;
use App\Services\Sales\SalesInvoiceService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;

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
    expect(round((float) $glEntries->sum('debit_amount'), 2))
        ->toBe(round((float) $glEntries->sum('credit_amount'), 2));

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
        ->toThrow(Exception::class, 'No posting setup found');

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
        'sales_invoice_id' => $fixture['user']->id,
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

    expect(fn () => app(SalesCreditMemoService::class)->post($creditMemo->fresh()))
        ->toThrow(Exception::class, 'Sales credit memo is already posted.');
});

test('sales credit memo posting requires permission and rolls back on missing setup', function () {
    $fixture = salesPostingFixture(createGeneralPostingSetup: false);
    $this->actingAs($fixture['user']);

    $creditMemo = SalesCreditMemo::query()->create([
        'memo_number' => 'SCM-MISSING-SETUP',
        'customer_id' => $fixture['customer']->id,
        'sales_invoice_id' => $fixture['user']->id,
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
        ->toThrow(Exception::class, 'Posting setup missing');

    expect($creditMemo->fresh()->status)->toBe(ApprovalStatus::APPROVED)
        ->and(ItemLedgerEntry::query()->where('document_number', 'SCM-MISSING-SETUP')->exists())->toBeFalse()
        ->and(ValueEntry::query()->where('document_no', 'SCM-MISSING-SETUP')->exists())->toBeFalse()
        ->and(GlEntry::query()->where('document_number', 'SCM-MISSING-SETUP')->exists())->toBeFalse();
});

/**
 * @return array{user: User, customer: Customer, item: Item}
 */
function salesPostingFixture(bool $createGeneralPostingSetup = true): array
{
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
