<?php

use App\Enums\IncomeBalanceType;
use App\Enums\ItemType;
use App\Enums\SalesOrderStatus;
use App\Filament\Resources\SalesInvoices\Pages\PostedSalesInvoices;
use App\Filament\Resources\SalesInvoices\SalesInvoiceResource;
use App\Models\ChartOfAccount;
use App\Models\Customer;
use App\Models\CustomerPostingGroup;
use App\Models\GeneralBusinessPostingGroup;
use App\Models\GeneralPostingSetup;
use App\Models\GeneralProductPostingGroup;
use App\Models\InventoryPostingGroup;
use App\Models\InventoryPostingSetup;
use App\Models\Item;
use App\Models\ItemLedgerEntry;
use App\Models\Location;
use App\Models\NumberSeries;
use App\Models\NumberSeriesLine;
use App\Models\Permission;
use App\Models\PostedSalesInvoice;
use App\Models\Role;
use App\Models\SalesOrder;
use App\Models\SalesOrderLine;
use App\Models\UnitOfMeasure;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;

uses(RefreshDatabase::class);

it('loads posted sales invoice history after sales order ship and invoice without draft invoice actions', function () {
    $fixture = postedSalesInvoiceHistoryFixture();
    $this->actingAs($fixture['user']);

    $order = SalesOrder::query()->create([
        'order_number' => 'SO-POSTED-001',
        'order_type' => 'SALES_ORDER',
        'status' => SalesOrderStatus::APPROVED,
        'customer_id' => $fixture['customer']->id,
        'customer_name' => $fixture['customer']->name,
        'customer_address' => $fixture['customer']->address,
        'ship_to_name' => $fixture['customer']->name,
        'ship_to_address' => $fixture['customer']->address,
        'order_date' => now()->toDateString(),
        'posting_date' => now()->toDateString(),
        'shipment_date' => now()->toDateString(),
        'general_business_posting_group_id' => $fixture['businessGroup']->id,
        'customer_posting_group_id' => $fixture['customerPostingGroup']->id,
        'location_id' => $fixture['location']->id,
        'currency_code' => 'NGN',
        'currency_factor' => 1,
        'created_by' => $fixture['user']->id,
    ]);

    SalesOrderLine::query()->create([
        'sales_order_id' => $order->id,
        'item_id' => $fixture['item']->id,
        'item_code' => $fixture['item']->item_code,
        'description' => $fixture['item']->description,
        'quantity' => 1,
        'unit_of_measure_code' => 'PCS',
        'qty_per_unit_of_measure' => 1,
        'quantity_base' => 1,
        'unit_price' => 100,
        'unit_cost' => 10,
        'location_id' => $fixture['location']->id,
        'general_product_posting_group_id' => $fixture['productGroup']->id,
        'inventory_posting_group_id' => $fixture['inventoryGroup']->id,
    ]);

    $order->postShipment();
    $postedInvoice = $order->fresh()->postInvoice();
    $postedLine = $postedInvoice->fresh('lines')->lines->first();

    expect($postedInvoice)->toBeInstanceOf(PostedSalesInvoice::class)
        ->and($postedLine->item_ledger_entry_id)->not->toBeNull()
        ->and($postedLine->itemLedgerEntry)->toBeInstanceOf(ItemLedgerEntry::class)
        ->and($postedLine->itemLedgerEntry->document_number)->toBe("SS-{$order->order_number}")
        ->and((float) $postedLine->itemLedgerEntry->quantity)->toBe(-1.0)
        ->and($postedInvoice->fresh()->wasChanged())->toBeFalse()
        ->and($fixture['user']->can('viewAny', PostedSalesInvoice::class))->toBeTrue()
        ->and($fixture['user']->hasAnyRole(['admin']))->toBeTrue()
        ->and($fixture['user']->can('update', $postedInvoice))->toBeFalse()
        ->and($fixture['user']->can('delete', $postedInvoice))->toBeFalse();

    $this->withSession(['two_factor_passed_at' => now()->timestamp])
        ->get(SalesInvoiceResource::getUrl('posted'))
        ->assertOk()
        ->assertSee('Posted Sales Invoices')
        ->assertSee($postedInvoice->document_number)
        ->assertDontSee('Approve')
        ->assertDontSee('Reopen')
        ->assertDontSee('Cancel');

    Livewire::actingAs($fixture['user'])
        ->test(PostedSalesInvoices::class)
        ->assertTableActionVisible('viewPosted', $postedInvoice)
        ->assertTableActionVisible('printPostedInvoice', $postedInvoice)
        ->assertTableActionHidden('edit', $postedInvoice)
        ->assertTableActionDoesNotExist('delete')
        ->assertTableActionHidden('post', $postedInvoice)
        ->assertTableActionHidden('approve', $postedInvoice)
        ->assertTableActionHidden('reopen', $postedInvoice)
        ->assertTableActionHidden('cancel', $postedInvoice);
});

it('blocks sales order shipment when inventory stock is insufficient', function (): void {
    $fixture = postedSalesInvoiceHistoryFixture();
    $this->actingAs($fixture['user']);

    $fixture['item']->update(['inventory' => 0]);

    $order = SalesOrder::query()->create([
        'order_number' => 'SO-NEGATIVE-001',
        'order_type' => 'SALES_ORDER',
        'status' => SalesOrderStatus::APPROVED,
        'customer_id' => $fixture['customer']->id,
        'customer_name' => $fixture['customer']->name,
        'customer_address' => $fixture['customer']->address,
        'ship_to_name' => $fixture['customer']->name,
        'ship_to_address' => $fixture['customer']->address,
        'order_date' => now()->toDateString(),
        'posting_date' => now()->toDateString(),
        'shipment_date' => now()->toDateString(),
        'general_business_posting_group_id' => $fixture['businessGroup']->id,
        'customer_posting_group_id' => $fixture['customerPostingGroup']->id,
        'location_id' => $fixture['location']->id,
        'currency_code' => 'NGN',
        'currency_factor' => 1,
        'created_by' => $fixture['user']->id,
    ]);

    SalesOrderLine::query()->create([
        'sales_order_id' => $order->id,
        'item_id' => $fixture['item']->id,
        'item_code' => $fixture['item']->item_code,
        'description' => $fixture['item']->description,
        'quantity' => 1,
        'unit_of_measure_code' => 'PCS',
        'qty_per_unit_of_measure' => 1,
        'quantity_base' => 1,
        'unit_price' => 100,
        'unit_cost' => 10,
        'location_id' => $fixture['location']->id,
        'general_product_posting_group_id' => $fixture['productGroup']->id,
        'inventory_posting_group_id' => $fixture['inventoryGroup']->id,
    ]);

    expect(fn () => $order->postShipment())
        ->toThrow(ValidationException::class, 'Insufficient stock');

    expect(ItemLedgerEntry::query()
        ->where('document_number', "SS-{$order->order_number}")
        ->exists()
    )->toBeFalse();
});

/**
 * @return array{
 *     user: User,
 *     customer: Customer,
 *     item: Item,
 *     businessGroup: GeneralBusinessPostingGroup,
 *     productGroup: GeneralProductPostingGroup,
 *     inventoryGroup: InventoryPostingGroup,
 *     customerPostingGroup: CustomerPostingGroup,
 *     location: Location
 * }
 */
function postedSalesInvoiceHistoryFixture(): array
{
    $user = User::factory()->create([
        'two_factor_secret' => 'confirmed-secret',
        'two_factor_recovery_codes' => [],
        'two_factor_confirmed_at' => now(),
    ]);
    $adminRole = Role::query()->firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
    $user->assignRole($adminRole);

    foreach ([
        'sales.invoice.view_any',
        'sales.invoice.view',
        'sales.posted_sales_invoice.view_any',
        'sales.posted_sales_invoice.view',
        'sales.posted_sales_invoice.print',
        'sales.posted_sales_invoice.export',
    ] as $permission) {
        Permission::query()->firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
        $user->givePermissionTo($permission);
    }

    $receivablesAccount = postedSalesInvoiceHistoryAccount('1100', 'Accounts Receivable', 'receivable', IncomeBalanceType::BALANCE_SHEET);
    $inventoryAccount = postedSalesInvoiceHistoryAccount('1200', 'Inventory', 'inventory', IncomeBalanceType::BALANCE_SHEET);
    $revenueAccount = postedSalesInvoiceHistoryAccount('4000', 'Sales Revenue', 'revenue', IncomeBalanceType::INCOME_STATEMENT);
    $cogsAccount = postedSalesInvoiceHistoryAccount('5000', 'Cost of Goods Sold', 'cogs', IncomeBalanceType::INCOME_STATEMENT);

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
        'item_code' => 'FG-POSTED',
        'description' => 'Posted invoice item',
        'item_type' => ItemType::FINISHED_GOOD,
        'base_uom_id' => $baseUom->id,
        'unit_cost' => 10,
        'inventory' => 10,
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

    ensurePostedSalesInvoiceHistoryNumberSeries();

    return compact('user', 'customer', 'item', 'businessGroup', 'productGroup', 'inventoryGroup', 'customerPostingGroup', 'location');
}

function postedSalesInvoiceHistoryAccount(
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

function ensurePostedSalesInvoiceHistoryNumberSeries(): void
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
