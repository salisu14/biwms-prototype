<?php

use App\Enums\ApprovalStatus;
use App\Enums\IncomeBalanceType;
use App\Enums\ItemLedgerEntryType;
use App\Enums\ItemType;
use App\Models\ChartOfAccount;
use App\Models\GeneralBusinessPostingGroup;
use App\Models\GeneralPostingSetup;
use App\Models\GeneralProductPostingGroup;
use App\Models\GlEntry;
use App\Models\InventoryPostingGroup;
use App\Models\InventoryPostingSetup;
use App\Models\Item;
use App\Models\ItemLedgerEntry;
use App\Models\ItemUomAssignment;
use App\Models\Location;
use App\Models\Permission;
use App\Models\PostedPurchaseCreditMemo;
use App\Models\PostedPurchaseInvoice;
use App\Models\PurchaseCreditMemo;
use App\Models\PurchaseInvoice;
use App\Models\UnitOfMeasure;
use App\Models\User;
use App\Models\ValueEntry;
use App\Models\Vendor;
use App\Models\VendorLedgerEntry;
use App\Models\VendorPostingGroup;
use App\Services\Purchase\PurchaseInvoiceService;
use App\Services\Purchases\PurchaseCreditMemoService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('purchase invoice posting creates traceable item, value, vendor, and balanced gl entries using base quantity', function () {
    $fixture = purchasePostingFixture();
    $this->actingAs($fixture['user']);

    $invoice = PurchaseInvoice::query()->create([
        'document_number' => 'PI-TRACE-001',
        'vendor_id' => $fixture['vendor']->id,
        'vendor_name' => $fixture['vendor']->vendor_name,
        'general_business_posting_group_id' => $fixture['vendor']->general_business_posting_group_id,
        'vendor_posting_group_id' => $fixture['vendor']->vendor_posting_group_id,
        'location_id' => $fixture['location']->id,
        'posting_date' => now()->toDateString(),
        'document_date' => now()->toDateString(),
        'due_date' => now()->addDays(7)->toDateString(),
        'status' => ApprovalStatus::APPROVED,
        'total_amount' => 1000,
        'total_vat' => 0,
        'grand_total' => 1000,
        'remaining_amount' => 1000,
        'currency_code' => 'NGN',
        'currency_factor' => 1,
        'approved_by' => $fixture['user']->id,
        'approved_at' => now(),
        'cancelled' => false,
    ]);

    $invoice->lines()->create([
        'line_number' => 10000,
        'item_id' => $fixture['item']->id,
        'item_code' => $fixture['item']->item_code,
        'item_description' => $fixture['item']->description,
        'general_product_posting_group_id' => $fixture['item']->general_product_posting_group_id,
        'inventory_posting_group_id' => $fixture['item']->inventory_posting_group_id,
        'quantity' => 1,
        'unit_of_measure_code' => 'CT',
        'qty_per_unit_of_measure' => 288,
        'quantity_base' => 288,
        'unit_cost' => 1000,
        'unit_cost_lcy' => 1000,
        'line_total' => 1000,
        'vat_percentage' => 0,
        'vat_amount' => 0,
        'vat_amount_lcy' => 0,
        'amount_including_vat' => 1000,
        'amount_including_vat_lcy' => 1000,
        'posting_date' => $invoice->posting_date,
    ]);

    $postedInvoice = app(PurchaseInvoiceService::class)->post($invoice);

    $invoice->refresh();
    $itemLedgerEntry = ItemLedgerEntry::query()
        ->where('document_type', 'PURCHASE_INVOICE')
        ->where('document_number', 'PI-TRACE-001')
        ->firstOrFail();

    expect($postedInvoice)->toBeInstanceOf(PostedPurchaseInvoice::class)
        ->and($invoice->status)->toBe(ApprovalStatus::POSTED)
        ->and((float) $itemLedgerEntry->quantity)->toBe(288.0)
        ->and((float) $itemLedgerEntry->remaining_quantity)->toBe(288.0)
        ->and($itemLedgerEntry->entry_type)->toBe(ItemLedgerEntryType::PURCHASE)
        ->and((float) $itemLedgerEntry->cost_amount_actual)->toBe(1000.0)
        ->and((float) $fixture['item']->fresh()->inventory)->toBe(288.0);

    expect(ValueEntry::query()
        ->where('item_ledger_entry_no', $itemLedgerEntry->entry_number)
        ->where('document_no', 'PI-TRACE-001')
        ->where('quantity', 288)
        ->where('cost_amount_actual', 1000)
        ->exists())->toBeTrue();

    expect(VendorLedgerEntry::query()
        ->where('document_type', 'PURCHASE_INVOICE')
        ->where('document_number', 'PI-TRACE-001')
        ->where('vendor_id', $fixture['vendor']->id)
        ->exists())->toBeTrue();

    $postedLine = $postedInvoice->lines()->firstOrFail();
    expect((float) $postedLine->quantity)->toBe(1.0)
        ->and((float) $postedLine->quantity_base)->toBe(288.0)
        ->and($postedLine->item_ledger_entry_id)->toBe($itemLedgerEntry->id);

    $glEntries = GlEntry::query()->where('document_number', 'PI-TRACE-001')->get();
    expect(round((float) $glEntries->sum('debit_amount'), 2))
        ->toBe(round((float) $glEntries->sum('credit_amount'), 2));

    $this->expectExceptionMessage('Purchase invoice is already posted.');
    app(PurchaseInvoiceService::class)->post($invoice->fresh());
});

test('purchase invoice posting rejects missing exact posting setup and rolls back ledger creation', function () {
    $fixture = purchasePostingFixture(createGeneralPostingSetup: false);
    $this->actingAs($fixture['user']);

    $invoice = PurchaseInvoice::query()->create([
        'document_number' => 'PI-MISSING-SETUP',
        'vendor_id' => $fixture['vendor']->id,
        'vendor_name' => $fixture['vendor']->vendor_name,
        'general_business_posting_group_id' => $fixture['vendor']->general_business_posting_group_id,
        'vendor_posting_group_id' => $fixture['vendor']->vendor_posting_group_id,
        'location_id' => $fixture['location']->id,
        'posting_date' => now()->toDateString(),
        'document_date' => now()->toDateString(),
        'due_date' => now()->addDays(7)->toDateString(),
        'status' => ApprovalStatus::APPROVED,
        'total_amount' => 1000,
        'total_vat' => 0,
        'grand_total' => 1000,
        'remaining_amount' => 1000,
        'currency_code' => 'NGN',
        'currency_factor' => 1,
        'approved_by' => $fixture['user']->id,
        'approved_at' => now(),
        'cancelled' => false,
    ]);

    $invoice->lines()->create([
        'line_number' => 10000,
        'item_id' => $fixture['item']->id,
        'item_code' => $fixture['item']->item_code,
        'item_description' => $fixture['item']->description,
        'general_product_posting_group_id' => $fixture['item']->general_product_posting_group_id,
        'inventory_posting_group_id' => $fixture['item']->inventory_posting_group_id,
        'quantity' => 1,
        'unit_of_measure_code' => 'CT',
        'qty_per_unit_of_measure' => 288,
        'quantity_base' => 288,
        'unit_cost' => 1000,
        'unit_cost_lcy' => 1000,
        'line_total' => 1000,
        'vat_percentage' => 0,
        'vat_amount' => 0,
        'vat_amount_lcy' => 0,
        'amount_including_vat' => 1000,
        'amount_including_vat_lcy' => 1000,
        'posting_date' => $invoice->posting_date,
    ]);

    expect(fn () => app(PurchaseInvoiceService::class)->post($invoice))
        ->toThrow(Exception::class, 'Posting setup missing');

    expect($invoice->fresh()->status)->toBe(ApprovalStatus::APPROVED)
        ->and(ItemLedgerEntry::query()->where('document_number', 'PI-MISSING-SETUP')->exists())->toBeFalse()
        ->and(ValueEntry::query()->where('document_no', 'PI-MISSING-SETUP')->exists())->toBeFalse()
        ->and(GlEntry::query()->where('document_number', 'PI-MISSING-SETUP')->exists())->toBeFalse()
        ->and(VendorLedgerEntry::query()->where('document_number', 'PI-MISSING-SETUP')->exists())->toBeFalse();
});

test('purchase credit memo reverses inventory, value, vendor, and gl entries using base quantity', function () {
    $fixture = purchasePostingFixture();
    grantPurchaseCreditMemoPostPermission($fixture['user']);
    $this->actingAs($fixture['user']);
    $fixture['item']->forceFill(['inventory' => 288])->save();

    $memo = PurchaseCreditMemo::query()->create([
        'document_number' => 'PCM-TRACE-001',
        'vendor_id' => $fixture['vendor']->id,
        'vendor_name' => $fixture['vendor']->vendor_name,
        'posting_date' => now()->toDateString(),
        'document_date' => now()->toDateString(),
        'location_id' => $fixture['location']->id,
        'status' => ApprovalStatus::APPROVED,
        'currency_code' => 'NGN',
        'description' => 'Return to vendor',
    ]);

    $memo->lines()->create([
        'line_number' => 10000,
        'item_id' => $fixture['item']->id,
        'item_code' => $fixture['item']->item_code,
        'description' => $fixture['item']->description,
        'quantity' => 1,
        'unit_cost' => 1000,
        'tax_percent' => 0,
        'general_product_posting_group_id' => $fixture['item']->general_product_posting_group_id,
        'unit_of_measure_code' => 'CT',
    ]);

    $postedMemo = app(PurchaseCreditMemoService::class)->post($memo);

    $itemLedgerEntry = ItemLedgerEntry::query()
        ->where('document_type', 'PURCHASE_CREDIT_MEMO')
        ->where('document_number', 'PCM-TRACE-001')
        ->firstOrFail();

    expect($postedMemo)->toBeInstanceOf(PostedPurchaseCreditMemo::class)
        ->and($memo->fresh()->status)->toBe(ApprovalStatus::POSTED)
        ->and((float) $itemLedgerEntry->quantity)->toBe(-288.0)
        ->and($itemLedgerEntry->entry_type)->toBe(ItemLedgerEntryType::PURCHASE)
        ->and((float) $fixture['item']->fresh()->inventory)->toBe(0.0);

    expect(ValueEntry::query()
        ->where('item_ledger_entry_no', $itemLedgerEntry->entry_number)
        ->where('document_no', 'PCM-TRACE-001')
        ->where('quantity', -288)
        ->exists())->toBeTrue();

    expect(VendorLedgerEntry::query()
        ->where('document_type', 'PURCHASE_CREDIT_MEMO')
        ->where('document_number', 'PCM-TRACE-001')
        ->where('vendor_id', $fixture['vendor']->id)
        ->exists())->toBeTrue();

    $glEntries = GlEntry::query()->where('document_number', 'PCM-TRACE-001')->get();
    expect(round((float) $glEntries->sum('debit_amount'), 2))
        ->toBe(round((float) $glEntries->sum('credit_amount'), 2));

    expect(fn () => app(PurchaseCreditMemoService::class)->post($memo->fresh()))
        ->toThrow(Exception::class, 'Purchase credit memo is already posted.');
});

test('purchase credit memo posting requires permission and rolls back on missing setup', function () {
    $fixture = purchasePostingFixture(createGeneralPostingSetup: false);
    $this->actingAs($fixture['user']);
    $fixture['item']->forceFill(['inventory' => 288])->save();

    $memo = PurchaseCreditMemo::query()->create([
        'document_number' => 'PCM-MISSING-SETUP',
        'vendor_id' => $fixture['vendor']->id,
        'vendor_name' => $fixture['vendor']->vendor_name,
        'posting_date' => now()->toDateString(),
        'document_date' => now()->toDateString(),
        'location_id' => $fixture['location']->id,
        'status' => ApprovalStatus::APPROVED,
        'currency_code' => 'NGN',
        'description' => 'Return to vendor',
    ]);

    $memo->lines()->create([
        'line_number' => 10000,
        'item_id' => $fixture['item']->id,
        'item_code' => $fixture['item']->item_code,
        'description' => $fixture['item']->description,
        'quantity' => 1,
        'unit_cost' => 1000,
        'tax_percent' => 0,
        'general_product_posting_group_id' => $fixture['item']->general_product_posting_group_id,
        'unit_of_measure_code' => 'CT',
    ]);

    expect(fn () => app(PurchaseCreditMemoService::class)->post($memo))
        ->toThrow(AuthorizationException::class);

    grantPurchaseCreditMemoPostPermission($fixture['user']);

    expect(fn () => app(PurchaseCreditMemoService::class)->post($memo->fresh()))
        ->toThrow(Exception::class, 'Posting setup missing');

    expect($memo->fresh()->status)->toBe(ApprovalStatus::APPROVED)
        ->and(ItemLedgerEntry::query()->where('document_number', 'PCM-MISSING-SETUP')->exists())->toBeFalse()
        ->and(ValueEntry::query()->where('document_no', 'PCM-MISSING-SETUP')->exists())->toBeFalse()
        ->and(GlEntry::query()->where('document_number', 'PCM-MISSING-SETUP')->exists())->toBeFalse()
        ->and(VendorLedgerEntry::query()->where('document_number', 'PCM-MISSING-SETUP')->exists())->toBeFalse();
});

/**
 * @return array{user: User, vendor: Vendor, item: Item, location: Location}
 */
function purchasePostingFixture(bool $createGeneralPostingSetup = true): array
{
    $user = User::factory()->create();
    $location = Location::factory()->create(['code' => 'MAIN']);

    $payablesAccount = purchasePostingTestAccount('2100', 'Accounts Payable', 'payable', IncomeBalanceType::BALANCE_SHEET);
    $inventoryAccount = purchasePostingTestAccount('1200', 'Inventory', 'inventory', IncomeBalanceType::BALANCE_SHEET);
    $purchaseAccount = purchasePostingTestAccount('5100', 'Purchases', 'direct_expense', IncomeBalanceType::INCOME_STATEMENT);

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
    $vendorPostingGroup = VendorPostingGroup::query()->create([
        'code' => 'DOMESTIC',
        'description' => 'Domestic Vendors',
        'payables_account_id' => $payablesAccount->id,
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
            'purchase_account_id' => $purchaseAccount->id,
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
        'item_code' => 'RM-CT',
        'description' => 'Raw Carton Item',
        'item_type' => ItemType::RAW_MATERIAL,
        'base_uom_id' => $baseUom->id,
        'unit_cost' => 10,
        'inventory' => 0,
        'location_id' => $location->id,
        'general_product_posting_group_id' => $productGroup->id,
        'inventory_posting_group_id' => $inventoryGroup->id,
    ]);

    ItemUomAssignment::query()->create([
        'item_id' => $item->id,
        'uom_id' => $cartonUom->id,
        'uom_type' => 'PURCHASE',
        'conversion_factor' => 288,
        'is_default' => true,
    ]);

    $vendor = Vendor::factory()->create([
        'general_business_posting_group_id' => $businessGroup->id,
        'vendor_posting_group_id' => $vendorPostingGroup->id,
        'vat_bus_posting_group' => null,
    ]);

    return compact('user', 'vendor', 'item', 'location');
}

function purchasePostingTestAccount(
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

function grantPurchaseCreditMemoPostPermission(User $user): void
{
    Permission::query()->firstOrCreate([
        'name' => 'purchase.credit_memo.post',
        'guard_name' => 'web',
    ]);

    $user->givePermissionTo('purchase.credit_memo.post');
}
