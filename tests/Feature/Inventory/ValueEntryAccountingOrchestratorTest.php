<?php

declare(strict_types=1);

use App\Enums\AccountCategory;
use App\Enums\AccountStructuralType;
use App\Enums\IncomeBalanceType;
use App\Enums\ItemLedgerEntryType;
use App\Enums\ItemType;
use App\Models\AccountingPeriod;
use App\Models\ChartOfAccount;
use App\Models\GeneralBusinessPostingGroup;
use App\Models\GeneralLedgerSetup;
use App\Models\GeneralPostingSetup;
use App\Models\GeneralProductPostingGroup;
use App\Models\GlEntry;
use App\Models\InventoryPostingGroup;
use App\Models\InventoryPostingSetup;
use App\Models\Item;
use App\Models\ItemLedgerEntry;
use App\Models\Location;
use App\Models\PostingTransaction;
use App\Models\ValueEntry;
use App\Services\Finance\GeneralLedgerService;
use App\Services\Inventory\ItemApplicationService;
use App\Services\Inventory\ValueEntryAccountingOrchestrator;
use App\Services\Inventory\ValueEntryService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    GeneralLedgerSetup::query()->updateOrCreate(
        ['company_name' => 'Default Company'],
        [
            'allow_posting_from' => '2026-01-01',
            'allow_posting_to' => '2026-12-31',
        ],
    );

    AccountingPeriod::query()->firstOrCreate([
        'start_date' => '2026-01-01',
        'end_date' => '2026-12-31',
    ], [
        'name' => 'FY2026',
        'is_closed' => false,
    ]);
});

it('posts sales shipment value entries through one balanced posting transaction', function (): void {
    $fixture = valueEntryAccountingFixture();
    $itemLedgerEntry = valueEntryAccountingItemLedgerEntry($fixture, ItemLedgerEntryType::SALE, -5, 125, 'SALES_ORDER_SHIPMENT');
    $valueEntry = ValueEntry::query()->where('item_ledger_entry_no', $itemLedgerEntry->entry_number)->firstOrFail();

    $transaction = app(ValueEntryAccountingOrchestrator::class)->post($valueEntry);
    $second = app(ValueEntryAccountingOrchestrator::class)->post($valueEntry->fresh());

    expect($transaction)->toBeInstanceOf(PostingTransaction::class)
        ->and($second?->id)->toBe($transaction->id)
        ->and(GlEntry::query()->where('posting_transaction_id', $transaction->id)->count())->toBe(2)
        ->and((float) GlEntry::query()->where('posting_transaction_id', $transaction->id)->sum('debit_amount'))->toBe(125.0)
        ->and((float) GlEntry::query()->where('posting_transaction_id', $transaction->id)->sum('credit_amount'))->toBe(125.0);

    $postedValueEntry = $valueEntry->fresh();
    expect($postedValueEntry->gl_posted)->toBeTrue()
        ->and((float) $postedValueEntry->unit_cost)->toBe(25.0)
        ->and($postedValueEntry->posting_transaction_id)->toBe($transaction->id)
        ->and($postedValueEntry->gl_account_no)->toBe($fixture['cogsAccount']->account_number)
        ->and($postedValueEntry->balancing_account_no)->toBe($fixture['inventoryAccount']->account_number);
});

it('normalizes value entry unit cost as positive economic cost across inventory movements', function (): void {
    $fixture = valueEntryAccountingFixture();

    $cases = [
        [ItemLedgerEntryType::SALE, -5.0, 125.0, 0.0, 'SALES_ORDER_SHIPMENT', 25.0],
        [ItemLedgerEntryType::PURCHASE, 5.0, 125.0, 0.0, 'PURCHASE_INVOICE', 25.0],
        [ItemLedgerEntryType::PURCHASE, 5.0, 0.0, 125.0, 'PURCHASE_RECEIPT', 25.0],
        [ItemLedgerEntryType::PURCHASE, -5.0, 125.0, 0.0, 'PURCHASE_CREDIT_MEMO', 25.0],
        [ItemLedgerEntryType::POSITIVE_ADJUSTMENT, 3.0, 30.0, 0.0, 'INVENTORY_ADJUSTMENT', 10.0],
        [ItemLedgerEntryType::NEGATIVE_ADJUSTMENT, -3.0, 30.0, 0.0, 'INVENTORY_ADJUSTMENT', 10.0],
        [ItemLedgerEntryType::TRANSFER, -4.0, 40.0, 0.0, 'WAREHOUSE_TRANSFER', 10.0],
        [ItemLedgerEntryType::TRANSFER, 4.0, 40.0, 0.0, 'WAREHOUSE_TRANSFER', 10.0],
        [ItemLedgerEntryType::CONSUMPTION, -2.0, 50.0, 0.0, 'PRODUCTION_ORDER', 25.0],
        [ItemLedgerEntryType::OUTPUT, 2.0, 50.0, 0.0, 'PRODUCTION_ORDER', 25.0],
    ];

    foreach ($cases as [$entryType, $quantity, $actualCost, $expectedCost, $documentType, $expectedUnitCost]) {
        $itemLedgerEntry = valueEntryAccountingItemLedgerEntry($fixture, $entryType, $quantity, $actualCost, $documentType, $expectedCost);
        $valueEntry = ValueEntry::query()->where('item_ledger_entry_no', $itemLedgerEntry->entry_number)->firstOrFail();

        expect((float) $valueEntry->quantity)->toBe($quantity)
            ->and((float) $valueEntry->cost_amount_actual)->toBe($actualCost)
            ->and((float) $valueEntry->cost_amount_expected)->toBe($expectedCost)
            ->and((float) $valueEntry->unit_cost)->toBe($expectedUnitCost);
    }
});

it('keeps value entry unit cost aligned with item application economic unit cost', function (): void {
    $fixture = valueEntryAccountingFixture();
    valueEntryAccountingItemLedgerEntry($fixture, ItemLedgerEntryType::PURCHASE, 10, 250, 'PURCHASE_INVOICE');
    $outbound = valueEntryAccountingItemLedgerEntry($fixture, ItemLedgerEntryType::CONSUMPTION, -4, 0, 'PRODUCTION_ORDER');

    $applications = app(ItemApplicationService::class)->applyOutbound($outbound, 'unit_cost_sign_regression');
    $valueEntry = app(ValueEntryService::class)->ensureForItemLedgerEntry($outbound->fresh());

    expect($applications)->toHaveCount(1)
        ->and((float) $applications[0]->unit_cost)->toBe(25.0)
        ->and((float) $outbound->fresh()->cost_amount_actual)->toBe(100.0)
        ->and((float) $valueEntry?->quantity)->toBe(-4.0)
        ->and((float) $valueEntry?->cost_amount_actual)->toBe(100.0)
        ->and((float) $valueEntry?->unit_cost)->toBe(25.0);
});

it('keeps prod 00003 style consumption unit cost positive at high precision', function (): void {
    $fixture = valueEntryAccountingFixture();
    $itemLedgerEntry = valueEntryAccountingItemLedgerEntry(
        $fixture,
        ItemLedgerEntryType::CONSUMPTION,
        -17.065728,
        13285.6692,
        'PRODUCTION_ORDER',
    );

    $valueEntry = ValueEntry::query()->where('item_ledger_entry_no', $itemLedgerEntry->entry_number)->firstOrFail();

    expect($valueEntry->quantity)->toBe('-17.06572800')
        ->and($valueEntry->cost_amount_actual)->toBe('13285.6692')
        ->and($valueEntry->unit_cost)->toBe('778.49999719');
});

it('keeps expected cost value entries out of gl unless explicitly enabled', function (): void {
    $fixture = valueEntryAccountingFixture();
    $itemLedgerEntry = valueEntryAccountingItemLedgerEntry($fixture, ItemLedgerEntryType::PURCHASE, 5, 0, 'PURCHASE_RECEIPT', 125);
    $valueEntry = ValueEntry::query()->where('item_ledger_entry_no', $itemLedgerEntry->entry_number)->firstOrFail();

    expect($valueEntry->expected_cost)->toBeTrue();
    $valueEntry->forceFill([
        'expected_cost' => true,
        'value_entry_state' => 'expected',
    ])->save();

    $transaction = app(ValueEntryAccountingOrchestrator::class)->post($valueEntry);

    expect($transaction)->toBeNull()
        ->and($valueEntry->fresh()->gl_posted)->toBeFalse()
        ->and(GlEntry::query()->where('document_number', 'PR-TEST')->count())->toBe(0);
});

it('finance reconcile reports source and value entry duplicate inventory value postings', function (): void {
    $fixture = valueEntryAccountingFixture();
    $itemLedgerEntry = valueEntryAccountingItemLedgerEntry($fixture, ItemLedgerEntryType::SALE, -5, 125, 'SALES_ORDER_SHIPMENT');
    $valueEntry = ValueEntry::query()->where('item_ledger_entry_no', $itemLedgerEntry->entry_number)->firstOrFail();

    app(ValueEntryAccountingOrchestrator::class)->post($valueEntry);

    app(GeneralLedgerService::class)->postTransaction([
        [
            'account_id' => $fixture['cogsAccount']->id,
            'debit' => 125,
            'credit' => 0,
            'item_ledger_entry_id' => $itemLedgerEntry->id,
        ],
        [
            'account_id' => $fixture['inventoryAccount']->id,
            'debit' => 0,
            'credit' => 125,
            'item_ledger_entry_id' => $itemLedgerEntry->id,
        ],
    ], [
        'posting_date' => '2026-07-26',
        'document_type' => 'SALES_ORDER_SHIPMENT',
        'document_number' => 'SS-TEST',
        'source_module' => 'sales',
        'source_type' => 'ITEM',
        'source_number' => 'FG-TEST',
        'description' => 'Legacy duplicate source posting',
        'idempotency_key' => 'legacy-duplicate-source-posting',
    ]);

    $this->artisan('biwms:finance-reconcile', ['--json' => true])
        ->expectsOutputToContain('source_and_value_entry_duplicate_inventory_value')
        ->assertSuccessful();
});

it('posts expected cost when the explicit configuration flag is enabled', function (): void {
    config(['accounts.post_expected_inventory_cost_to_gl' => true]);

    $fixture = valueEntryAccountingFixture();
    $itemLedgerEntry = valueEntryAccountingItemLedgerEntry($fixture, ItemLedgerEntryType::PURCHASE, 5, 0, 'PURCHASE_RECEIPT', 125);
    $valueEntry = ValueEntry::query()->where('item_ledger_entry_no', $itemLedgerEntry->entry_number)->firstOrFail();
    $valueEntry->forceFill([
        'expected_cost' => true,
        'value_entry_state' => 'expected',
    ])->save();

    $transaction = app(ValueEntryAccountingOrchestrator::class)->post($valueEntry);

    expect($transaction)->toBeInstanceOf(PostingTransaction::class)
        ->and((float) GlEntry::query()->where('posting_transaction_id', $transaction->id)->sum('debit_amount'))->toBe(125.0)
        ->and((float) GlEntry::query()->where('posting_transaction_id', $transaction->id)->sum('credit_amount'))->toBe(125.0)
        ->and($valueEntry->fresh()->gl_posted)->toBeTrue();
});

/**
 * @return array<string, mixed>
 */
function valueEntryAccountingFixture(): array
{
    $inventoryAccount = valueEntryAccountingAccount('13000', 'Inventory', AccountCategory::INVENTORY);
    $cogsAccount = valueEntryAccountingAccount('50000', 'COGS', AccountCategory::COGS);
    $purchaseAccount = valueEntryAccountingAccount('21000', 'Purchase Clearing', AccountCategory::LIABILITY);
    $adjustmentAccount = valueEntryAccountingAccount('51000', 'Inventory Adjustment', AccountCategory::DIRECT_EXPENSE);
    $wipAccount = valueEntryAccountingAccount('13500', 'WIP', AccountCategory::INVENTORY);

    $generalBusinessPostingGroup = GeneralBusinessPostingGroup::factory()->create(['code' => 'DOMESTIC']);
    $generalProductPostingGroup = GeneralProductPostingGroup::query()->create([
        'code' => 'FINISHED',
        'description' => 'Finished Goods',
        'blocked' => false,
        'auto_create_vat_prod_posting_group' => false,
    ]);
    $inventoryPostingGroup = InventoryPostingGroup::query()->create([
        'code' => 'FINISHED',
        'description' => 'Finished Goods',
        'blocked' => false,
    ]);
    $location = Location::factory()->create(['code' => 'MAIN']);

    InventoryPostingSetup::query()->create([
        'location_id' => $location->id,
        'inventory_posting_group_id' => $inventoryPostingGroup->id,
        'inventory_account_id' => $inventoryAccount->id,
        'inventory_account_interim_id' => $inventoryAccount->id,
        'wip_account_id' => $wipAccount->id,
    ]);

    GeneralPostingSetup::query()->create([
        'general_business_posting_group_id' => $generalBusinessPostingGroup->id,
        'general_product_posting_group_id' => $generalProductPostingGroup->id,
        'sales_account_id' => valueEntryAccountingAccount('40000', 'Sales', AccountCategory::REVENUE)->id,
        'cogs_account_id' => $cogsAccount->id,
        'inventory_adj_account_id' => $adjustmentAccount->id,
        'inventory_account_id' => $inventoryAccount->id,
        'purchase_account_id' => $purchaseAccount->id,
        'direct_cost_applied_account_id' => valueEntryAccountingAccount('52000', 'Direct Cost Applied', AccountCategory::DIRECT_EXPENSE)->id,
        'overhead_applied_account_id' => valueEntryAccountingAccount('53000', 'Overhead Applied', AccountCategory::DIRECT_EXPENSE)->id,
        'blocked' => false,
    ]);

    $item = Item::factory()->create([
        'item_code' => 'FG-TEST',
        'description' => 'Finished Test Item',
        'item_type' => ItemType::RAW_MATERIAL,
        'unit_cost' => 25,
        'general_product_posting_group_id' => $generalProductPostingGroup->id,
        'inventory_posting_group_id' => $inventoryPostingGroup->id,
        'location_id' => $location->id,
    ]);

    return compact(
        'inventoryAccount',
        'cogsAccount',
        'purchaseAccount',
        'generalBusinessPostingGroup',
        'generalProductPostingGroup',
        'inventoryPostingGroup',
        'location',
        'item'
    );
}

function valueEntryAccountingAccount(string $number, string $name, AccountCategory $category): ChartOfAccount
{
    return ChartOfAccount::query()->firstOrCreate(
        ['account_number' => $number],
        [
            'name' => $name,
            'structural_type' => AccountStructuralType::POSTING,
            'account_category' => $category,
            'balance' => 0,
            'direct_posting' => true,
            'blocked' => false,
            'income_balance' => $category->isBalanceSheet()
                ? IncomeBalanceType::BALANCE_SHEET
                : IncomeBalanceType::INCOME_STATEMENT,
        ],
    );
}

/**
 * @param  array<string, mixed>  $fixture
 */
function valueEntryAccountingItemLedgerEntry(
    array $fixture,
    ItemLedgerEntryType $entryType,
    float $quantity,
    float $actualCost,
    string $documentType,
    float $expectedCost = 0,
): ItemLedgerEntry {
    return ItemLedgerEntry::query()->create([
        'entry_type' => $entryType,
        'document_type' => $documentType,
        'document_number' => match ($documentType) {
            'SALES_ORDER_SHIPMENT' => 'SS-TEST',
            'PURCHASE_RECEIPT' => 'PR-TEST',
            default => 'VE-TEST',
        },
        'document_line_number' => 10000,
        'item_id' => $fixture['item']->id,
        'location_id' => $fixture['location']->id,
        'quantity' => $quantity,
        'remaining_quantity' => $quantity,
        'cost_amount_actual' => $actualCost,
        'cost_amount_expected' => $expectedCost,
        'purchase_amount_actual' => $actualCost,
        'general_business_posting_group_id' => $fixture['generalBusinessPostingGroup']->id,
        'general_product_posting_group_id' => $fixture['generalProductPostingGroup']->id,
        'inventory_posting_group_id' => $fixture['inventoryPostingGroup']->id,
        'posting_date' => '2026-07-26',
        'entry_date' => now(),
        'open' => false,
    ]);
}
