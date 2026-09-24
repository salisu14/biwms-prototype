<?php

declare(strict_types=1);

use App\Enums\CategoryType;
use App\Enums\IncomeBalanceType;
use App\Enums\ItemType;
use App\Enums\PurchaseOrderStatus;
use App\Filament\Resources\Items\Pages\EditItem;
use App\Filament\Resources\PurchaseReceipts\Pages\ViewPurchaseReceipt;
use App\Filament\Resources\PurchaseReceipts\RelationManagers\LinesRelationManager;
use App\Models\Business;
use App\Models\Category;
use App\Models\ChartOfAccount;
use App\Models\Currency;
use App\Models\GeneralBusinessPostingGroup;
use App\Models\Item;
use App\Models\Location;
use App\Models\PostedPurchaseInvoiceLine;
use App\Models\PurchaseOrder;
use App\Models\PurchaseReceipt;
use App\Models\PurchaseReceiptLine;
use App\Models\UnitOfMeasure;
use App\Models\User;
use App\Models\Vendor;
use App\Models\VendorPostingGroup;
use App\Support\CurrencyPresentation;
use Database\Seeders\PermissionsTableSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Number;
use Livewire\Livewire;
use Spatie\Permission\PermissionRegistrar;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    $this->seed(PermissionsTableSeeder::class);
});

it('separates a defaultable currency context from persisted unknown document provenance', function (): void {
    // Defaultable context: a new/contractually-defaulted document with no explicit
    // currency resolves to the configured application default, never Laravel USD.
    expect(config('app.default_currency'))->toBe('NGN')
        ->and(CurrencyPresentation::default())->toBe('NGN')
        ->and(CurrencyPresentation::lcy())->toBe('NGN')
        ->and(CurrencyPresentation::documentOrDefault(null))->toBe('NGN')
        ->and(CurrencyPresentation::documentOrDefault('  '))->toBe('NGN');

    expect(Number::currency(100, CurrencyPresentation::documentOrDefault(null)))
        ->toBe(Number::currency(100, 'NGN'))
        ->not->toBe(Number::currency(100, 'USD'));

    // Persisted unknown provenance: a missing code stays unknown so a legacy
    // document can never be relabelled as NGN (or USD) merely by assumption.
    expect(CurrencyPresentation::document(null))->toBeNull()
        ->and(CurrencyPresentation::document('  '))->toBeNull()
        ->and(CurrencyPresentation::symbol(null))->toBe('—');
});

it('labels a document amount with its own currency and leaves unknown codes neutral', function (): void {
    expect(CurrencyPresentation::document('usd'))->toBe('USD')
        ->and(CurrencyPresentation::document('  '))->toBeNull()
        ->and(CurrencyPresentation::document(null))->toBeNull()
        ->and(CurrencyPresentation::documentOrDefault('EUR'))->toBe('EUR');

    expect(CurrencyPresentation::symbol(null))->toBe('—')
        ->and(CurrencyPresentation::symbol(''))->toBe('—')
        ->and(CurrencyPresentation::symbol('NGN'))->toBe('₦')
        ->and(CurrencyPresentation::symbol('USD'))->toBe('$')
        ->and(CurrencyPresentation::symbol('EUR'))->toBe('€')
        ->and(CurrencyPresentation::symbol('GBP'))->toBe('£')
        ->and(CurrencyPresentation::symbol('JPY'))->toBe('JPY');
});

it('renders LCY reference costs as NGN even when the item commercial currency is USD', function (): void {
    $user = User::factory()->create();
    $user->givePermissionTo([
        'sales.item.view_any',
        'sales.item.view',
        'sales.item.update',
    ]);

    $item = presentationItem();

    $this->actingAs($user)
        ->get("/admin/items/{$item->getKey()}")
        ->assertSuccessful()
        // LCY reference cost is labelled with the company LCY, not the item's USD.
        ->assertSee(Number::currency(850, 'NGN'), escape: false)
        // The commercial selling price legitimately keeps the item's own currency.
        ->assertSee(Number::currency(1200, 'USD'), escape: false)
        ->assertDontSee(Number::currency(850, 'USD'), escape: false);
});

it('never labels the persisted last direct cost with a currency', function (): void {
    $user = User::factory()->create();
    $user->givePermissionTo([
        'sales.item.view_any',
        'sales.item.view',
        'sales.item.update',
    ]);

    $item = presentationItem();

    expect((float) $item->last_direct_cost)->toBe(1500.0);

    $this->actingAs($user);

    $component = Livewire::test(EditItem::class, ['record' => $item->getKey()])->instance();
    $fields = $component->getSchema('form')->getFlatFields(withHidden: true);

    // The ambiguous master/legacy value carries no currency prefix at all.
    expect($fields['last_direct_cost']->getPrefixLabel())->toBeNull()
        ->and($fields['last_direct_cost']->getLabel())->toBe('Last Direct Cost (Currency Unavailable)')
        // LCY reference costs stay attached to the company LCY...
        ->and($fields['unit_cost']->getPrefixLabel())->toBe('₦')
        ->and($fields['standard_cost']->getPrefixLabel())->toBe('₦')
        // ...while the commercial selling price keeps the item's own currency.
        ->and($fields['unit_price']->getPrefixLabel())->toBe('$');

    $this->get("/admin/items/{$item->getKey()}/edit")
        ->assertSuccessful()
        ->assertSee('Last Direct Cost (Currency Unavailable)')
        ->assertSee('Legacy/master cost value; currency provenance is unavailable.')
        ->assertDontSee('Last purchase price from vendor');
});

it('shows document currency and LCY columns for a posted purchase invoice', function (): void {
    $fixture = $this->createPostedPayableFixture(1000.00);
    $invoice = $fixture['postedInvoice'];

    $invoice->update([
        'currency_code' => 'USD',
        'currency_factor' => 1500,
        'total_amount_lcy' => 1500000,
        'total_vat_lcy' => 0,
        'grand_total_lcy' => 1500000,
        'remaining_amount_lcy' => 1500000,
    ]);

    PostedPurchaseInvoiceLine::query()->create([
        'posted_purchase_invoice_id' => $invoice->id,
        'line_number' => 10000,
        'item_code' => 'ITEM-1',
        'item_description' => 'Test Item',
        'quantity' => 2,
        'unit_of_measure_code' => 'PCS',
        'unit_cost' => 500,
        'unit_cost_lcy' => 750000,
        'line_total' => 1000,
        'line_total_lcy' => 1500000,
        'line_discount_amount' => 0,
        'vat_amount' => 0,
        'vat_amount_lcy' => 0,
        'amount_including_vat' => 1000,
        'amount_including_vat_lcy' => 1500000,
    ]);

    $user = $fixture['user'];
    $user->givePermissionTo([
        'procurement.purchase_invoice.view_any',
        'procurement.purchase_invoice.view',
    ]);

    $this->actingAs($user)
        ->get("/admin/purchase-invoices/history/posted/{$invoice->id}")
        ->assertSuccessful()
        ->assertSee('Amount (USD)')
        ->assertSee('Amount (LCY / NGN)')
        ->assertSee('Unit Cost (USD)')
        ->assertSee('Unit Cost (NGN)')
        ->assertSee('Line Total (NGN)');
});

it('presents a receipt with no currency as a neutral amount instead of NGN or USD', function (): void {
    $fixture = presentationFixture();
    $user = $fixture['user'];
    $user->givePermissionTo([
        'procurement.purchase_receipt.view_any',
        'procurement.purchase_receipt.view',
    ]);

    $receipt = presentationReceipt($fixture, ['currency_code' => null, 'exchange_rate' => null]);
    presentationReceiptLine($receipt);

    $this->actingAs($user);

    // The receipt line table is a lazy relation manager, so it is rendered directly.
    $unknown = Livewire::test(LinesRelationManager::class, [
        'ownerRecord' => $receipt,
        'pageClass' => ViewPurchaseReceipt::class,
    ]);

    $unknown->assertSuccessful()
        // Neutral numeric presentation: the amount renders without any currency.
        ->assertSee(Number::format(4321, 2))
        ->assertDontSee(Number::currency(4321, 'NGN'), escape: false)
        ->assertDontSee(Number::currency(4321, 'USD'), escape: false)
        ->assertDontSee(Number::currency(2160.5, 'NGN'), escape: false)
        ->assertDontSee(Number::currency(2160.5, 'USD'), escape: false);

    // An explicit NGN receipt keeps its own document currency.
    $receipt->update(['currency_code' => 'NGN', 'exchange_rate' => 1]);

    Livewire::test(LinesRelationManager::class, [
        'ownerRecord' => $receipt->fresh(),
        'pageClass' => ViewPurchaseReceipt::class,
    ])->assertSuccessful()
        ->assertSee(Number::currency(4321, 'NGN'), escape: false)
        ->assertDontSee(Number::currency(4321, 'USD'), escape: false);

    // An explicit USD receipt keeps its own document currency.
    $receipt->update(['currency_code' => 'USD', 'exchange_rate' => 1500]);

    Livewire::test(LinesRelationManager::class, [
        'ownerRecord' => $receipt->fresh(),
        'pageClass' => ViewPurchaseReceipt::class,
    ])->assertSuccessful()
        ->assertSee(Number::currency(4321, 'USD'), escape: false)
        ->assertDontSee(Number::currency(4321, 'NGN'), escape: false);
});

it('presents the active and archived purchase order lists in each document currency', function (): void {
    $fixture = presentationFixture();
    $user = $fixture['user'];
    $user->givePermissionTo([
        'procurement.purchase_order.view_any',
        'procurement.purchase_order.view',
    ]);

    presentationOrder($fixture, [
        'order_number' => 'PO-PRES-NGN',
        'currency_code' => 'NGN',
        'grand_total' => 1234.5,
    ]);

    presentationOrder($fixture, [
        'order_number' => 'PO-PRES-USD',
        'currency_code' => 'USD',
        'grand_total' => 5678,
    ]);

    presentationOrder($fixture, [
        'order_number' => 'PO-PRES-ARCHIVED-EUR',
        'status' => PurchaseOrderStatus::INVOICED,
        'currency_code' => 'EUR',
        'grand_total' => 99.75,
    ]);

    $this->actingAs($user)
        ->get('/admin/purchase-orders')
        ->assertSuccessful()
        ->assertSee(Number::currency(1234.5, 'NGN'), escape: false)
        ->assertSee(Number::currency(5678, 'USD'), escape: false)
        // The NGN order must never be presented through a USD fallback.
        ->assertDontSee(Number::currency(1234.5, 'USD'), escape: false);

    $this->actingAs($user)
        ->get('/admin/purchase-orders/history/archived-pos')
        ->assertSuccessful()
        ->assertSee(Number::currency(99.75, 'EUR'), escape: false)
        ->assertDontSee(Number::currency(99.75, 'USD'), escape: false);
});

it('no longer hardcodes USD on the corrected purchase, G/L, item and vendor surfaces', function (): void {
    $correctedSurfaces = [
        'app/Filament/Resources/PurchaseOrders/Schemas/PurchaseOrderForm.php',
        'app/Filament/Resources/PurchaseOrders/Schemas/PurchaseOrderInfolist.php',
        'app/Filament/Resources/PurchaseOrders/RelationManagers/PurchaseOrderLinesRelationManager.php',
        'app/Filament/Resources/PurchaseOrders/RelationManagers/GlEntriesRelationManager.php',
        'app/Filament/Resources/PurchaseOrders/PurchaseOrderResource.php',
        'app/Filament/Resources/PurchaseOrders/Tables/PurchaseOrdersTable.php',
        'app/Filament/Resources/PurchaseOrders/Pages/ArchivedPurchaseOrders.php',
        'app/Filament/Resources/PurchaseReceipts/RelationManagers/LinesRelationManager.php',
        'app/Filament/Resources/PurchaseInvoices/Tables/PurchaseInvoicesTable.php',
        'app/Filament/Resources/PurchaseInvoices/Schemas/PurchaseInvoiceInfolist.php',
        'app/Filament/Resources/PurchaseInvoices/Pages/PostedPurchaseInvoices.php',
        'app/Filament/Resources/PurchaseInvoices/Pages/ViewPostedPurchaseInvoice.php',
        'app/Filament/Resources/PurchaseInvoices/Pages/ViewPurchaseInvoice.php',
        'app/Filament/Resources/PurchaseInvoices/Pages/EditPurchaseInvoice.php',
        'app/Filament/Resources/PurchaseInvoices/PurchaseInvoiceResource.php',
        'app/Filament/Resources/PurchaseCreditMemos/PurchaseCreditMemoResource.php',
        'app/Filament/Resources/PostedPurchaseCreditMemos/PostedPurchaseCreditMemoResource.php',
        'app/Filament/Resources/PostedPurchaseCreditMemos/Schemas/PostedPurchaseCreditMemoForm.php',
        'app/Filament/Resources/PostedPurchaseCreditMemos/Schemas/PostedPurchaseCreditMemoInfolist.php',
        'app/Filament/Resources/PostedPurchaseCreditMemos/Tables/PostedPurchaseCreditMemosTable.php',
        'app/Filament/Resources/PostedPurchaseCreditMemos/Pages/ViewPostedPurchaseCreditMemo.php',
        'app/Filament/Resources/JournalLines/Tables/JournalLinesTable.php',
        'app/Filament/Resources/JournalLines/Schemas/JournalLineInfolist.php',
        'app/Filament/Resources/JournalLines/Schemas/JournalLineForm.php',
        'app/Filament/Resources/SalesOrders/RelationManagers/GlEntriesRelationManager.php',
        'app/Filament/Resources/Items/Schemas/ItemInfolist.php',
        'app/Filament/Resources/Items/Schemas/ItemForm.php',
        'app/Filament/Resources/Items/Tables/ItemsTable.php',
        'app/Filament/Sales/Resources/Items/Schemas/ItemForm.php',
        'app/Filament/Sales/Resources/Items/Schemas/ItemInfolist.php',
        'app/Filament/Sales/Resources/Items/Tables/ItemsTable.php',
        'app/Filament/Resources/Vendors/Schemas/VendorForm.php',
        'app/Filament/Resources/VendorLedgerEntries/Schemas/VendorLedgerEntryInfolist.php',
        'resources/views/filament/resources/purchase-invoices/pages/view-posted-purchase-invoice.blade.php',
    ];

    foreach ($correctedSurfaces as $relativePath) {
        $source = file_get_contents(base_path($relativePath));

        // No hardcoded USD money labels, dollar prefixes, or silent USD fallbacks.
        expect($source)->not->toContain('money(\'USD\')');
        expect($source)->not->toContain('prefix(\'$\')');
        expect($source)->not->toContain('config(\'app.default_currency\', \'USD\')');
        expect($source)->not->toContain('?: \'USD\'');
    }

    // Base G/L amounts are LCY and must render as NGN on every active G/L surface.
    foreach ([
        'app/Filament/Resources/PurchaseOrders/RelationManagers/GlEntriesRelationManager.php',
        'app/Filament/Resources/SalesOrders/RelationManagers/GlEntriesRelationManager.php',
        'app/Filament/Resources/JournalLines/Tables/JournalLinesTable.php',
        'app/Filament/Resources/JournalLines/Schemas/JournalLineInfolist.php',
    ] as $relativePath) {
        expect(file_get_contents(base_path($relativePath)))->toContain('money(\'NGN\')');
    }
});

function presentationItem(): Item
{
    $usd = Currency::factory()->create([
        'code' => 'USD',
        'symbol' => '$',
        'is_lcy' => false,
    ]);

    $uom = UnitOfMeasure::query()->firstOrCreate(
        ['uom_code' => 'PCS'],
        ['description' => 'Pieces', 'conversion_factor' => 1, 'is_base_uom' => true],
    );

    $location = Location::factory()->create(['code' => 'GBS-FGN']);

    $category = Category::query()->firstOrCreate(
        ['category_code' => 'FGN'],
        [
            'category_name' => 'Uncategorized',
            'hierarchy_path' => 'Uncategorized',
            'category_type' => CategoryType::FINISHED_GOOD,
            'level' => 0,
            'sort_order' => 1,
            'is_active' => true,
        ],
    );

    return Item::factory()->create([
        'item_code' => '1000',
        'description' => 'Mai Sasanci',
        'item_type' => ItemType::FINISHED_GOOD,
        'unit_price' => 1200,
        'standard_cost' => 850,
        'last_direct_cost' => 1500,
        'currency_id' => $usd->id,
        'base_uom_id' => $uom->id,
        'location_id' => $location->id,
        'item_category_id' => $category->id,
    ]);
}

function presentationFixture(): array
{
    $user = User::factory()->create();
    $location = Location::factory()->create(['code' => 'MAIN']);
    $business = Business::query()->create([
        'code' => 'BUS-PRES-'.substr(uniqid(), -6),
        'name' => 'Presentation Business',
        'is_active' => true,
    ]);

    $businessGroup = GeneralBusinessPostingGroup::query()->create([
        'code' => 'PRES-'.substr(uniqid(), -6),
        'description' => 'Domestic',
        'blocked' => false,
    ]);

    $payables = ChartOfAccount::query()->create([
        'account_number' => 'PRES-'.substr(uniqid(), -6),
        'name' => 'Accounts Payable',
        'account_category' => 'payable',
        'income_balance' => IncomeBalanceType::BALANCE_SHEET,
        'direct_posting' => true,
        'blocked' => false,
    ]);

    $vendorPostingGroup = VendorPostingGroup::query()->create([
        'code' => 'PRES-'.substr(uniqid(), -6),
        'description' => 'Domestic Vendors',
        'payables_account_id' => $payables->id,
        'blocked' => false,
    ]);

    $vendor = Vendor::factory()->create([
        'general_business_posting_group_id' => $businessGroup->id,
        'vendor_posting_group_id' => $vendorPostingGroup->id,
        'vat_bus_posting_group' => null,
    ]);

    return compact('user', 'vendor', 'location', 'business');
}

function presentationOrder(array $fixture, array $overrides): PurchaseOrder
{
    return PurchaseOrder::query()->create(array_merge([
        'order_number' => 'PO-PRES-'.substr(uniqid(), -8),
        'status' => PurchaseOrderStatus::APPROVED,
        'business_id' => $fixture['business']->id,
        'vendor_id' => $fixture['vendor']->id,
        'vendor_name' => $fixture['vendor']->vendor_name,
        'order_date' => now()->toDateString(),
        'posting_date' => now()->toDateString(),
        'location_id' => $fixture['location']->id,
        'payment_terms' => 30,
        'general_business_posting_group_id' => $fixture['vendor']->general_business_posting_group_id,
        'vendor_posting_group_id' => $fixture['vendor']->vendor_posting_group_id,
        'created_by' => $fixture['user']->id,
        'currency_code' => 'NGN',
        'grand_total' => 0,
    ], $overrides));
}

function presentationReceipt(array $fixture, array $overrides): PurchaseReceipt
{
    return PurchaseReceipt::query()->create(array_merge([
        'business_id' => $fixture['business']->id,
        'document_number' => 'PR-PRES-'.substr(uniqid(), -8),
        'vendor_id' => $fixture['vendor']->id,
        'posting_date' => now()->toDateString(),
        'document_date' => now()->toDateString(),
        'currency_code' => 'NGN',
        'exchange_rate' => 1,
    ], $overrides));
}

function presentationReceiptLine(PurchaseReceipt $receipt): PurchaseReceiptLine
{
    return PurchaseReceiptLine::query()->create([
        'purchase_receipt_id' => $receipt->id,
        'line_number' => 10000,
        'type' => 'ITEM',
        'no' => 'ITEM-1',
        'description' => 'Presentation line',
        'quantity' => 2,
        'quantity_received' => 0,
        'quantity_invoiced' => 0,
        'direct_unit_cost' => 2160.5,
        'line_amount' => 4321,
    ]);
}
