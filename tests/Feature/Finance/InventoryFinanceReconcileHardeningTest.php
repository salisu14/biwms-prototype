<?php

declare(strict_types=1);

use App\Enums\AccountCategory;
use App\Enums\AccountStructuralType;
use App\Enums\IncomeBalanceType;
use App\Enums\ItemLedgerEntryType;
use App\Enums\ItemType;
use App\Models\ChartOfAccount;
use App\Models\GeneralBusinessPostingGroup;
use App\Models\GeneralProductPostingGroup;
use App\Models\GlEntry;
use App\Models\InventoryPostingGroup;
use App\Models\InventoryPostingSetup;
use App\Models\Item;
use App\Models\ItemLedgerEntry;
use App\Models\Location;
use App\Models\PostingTransaction;
use App\Models\ValueEntry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;

uses(RefreshDatabase::class);

it('does not report missing inventory control when value entry and gl share posting transaction despite truncated document numbers', function (): void {
    $fixture = financeInventoryFixture();
    $itemLedgerEntry = financeInventoryItemLedgerEntry($fixture, ItemLedgerEntryType::POSITIVE_ADJUSTMENT, 1, 29976.3702);
    $transaction = financeInventoryPostingTransaction('INVENTORY_REVALUATION', 'COSTADJ-fc50ef4d', 'value-entry:71');
    financeInventoryValueEntry($itemLedgerEntry, [
        'entry_no' => 71,
        'document_type' => 'INVENTORY_REVALUATION',
        'document_no' => 'COSTADJ-fc50ef4d036b303ef74ca848',
        'cost_amount_actual' => 29976.3702,
        'posting_transaction_id' => $transaction->id,
        'gl_posted' => true,
        'value_entry_state' => 'adjustment',
    ]);
    financeInventoryGlLines($transaction, $fixture['inventory'], $fixture['wip'], 29976.37, $itemLedgerEntry, 'COSTADJ-fc50ef4d');

    $report = financeInventoryReconcileReport();

    expect(financeInventoryFindingsForValueEntry($report, 'missing_control_account_entries', 71))->toBeEmpty();
});

it('still reports a modern value entry marked gl posted without a control gl line', function (): void {
    $fixture = financeInventoryFixture();
    $itemLedgerEntry = financeInventoryItemLedgerEntry($fixture, ItemLedgerEntryType::POSITIVE_ADJUSTMENT, 1, 100);
    $transaction = financeInventoryPostingTransaction('INVENTORY_REVALUATION', 'COSTADJ-MISS', 'value-entry:501');

    financeInventoryValueEntry($itemLedgerEntry, [
        'entry_no' => 501,
        'document_type' => 'INVENTORY_REVALUATION',
        'document_no' => 'COSTADJ-MISS-LONG',
        'cost_amount_actual' => 100,
        'posting_transaction_id' => $transaction->id,
        'gl_posted' => true,
        'value_entry_state' => 'adjustment',
    ]);

    $report = financeInventoryReconcileReport();

    $findings = financeInventoryFindingsForValueEntry($report, 'missing_control_account_entries', 501);

    expect($findings)->toHaveCount(1)
        ->and($findings[0]['value_entry_no'])->toBe(501)
        ->and($findings[0]['posting_transaction_id'])->toBe($transaction->id);
});

it('classifies a zero-value legacy inventory control fallback as informational', function (): void {
    $fixture = financeInventoryFixture();
    $itemLedgerEntry = financeInventoryItemLedgerEntry($fixture, ItemLedgerEntryType::PURCHASE, 1, 0);
    financeInventoryValueEntry($itemLedgerEntry, [
        'entry_no' => 901,
        'document_type' => 'INVENTORY_PURCHASE_RECEIPT',
        'document_no' => 'PO-LEGACY-ZERO',
        'cost_amount_actual' => 0,
        'posting_transaction_id' => null,
        'gl_posted' => false,
    ]);

    $report = financeInventoryReconcileReport();
    $finding = collect($report['missing_control_account_entries'])
        ->firstWhere('value_entry_no', 901);

    expect($finding)->not->toBeNull()
        ->and($finding['account_number'])->toBe('LEGACY_INVENTORY_CONTROL_TOTAL')
        ->and($finding['amount'])->toBe(0)
        ->and($finding['severity'])->toBe('info');
});

it('allows append-only value entry history for the same item ledger entry without duplicate-source findings', function (): void {
    $fixture = financeInventoryFixture();
    $itemLedgerEntry = financeInventoryItemLedgerEntry($fixture, ItemLedgerEntryType::CONSUMPTION, -17.0657, 13285.6692);

    foreach ([
        [55, 'PRODUCTION_CONSUMPTION', 'CONS-001', 13285.6692, 'actual'],
        [70, 'COST_ADJUSTMENT', 'COSTADJ-001', 7.8766, 'adjustment'],
        [72, 'COST_ADJUSTMENT', 'COSTADJ-REV', -7.8766, 'reversal'],
    ] as [$entryNo, $documentType, $documentNo, $amount, $state]) {
        $transaction = financeInventoryPostingTransaction($documentType, $documentNo, "value-entry:{$entryNo}");
        financeInventoryValueEntry($itemLedgerEntry, [
            'entry_no' => $entryNo,
            'document_type' => $documentType,
            'document_no' => $documentNo,
            'cost_amount_actual' => $amount,
            'posting_transaction_id' => $transaction->id,
            'gl_posted' => true,
            'value_entry_state' => $state,
        ]);
        $amount >= 0
            ? financeInventoryGlLines($transaction, $fixture['wip'], $fixture['inventory'], abs((float) $amount), $itemLedgerEntry, $documentNo)
            : financeInventoryGlLines($transaction, $fixture['inventory'], $fixture['wip'], abs((float) $amount), $itemLedgerEntry, $documentNo);
    }

    $report = financeInventoryReconcileReport();

    expect($report['source_and_value_entry_duplicate_inventory_value'])->toBeEmpty()
        ->and(financeInventoryFindingsForValueEntries($report, 'missing_control_account_entries', [55, 70, 72]))->toBeEmpty();
});

it('reports a genuine duplicate economic posting transaction for the same value entry key', function (): void {
    $fixture = financeInventoryFixture();
    $itemLedgerEntry = financeInventoryItemLedgerEntry($fixture, ItemLedgerEntryType::POSITIVE_ADJUSTMENT, 1, 100);
    $owner = financeInventoryPostingTransaction('INVENTORY_REVALUATION', 'COSTADJ-DUP-A', 'value-entry:777');
    $duplicate = financeInventoryPostingTransaction('INVENTORY_REVALUATION', 'COSTADJ-DUP-B', 'value-entry:777', 'dup-idempotency-777');

    financeInventoryValueEntry($itemLedgerEntry, [
        'entry_no' => 777,
        'document_type' => 'INVENTORY_REVALUATION',
        'document_no' => 'COSTADJ-DUP-A',
        'cost_amount_actual' => 100,
        'posting_transaction_id' => $owner->id,
        'gl_posted' => true,
        'value_entry_state' => 'adjustment',
    ]);
    financeInventoryGlLines($owner, $fixture['inventory'], $fixture['wip'], 100, $itemLedgerEntry, 'COSTADJ-DUP-A');
    financeInventoryGlLines($duplicate, $fixture['inventory'], $fixture['wip'], 100, $itemLedgerEntry, 'COSTADJ-DUP-B');

    $report = financeInventoryReconcileReport();

    expect($report['source_and_value_entry_duplicate_inventory_value'])->toHaveCount(1)
        ->and($report['source_and_value_entry_duplicate_inventory_value'][0]['value_entry_no'])->toBe(777);
});

it('keeps a sodium-saccharine style revaluation and adjustment chain clean', function (): void {
    $fixture = financeInventoryFixture();
    $opening = financeInventoryItemLedgerEntry($fixture, ItemLedgerEntryType::PURCHASE, 25000, 220000);
    $remaining = financeInventoryItemLedgerEntry($fixture, ItemLedgerEntryType::POSITIVE_ADJUSTMENT, 24997.2987, 29976.3702);

    foreach ([
        [$opening, 1001, 'OPENING_INVENTORY', 'OI-SODIUM', 220000.0000, $fixture['inventory'], $fixture['offset'], 'actual'],
        [$opening, 1015, 'COST_ADJUSTMENT', 'COSTADJ-CONS-1', 7.8766, $fixture['wip'], $fixture['inventory'], 'adjustment'],
        [$opening, 1031, 'COST_ADJUSTMENT', 'COSTADJ-CONS-2', 7.8766, $fixture['wip'], $fixture['inventory'], 'adjustment'],
        [$opening, 1055, 'COST_ADJUSTMENT', 'COSTADJ-CONS-3', 7.8766, $fixture['wip'], $fixture['inventory'], 'adjustment'],
        [$remaining, 1071, 'INVENTORY_REVALUATION', 'COSTADJ-fc50ef4d036b303ef74ca848', 29976.3702, $fixture['inventory'], $fixture['wip'], 'adjustment'],
        [$opening, 1072, 'COST_ADJUSTMENT', 'COSTADJ-REV-CONS-1', -7.8766, $fixture['inventory'], $fixture['wip'], 'reversal'],
    ] as [$ile, $entryNo, $documentType, $documentNo, $amount, $debitAccount, $creditAccount, $state]) {
        $transaction = financeInventoryPostingTransaction($documentType, substr((string) $documentNo, 0, 18), "value-entry:{$entryNo}");
        financeInventoryValueEntry($ile, [
            'entry_no' => $entryNo,
            'document_type' => $documentType,
            'document_no' => $documentNo,
            'cost_amount_actual' => $amount,
            'posting_transaction_id' => $transaction->id,
            'gl_posted' => true,
            'value_entry_state' => $state,
        ]);
        financeInventoryGlLines($transaction, $debitAccount, $creditAccount, abs((float) $amount), $ile, substr((string) $documentNo, 0, 18));
    }

    $report = financeInventoryReconcileReport();

    expect(financeInventoryFindingsForValueEntries($report, 'missing_control_account_entries', [1001, 1015, 1031, 1055, 1071, 1072]))->toBeEmpty()
        ->and($report['source_and_value_entry_duplicate_inventory_value'])->toBeEmpty()
        ->and(financeInventoryFindingsForValueEntries($report, 'duplicate_gl_posting_for_value_entry', [1001, 1015, 1031, 1055, 1071, 1072]))->toBeEmpty();
});

function financeInventoryReconcileReport(): array
{
    expect(Artisan::call('biwms:finance-reconcile', ['--json' => true]))->toBe(0);

    return json_decode(trim(Artisan::output()), true);
}

/**
 * @return array<int, array<string, mixed>>
 */
function financeInventoryFindingsForValueEntry(array $report, string $section, int $entryNo): array
{
    return financeInventoryFindingsForValueEntries($report, $section, [$entryNo]);
}

/**
 * @param  array<int, int>  $entryNos
 * @return array<int, array<string, mixed>>
 */
function financeInventoryFindingsForValueEntries(array $report, string $section, array $entryNos): array
{
    return collect($report[$section] ?? [])
        ->filter(fn (array $finding): bool => in_array((int) ($finding['value_entry_no'] ?? 0), $entryNos, true))
        ->values()
        ->all();
}

function financeInventoryFixture(): array
{
    $inventory = financeInventoryAccount('13110', 'Raw Materials - Warehouse', AccountCategory::INVENTORY);
    $wip = financeInventoryAccount('13300', 'Work in Process', AccountCategory::INVENTORY);
    $offset = financeInventoryAccount('59990', 'Inventory Offset', AccountCategory::OPERATING_EXPENSE);
    $inventoryPostingGroup = InventoryPostingGroup::query()->firstOrCreate(['code' => 'SODIUM'], ['description' => 'Sodium Saccharine', 'blocked' => false]);
    $generalBusinessPostingGroup = GeneralBusinessPostingGroup::query()->firstOrCreate(['code' => 'DOMESTIC'], ['description' => 'Domestic', 'blocked' => false]);
    $generalProductPostingGroup = GeneralProductPostingGroup::query()->firstOrCreate(['code' => 'RAW'], ['description' => 'Raw', 'blocked' => false]);
    $location = Location::factory()->create();
    $item = Item::factory()->create([
        'item_code' => 'SODIUM-SAC',
        'description' => 'Sodium Saccharine',
        'item_type' => ItemType::RAW_MATERIAL,
        'inventory_posting_group_id' => $inventoryPostingGroup->id,
        'general_product_posting_group_id' => $generalProductPostingGroup->id,
    ]);

    InventoryPostingSetup::query()->create([
        'inventory_posting_group_id' => $inventoryPostingGroup->id,
        'location_id' => $location->id,
        'inventory_account_id' => $inventory->id,
        'wip_account_id' => $wip->id,
    ]);

    return compact('inventory', 'wip', 'offset', 'inventoryPostingGroup', 'generalBusinessPostingGroup', 'generalProductPostingGroup', 'location', 'item');
}

function financeInventoryItemLedgerEntry(array $fixture, ItemLedgerEntryType $type, float $quantity, float $cost): ItemLedgerEntry
{
    return ItemLedgerEntry::query()->create([
        'entry_number' => ((int) ItemLedgerEntry::query()->max('entry_number')) + 1,
        'entry_type' => $type,
        'document_type' => 'TEST',
        'document_number' => 'ILE-'.(((int) ItemLedgerEntry::query()->max('entry_number')) + 1),
        'document_line_number' => 1,
        'item_id' => $fixture['item']->id,
        'location_id' => $fixture['location']->id,
        'quantity' => $quantity,
        'remaining_quantity' => $quantity,
        'cost_amount_actual' => $cost,
        'general_business_posting_group_id' => $fixture['generalBusinessPostingGroup']->id,
        'general_product_posting_group_id' => $fixture['generalProductPostingGroup']->id,
        'inventory_posting_group_id' => $fixture['inventoryPostingGroup']->id,
        'posting_date' => '2026-08-14',
        'entry_date' => now(),
        'open' => true,
    ]);
}

function financeInventoryValueEntry(ItemLedgerEntry $itemLedgerEntry, array $overrides = []): ValueEntry
{
    return ValueEntry::query()->create(array_replace([
        'entry_no' => ((int) ValueEntry::query()->max('entry_no')) + 1,
        'item_ledger_entry_no' => $itemLedgerEntry->entry_number,
        'item_ledger_entry_type' => financeInventoryEntryTypeCode($itemLedgerEntry->entry_type),
        'item_no' => $itemLedgerEntry->item->item_code,
        'location_code' => $itemLedgerEntry->location->code,
        'posting_date' => '2026-08-14',
        'document_type' => 'TEST',
        'document_no' => 'VE-TEST',
        'quantity' => $itemLedgerEntry->quantity,
        'cost_amount_actual' => $itemLedgerEntry->cost_amount_actual,
        'expected_cost' => false,
        'gl_posted' => true,
        'source_module' => 'inventory',
        'value_entry_state' => 'actual',
    ], $overrides));
}

function financeInventoryPostingTransaction(string $documentType, string $documentNumber, string $transactionKey, ?string $idempotencyKey = null): PostingTransaction
{
    return PostingTransaction::query()->create([
        'source_module' => 'inventory',
        'source_type' => 'ITEM',
        'source_number' => $documentNumber,
        'document_type' => $documentType,
        'document_number' => $documentNumber,
        'transaction_key' => $transactionKey,
        'idempotency_key' => $idempotencyKey ?? hash('sha256', $transactionKey.'|'.$documentNumber),
        'transaction_number' => ((int) PostingTransaction::query()->max('transaction_number')) + 1,
        'posting_date' => '2026-08-14',
        'document_date' => '2026-08-14',
        'status' => 'completed',
    ]);
}

function financeInventoryGlLines(PostingTransaction $transaction, ChartOfAccount $debitAccount, ChartOfAccount $creditAccount, float $amount, ItemLedgerEntry $itemLedgerEntry, string $documentNumber): void
{
    financeInventoryGlEntry($transaction, $debitAccount, $amount, 0, $itemLedgerEntry, $documentNumber);
    financeInventoryGlEntry($transaction, $creditAccount, 0, $amount, $itemLedgerEntry, $documentNumber);
}

function financeInventoryGlEntry(PostingTransaction $transaction, ChartOfAccount $account, float $debit, float $credit, ItemLedgerEntry $itemLedgerEntry, string $documentNumber): GlEntry
{
    return GlEntry::query()->create([
        'entry_number' => ((int) GlEntry::query()->max('entry_number')) + 1,
        'transaction_number' => $transaction->transaction_number,
        'posting_transaction_id' => $transaction->id,
        'chart_of_account_id' => $account->id,
        'debit_amount' => $debit,
        'credit_amount' => $credit,
        'amount' => $debit - $credit,
        'source_type' => 'ITEM',
        'source_number' => 'SODIUM-SAC',
        'document_type' => $transaction->document_type,
        'document_number' => $documentNumber,
        'document_date' => '2026-08-14',
        'posting_date' => '2026-08-14',
        'description' => 'Finance inventory reconcile hardening',
        'item_ledger_entry_id' => $itemLedgerEntry->id,
        'transaction_key' => $transaction->transaction_key,
        'idempotency_key' => $transaction->idempotency_key,
    ]);
}

function financeInventoryAccount(string $number, string $name, AccountCategory $category): ChartOfAccount
{
    return ChartOfAccount::query()->create([
        'account_number' => $number,
        'name' => $name,
        'structural_type' => AccountStructuralType::POSTING,
        'account_category' => $category,
        'income_balance' => $category->isIncomeStatement() ? IncomeBalanceType::INCOME_STATEMENT : IncomeBalanceType::BALANCE_SHEET,
        'direct_posting' => true,
        'blocked' => false,
    ]);
}

function financeInventoryEntryTypeCode(ItemLedgerEntryType $type): int
{
    return match ($type) {
        ItemLedgerEntryType::PURCHASE => 1,
        ItemLedgerEntryType::SALE => 2,
        ItemLedgerEntryType::POSITIVE_ADJUSTMENT => 3,
        ItemLedgerEntryType::NEGATIVE_ADJUSTMENT => 4,
        ItemLedgerEntryType::TRANSFER => 5,
        ItemLedgerEntryType::CONSUMPTION => 6,
        ItemLedgerEntryType::OUTPUT => 7,
        ItemLedgerEntryType::CAPACITY => 8,
        ItemLedgerEntryType::OVERHEAD => 10,
        default => 3,
    };
}
