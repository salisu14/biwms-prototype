<?php

declare(strict_types=1);

use App\Enums\AccountCategory;
use App\Enums\AccountStructuralType;
use App\Enums\CostingMethod;
use App\Enums\IncomeBalanceType;
use App\Enums\ItemLedgerEntryType;
use App\Enums\ItemType;
use App\Enums\SalesOrderStatus;
use App\Models\AccountingPeriod;
use App\Models\ChartOfAccount;
use App\Models\Customer;
use App\Models\CustomerPostingGroup;
use App\Models\GeneralBusinessPostingGroup;
use App\Models\GeneralLedgerSetup;
use App\Models\GeneralPostingSetup;
use App\Models\GeneralProductPostingGroup;
use App\Models\GlEntry;
use App\Models\InventoryPostingGroup;
use App\Models\InventoryPostingSetup;
use App\Models\Item;
use App\Models\ItemApplicationEntry;
use App\Models\ItemLedgerEntry;
use App\Models\Location;
use App\Models\SalesOrder;
use App\Models\SalesOrderLine;
use App\Models\UnitOfMeasure;
use App\Models\User;
use App\Models\ValueEntry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Validation\ValidationException;

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

it('posts sales order shipment through canonical fifo item application before value entry accounting', function (): void {
    $user = User::factory()->create();
    $this->actingAs($user);

    $fixture = salesShipmentApplicationFixture($user);
    $firstInbound = salesShipmentInboundLayer($fixture, 'PO-IN-001', 5, 50, '2026-01-01');
    $secondInbound = salesShipmentInboundLayer($fixture, 'PO-IN-002', 10, 150, '2026-01-02');
    $order = salesShipmentOrder($fixture, $user, quantity: 12);

    $order->postShipment();

    expect(fn () => $order->fresh()->postShipment())
        ->toThrow(ValidationException::class);

    $shipmentDocumentNumber = "SS-{$order->order_number}";
    $outbound = ItemLedgerEntry::query()
        ->where('document_type', 'SALES_ORDER_SHIPMENT')
        ->where('document_number', $shipmentDocumentNumber)
        ->firstOrFail();
    $applications = ItemApplicationEntry::query()
        ->where('outbound_item_ledger_entry_id', $outbound->id)
        ->where('is_reversed', false)
        ->orderBy('id')
        ->get();
    $valueEntry = ValueEntry::query()
        ->where('item_ledger_entry_no', $outbound->entry_number)
        ->firstOrFail();

    expect($applications)->toHaveCount(2)
        ->and($applications->pluck('inbound_item_ledger_entry_id')->all())->toBe([$firstInbound->id, $secondInbound->id])
        ->and((float) $applications->sum('applied_quantity'))->toBe(12.0)
        ->and((float) $applications->sum('cost_amount'))->toBe(155.0)
        ->and((float) $firstInbound->fresh()->remaining_quantity)->toBe(0.0)
        ->and((float) $secondInbound->fresh()->remaining_quantity)->toBe(3.0)
        ->and((float) $outbound->quantity)->toBe(-12.0)
        ->and((float) $outbound->remaining_quantity)->toBe(0.0)
        ->and((float) $outbound->cost_amount_actual)->toBe(155.0)
        ->and((float) $valueEntry->cost_amount_actual)->toBe(155.0)
        ->and($valueEntry->gl_posted)->toBeTrue()
        ->and(ValueEntry::query()->where('item_ledger_entry_no', $outbound->entry_number)->count())->toBe(1)
        ->and(GlEntry::query()->where('item_ledger_entry_id', $outbound->id)->count())->toBe(2);

    expect(Artisan::call('biwms:costing-reconcile', ['--json' => true]))->toBe(0);
    $report = json_decode(trim(Artisan::output()), true, flags: JSON_THROW_ON_ERROR);

    expect($report['outbound_without_applications'])->toBeEmpty()
        ->and($report['application_quantity_mismatches'])->toBeEmpty()
        ->and($report['value_entry_cost_mismatches'])->toBeEmpty();
});

function salesShipmentApplicationFixture(User $user): array
{
    $receivablesAccount = salesShipmentAccount('11400', 'Shipment Receivables', AccountCategory::RECEIVABLE);
    $inventoryAccount = salesShipmentAccount('12400', 'Shipment Inventory', AccountCategory::INVENTORY);
    $revenueAccount = salesShipmentAccount('41400', 'Shipment Revenue', AccountCategory::REVENUE);
    $cogsAccount = salesShipmentAccount('51400', 'Shipment COGS', AccountCategory::COGS);
    $businessGroup = GeneralBusinessPostingGroup::query()->create([
        'code' => 'SHIP-DOM',
        'description' => 'Shipment Domestic',
        'blocked' => false,
    ]);
    $productGroup = GeneralProductPostingGroup::query()->create([
        'code' => 'SHIP-FG',
        'description' => 'Shipment Finished Goods',
        'blocked' => false,
    ]);
    $inventoryGroup = InventoryPostingGroup::query()->create([
        'code' => 'SHIP-FG',
        'description' => 'Shipment Finished Goods',
        'blocked' => false,
    ]);
    $customerPostingGroup = CustomerPostingGroup::query()->create([
        'code' => 'SHIP-DOM',
        'description' => 'Shipment Customers',
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
        'item_code' => 'SHIP-FG-001',
        'description' => 'Shipment Finished Good',
        'item_type' => ItemType::FINISHED_GOOD,
        'base_uom_id' => $baseUom->id,
        'costing_method' => CostingMethod::FIFO->value,
        'unit_price' => 100,
        'unit_cost' => 10,
        'inventory' => 15,
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

    return compact('businessGroup', 'productGroup', 'inventoryGroup', 'customerPostingGroup', 'location', 'item', 'customer', 'user');
}

function salesShipmentInboundLayer(array $fixture, string $documentNumber, float $quantity, float $cost, string $postingDate): ItemLedgerEntry
{
    return ItemLedgerEntry::query()->create([
        'entry_type' => ItemLedgerEntryType::PURCHASE,
        'document_type' => 'PURCHASE_RECEIPT',
        'document_number' => $documentNumber,
        'document_line_number' => 10000,
        'item_id' => $fixture['item']->id,
        'location_id' => $fixture['location']->id,
        'quantity' => $quantity,
        'remaining_quantity' => $quantity,
        'cost_amount_actual' => $cost,
        'cost_amount_expected' => 0,
        'purchase_amount_actual' => $cost,
        'general_business_posting_group_id' => $fixture['businessGroup']->id,
        'general_product_posting_group_id' => $fixture['productGroup']->id,
        'inventory_posting_group_id' => $fixture['inventoryGroup']->id,
        'posting_date' => $postingDate,
        'entry_date' => now(),
        'open' => true,
    ]);
}

function salesShipmentOrder(array $fixture, User $user, float $quantity): SalesOrder
{
    $order = SalesOrder::query()->create([
        'order_number' => 'SO-APP-001',
        'order_type' => 'SALES_ORDER',
        'status' => SalesOrderStatus::APPROVED,
        'customer_id' => $fixture['customer']->id,
        'customer_name' => $fixture['customer']->name,
        'customer_address' => $fixture['customer']->address,
        'ship_to_name' => $fixture['customer']->name,
        'ship_to_address' => $fixture['customer']->address,
        'order_date' => '2026-06-25',
        'posting_date' => '2026-06-25',
        'shipment_date' => '2026-06-25',
        'general_business_posting_group_id' => $fixture['businessGroup']->id,
        'customer_posting_group_id' => $fixture['customerPostingGroup']->id,
        'location_id' => $fixture['location']->id,
        'currency_code' => 'NGN',
        'currency_factor' => 1,
        'created_by' => $user->id,
    ]);

    SalesOrderLine::query()->create([
        'sales_order_id' => $order->id,
        'line_number' => 10000,
        'item_id' => $fixture['item']->id,
        'item_code' => $fixture['item']->item_code,
        'description' => $fixture['item']->description,
        'quantity' => $quantity,
        'unit_of_measure_code' => 'PCS',
        'qty_per_unit_of_measure' => 1,
        'quantity_base' => $quantity,
        'unit_price' => 100,
        'unit_cost' => 10,
        'location_id' => $fixture['location']->id,
        'general_product_posting_group_id' => $fixture['productGroup']->id,
        'inventory_posting_group_id' => $fixture['inventoryGroup']->id,
    ]);

    return $order;
}

function salesShipmentAccount(string $number, string $name, AccountCategory $category): ChartOfAccount
{
    return ChartOfAccount::query()->create([
        'account_number' => $number,
        'name' => $name,
        'structural_type' => AccountStructuralType::POSTING,
        'account_category' => $category,
        'balance' => 0,
        'direct_posting' => true,
        'blocked' => false,
        'income_balance' => $category->isBalanceSheet()
            ? IncomeBalanceType::BALANCE_SHEET
            : IncomeBalanceType::INCOME_STATEMENT,
    ]);
}
