<?php

declare(strict_types=1);

use App\Enums\AccountCategory;
use App\Enums\AccountStructuralType;
use App\Enums\CostingMethod;
use App\Enums\IncomeBalanceType;
use App\Enums\ItemLedgerEntryType;
use App\Enums\ItemType;
use App\Enums\SalesOrderStatus;
use App\Enums\SalesOrderType;
use App\Exceptions\InsufficientInventoryApplicationException;
use App\Filament\Resources\SalesOrders\Pages\CreateSalesOrder as AdminCreateSalesOrder;
use App\Filament\Sales\Resources\SalesOrders\Pages\CreateSalesOrder;
use App\Filament\Sales\Resources\SalesOrders\Pages\EditSalesOrder;
use App\Filament\Sales\Resources\SalesOrders\Pages\ListSalesOrders;
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
use App\Models\ItemApplicationEntry;
use App\Models\ItemLedgerEntry;
use App\Models\ItemUomAssignment;
use App\Models\Location;
use App\Models\NumberSeries;
use App\Models\NumberSeriesLine;
use App\Models\Payment;
use App\Models\Role;
use App\Models\SalesOrder;
use App\Models\SalesOrderLine;
use App\Models\UnitOfMeasure;
use App\Models\User;
use App\Models\ValueEntry;
use App\Services\Accounting\LedgerSequenceAllocator;
use App\Services\Finance\PaymentService;
use App\Support\SalesOrderPostingActionHandler;
use Filament\Notifications\Notification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Spatie\Permission\PermissionRegistrar;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    app(PermissionRegistrar::class)->forgetCachedPermissions();

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

it('persists approved sales order lines from the Filament create flow and recalculates header totals', function (): void {
    $user = salesOrderFilamentUser();
    $fixture = salesOrderFilamentFixture();
    salesOrderFilamentNumberSeries();

    Livewire::actingAs($user)
        ->test(CreateSalesOrder::class)
        ->fillForm(salesOrderFilamentPayload($fixture, SalesOrderStatus::APPROVED))
        ->call('create')
        ->assertHasNoFormErrors();

    $order = SalesOrder::query()->with('lines')->firstOrFail();
    $line = $order->lines->first();

    expect($order->status)->toBe(SalesOrderStatus::APPROVED)
        ->and($order->order_number)->toBe('SO-2026-00001')
        ->and($order->lines)->toHaveCount(1)
        ->and($line)->toBeInstanceOf(SalesOrderLine::class)
        ->and($line->item_id)->toBe($fixture['item']->id)
        ->and((float) $line->quantity)->toBe(10.0)
        ->and((float) $line->quantity_to_ship)->toBe(10.0)
        ->and((float) $line->unit_price)->toBe(150.0)
        ->and((float) $line->line_total)->toBe(1500.0)
        ->and((float) $order->grand_total)->toBe(1500.0);
});

it('recalculates sales order header totals for admin create and persisted line mutations', function (): void {
    $user = salesOrderFilamentUser();
    $fixture = salesOrderFilamentFixture();
    salesOrderFilamentNumberSeries();

    Livewire::actingAs($user)
        ->test(AdminCreateSalesOrder::class)
        ->fillForm(salesOrderFilamentPayload($fixture, SalesOrderStatus::DRAFT))
        ->call('create')
        ->assertHasNoFormErrors();

    $order = SalesOrder::query()->with('lines')->firstOrFail();
    $line = $order->lines->firstOrFail();

    expect((float) $order->fresh()->grand_total)->toBe(1500.0);

    $line->update([
        'quantity' => 4,
    ]);

    expect((float) $order->fresh()->grand_total)->toBe(600.0);

    SalesOrderLine::query()->create([
        'sales_order_id' => $order->id,
        'line_number' => 20,
        'item_id' => $fixture['item']->id,
        'item_code' => $fixture['item']->item_code,
        'description' => $fixture['item']->description,
        'quantity' => 2,
        'unit_of_measure_code' => 'PCS',
        'qty_per_unit_of_measure' => 1,
        'unit_price' => 150,
        'line_discount_percent' => 0,
        'location_id' => $fixture['location']->id,
        'general_product_posting_group_id' => $fixture['productGroup']->id,
        'inventory_posting_group_id' => $fixture['inventoryGroup']->id,
    ]);

    expect((float) $order->fresh()->grand_total)->toBe(900.0);

    $order->fresh('lines')->lines->firstWhere('line_number', 20)?->delete();

    expect((float) $order->fresh()->grand_total)->toBe(600.0);
});

it('rolls back the Filament create flow when no persisted positive quantity line exists', function (): void {
    $user = salesOrderFilamentUser();
    $fixture = salesOrderFilamentFixture();
    salesOrderFilamentNumberSeries();

    Livewire::actingAs($user)
        ->test(CreateSalesOrder::class)
        ->fillForm([
            ...salesOrderFilamentPayload($fixture, SalesOrderStatus::APPROVED),
            'lines' => [],
        ])
        ->call('create')
        ->assertHasFormErrors(['lines']);

    expect(SalesOrder::query()->count())->toBe(0)
        ->and(SalesOrderLine::query()->count())->toBe(0);
});

it('rejects zero quantity lines before creating a sales order header', function (): void {
    $user = salesOrderFilamentUser();
    $fixture = salesOrderFilamentFixture();
    salesOrderFilamentNumberSeries();

    $payload = salesOrderFilamentPayload($fixture, SalesOrderStatus::APPROVED);
    $payload['lines'][0]['quantity'] = 0;

    Livewire::actingAs($user)
        ->test(CreateSalesOrder::class)
        ->fillForm($payload)
        ->call('create')
        ->assertHasFormErrors(['lines.0.quantity']);

    expect(SalesOrder::query()->count())->toBe(0)
        ->and(SalesOrderLine::query()->count())->toBe(0);
});

it('preserves persisted line identity on edit and keeps totals derived from saved lines', function (): void {
    $user = salesOrderFilamentUser();
    $fixture = salesOrderFilamentFixture();
    salesOrderFilamentNumberSeries();

    Livewire::actingAs($user)
        ->test(CreateSalesOrder::class)
        ->fillForm(salesOrderFilamentPayload($fixture, SalesOrderStatus::DRAFT))
        ->call('create')
        ->assertHasNoFormErrors();

    $order = SalesOrder::query()->with('lines')->firstOrFail();
    $lineId = $order->lines->first()->id;

    $payload = salesOrderFilamentPayload($fixture, SalesOrderStatus::DRAFT);
    $payload['lines'] = [
        "record-{$lineId}" => [
            'id' => $lineId,
            'line_number' => $order->lines->first()->line_number,
            'item_id' => $fixture['item']->id,
            'item_code' => $fixture['item']->item_code,
            'description' => $fixture['item']->description,
            'quantity' => 4,
            'unit_of_measure_code' => 'PCS',
            'qty_per_unit_of_measure' => 1,
            'unit_price' => 150,
            'line_discount_percent' => 0,
            'price_source' => null,
            'pricing_master_id' => null,
        ],
    ];

    Livewire::actingAs($user)
        ->test(EditSalesOrder::class, ['record' => $order->getRouteKey()])
        ->fillForm($payload)
        ->call('save')
        ->assertHasNoFormErrors();

    $order->refresh()->load('lines');

    expect($order->lines)->toHaveCount(1)
        ->and($order->lines->first()->id)->toBe($lineId)
        ->and((float) $order->lines->first()->quantity)->toBe(4.0)
        ->and((float) $order->grand_total)->toBe(600.0);
});

it('ships a Filament-created approved sales order because persisted lines remain available to posting', function (): void {
    $user = salesOrderFilamentUser();
    $fixture = salesOrderFilamentFixture();
    salesOrderFilamentNumberSeries();
    salesOrderFilamentInboundLayer($fixture, 10, 500);

    Livewire::actingAs($user)
        ->test(CreateSalesOrder::class)
        ->fillForm(salesOrderFilamentPayload($fixture, SalesOrderStatus::APPROVED))
        ->call('create')
        ->assertHasNoFormErrors();

    $order = SalesOrder::query()->with('lines')->firstOrFail();

    $order->postShipment();

    $outboundEntry = ItemLedgerEntry::query()
        ->where('document_number', "SS-{$order->order_number}")
        ->where('entry_type', ItemLedgerEntryType::SALE)
        ->firstOrFail();

    expect(ItemLedgerEntry::query()
        ->where('document_number', "SS-{$order->order_number}")
        ->where('entry_type', ItemLedgerEntryType::SALE)
        ->count())->toBe(1)
        ->and(ItemApplicationEntry::query()->count())->toBe(1)
        ->and(ValueEntry::query()->where('document_no', "SS-{$order->order_number}")->count())->toBe(1)
        ->and(GlEntry::query()->where('item_ledger_entry_id', $outboundEntry->id)->count())->toBe(2)
        ->and((float) $fixture['item']->fresh()->inventory)->toBe(0.0);
});

it('rolls back a shipment when ledger quantity exists but open inbound costing layers are insufficient', function (): void {
    $user = salesOrderFilamentUser();
    $fixture = salesOrderFilamentFixture();
    salesOrderFilamentNumberSeries();
    salesOrderFilamentClosedInboundLayer($fixture, 10, 500);

    Livewire::actingAs($user)
        ->test(CreateSalesOrder::class)
        ->fillForm(salesOrderFilamentPayload($fixture, SalesOrderStatus::APPROVED))
        ->call('create')
        ->assertHasNoFormErrors();

    $order = SalesOrder::query()->with('lines')->firstOrFail();

    foreach ([1, 2] as $attempt) {
        expect(fn () => $order->fresh()->postShipment())
            ->toThrow(
                InsufficientInventoryApplicationException::class,
                'insufficient open inbound quantity',
            );

        $order->refresh()->load('lines');

        expect(ItemLedgerEntry::query()
            ->where('document_number', "SS-{$order->order_number}")
            ->where('entry_type', ItemLedgerEntryType::SALE)
            ->count())->toBe(0)
            ->and(ItemApplicationEntry::query()->count())->toBe(0)
            ->and(ValueEntry::query()->where('document_no', "SS-{$order->order_number}")->count())->toBe(0)
            ->and(GlEntry::query()->where('document_number', "SS-{$order->order_number}")->count())->toBe(0)
            ->and((float) $order->lines->first()->quantity_shipped)->toBe(0.0)
            ->and((float) $order->quantity_shipped)->toBe(0.0)
            ->and($order->fully_shipped)->toBeFalse()
            ->and($order->status)->toBe(SalesOrderStatus::APPROVED);
    }
});

it('shows Filament danger notifications for expected shipment and post plus invoice failures', function (): void {
    $user = salesOrderFilamentUser();
    $fixture = salesOrderFilamentFixture();
    salesOrderFilamentNumberSeries();
    salesOrderFilamentInvoiceNumberSeries();
    salesOrderFilamentClosedInboundLayer($fixture, 10, 500);

    Livewire::actingAs($user)
        ->test(CreateSalesOrder::class)
        ->fillForm(salesOrderFilamentPayload($fixture, SalesOrderStatus::APPROVED))
        ->call('create')
        ->assertHasNoFormErrors();

    $order = SalesOrder::query()->firstOrFail();

    Livewire::actingAs($user)
        ->test(ListSalesOrders::class)
        ->assertTableActionVisible('post_shipment', $order)
        ->assertTableActionVisible('post_and_invoice', $order);

    app(SalesOrderPostingActionHandler::class)->postShipment($order);
    Notification::assertNotified('Insufficient inventory costing layers');

    app(SalesOrderPostingActionHandler::class)->postAndInvoice($order->fresh());
    Notification::assertNotified('Insufficient inventory costing layers');

    expect(ItemLedgerEntry::query()
        ->where('document_number', "SS-{$order->order_number}")
        ->where('entry_type', ItemLedgerEntryType::SALE)
        ->count())->toBe(0)
        ->and(ValueEntry::query()->where('document_no', "SS-{$order->order_number}")->count())->toBe(0)
        ->and($order->fresh()->status)->toBe(SalesOrderStatus::APPROVED);
});

it('renders an unshipped uninvoiced sales order as not invoiced in the table', function (): void {
    $user = salesOrderFilamentUser();
    $fixture = salesOrderFilamentFixture();
    salesOrderFilamentNumberSeries();

    Livewire::actingAs($user)
        ->test(CreateSalesOrder::class)
        ->fillForm(salesOrderFilamentPayload($fixture, SalesOrderStatus::APPROVED))
        ->call('create')
        ->assertHasNoFormErrors();

    $order = SalesOrder::query()->firstOrFail();

    expect($order->fully_invoiced)->toBeFalse()
        ->and((float) $order->quantity_invoiced)->toBe(0.0)
        ->and((float) $order->quantity_shipped)->toBe(0.0);

    Livewire::actingAs($user)
        ->test(ListSalesOrders::class)
        ->assertTableColumnStateSet('fully_invoiced', false, $order);
});

it('post plus invoice is successful and keeps shipment and invoice accounting idempotent with sufficient stock', function (): void {
    $user = salesOrderFilamentUser();
    $fixture = salesOrderFilamentFixture();
    salesOrderFilamentNumberSeries();
    salesOrderFilamentInvoiceNumberSeries();
    salesOrderFilamentInboundLayer($fixture, 10, 500);

    Livewire::actingAs($user)
        ->test(CreateSalesOrder::class)
        ->fillForm(salesOrderFilamentPayload($fixture, SalesOrderStatus::APPROVED))
        ->call('create')
        ->assertHasNoFormErrors();

    $order = SalesOrder::query()->with('lines')->firstOrFail();

    Livewire::actingAs($user)
        ->test(ListSalesOrders::class)
        ->assertTableActionVisible('post_and_invoice', $order);

    app(SalesOrderPostingActionHandler::class)->postAndInvoice($order);
    Notification::assertNotified('Shipment and Invoice Posted');

    $order->refresh()->load('lines');

    expect($order->status)->toBe(SalesOrderStatus::INVOICED)
        ->and($order->fully_shipped)->toBeTrue()
        ->and($order->fully_invoiced)->toBeTrue()
        ->and((float) $order->quantity_shipped)->toBe(10.0)
        ->and((float) $order->quantity_invoiced)->toBe(10.0)
        ->and(ItemLedgerEntry::query()->where('document_number', "SS-{$order->order_number}")->where('entry_type', ItemLedgerEntryType::SALE)->count())->toBe(1)
        ->and(ItemApplicationEntry::query()->count())->toBe(1)
        ->and(ValueEntry::query()->where('document_no', "SS-{$order->order_number}")->count())->toBe(1)
        ->and(GlEntry::query()->where('item_ledger_entry_id', ItemLedgerEntry::query()->where('document_number', "SS-{$order->order_number}")->value('id'))->count())->toBe(2)
        ->and($order->postedInvoices()->count())->toBe(1);

    expect(fn () => $order->fresh()->postShipment())->toThrow(ValidationException::class);
});

it('traces sales order shipment and posted invoice gl entries through canonical lifecycle ownership', function (): void {
    $user = salesOrderFilamentUser();
    $fixture = salesOrderFilamentFixture();
    salesOrderFilamentNumberSeries();
    salesOrderFilamentInvoiceNumberSeries();
    salesOrderFilamentInboundLayer($fixture, 10, 500);

    Livewire::actingAs($user)
        ->test(CreateSalesOrder::class)
        ->fillForm(salesOrderFilamentPayload($fixture, SalesOrderStatus::APPROVED))
        ->call('create')
        ->assertHasNoFormErrors();

    $order = SalesOrder::query()->with('lines')->firstOrFail();

    app(SalesOrderPostingActionHandler::class)->postAndInvoice($order);

    $order->refresh();
    $shipmentDocumentNumber = "SS-{$order->order_number}";
    $postedInvoice = $order->postedInvoices()->firstOrFail();
    $outboundEntry = ItemLedgerEntry::query()
        ->where('document_number', $shipmentDocumentNumber)
        ->where('entry_type', ItemLedgerEntryType::SALE)
        ->firstOrFail();
    $shipmentValueEntry = ValueEntry::query()
        ->where('document_no', $shipmentDocumentNumber)
        ->where('item_ledger_entry_no', $outboundEntry->entry_number)
        ->firstOrFail();

    salesOrderFilamentCreateUnrelatedGlEntry('SALES_INVOICE', 'SI-UNRELATED');
    salesOrderFilamentCreateUnrelatedGlEntry('COST_ADJUSTMENT', $shipmentDocumentNumber, $outboundEntry->id);

    $glEntries = $order->accountingGlEntriesQuery()
        ->orderBy('document_type')
        ->orderBy('entry_number')
        ->get();

    expect($glEntries)->toHaveCount(4)
        ->and($glEntries->pluck('document_number')->unique()->values()->all())->toContain($shipmentDocumentNumber, $postedInvoice->document_number)
        ->and($glEntries->where('posting_transaction_id', $shipmentValueEntry->posting_transaction_id))->toHaveCount(2)
        ->and($glEntries->where('document_number', $postedInvoice->document_number))->toHaveCount(2)
        ->and($glEntries->pluck('document_number')->all())->not->toContain('SI-UNRELATED')
        ->and($glEntries->where('document_type', 'COST_ADJUSTMENT'))->toHaveCount(0);

    salesOrderFilamentBankLedgerNumberSeries();
    $bankAccount = BankAccount::factory()->receiptOnly()->create([
        'gl_account_id' => salesOrderFilamentAccount('10600', 'Trace Bank', AccountCategory::ASSET)->id,
        'current_balance' => 1800,
        'available_balance' => 1800,
    ]);
    $payment = Payment::factory()->customerReceipt()->create([
        'party_id' => $fixture['customer']->id,
        'party_name' => $fixture['customer']->name,
        'bank_account_id' => $bankAccount->id,
        'payment_amount' => 50000,
        'payment_amount_lcy' => 50000,
        'applied_amount' => 0,
        'unapplied_amount' => 50000,
        'status' => 'APPROVED',
        'created_by' => $user->id,
    ]);

    app(PaymentService::class)->post($payment, $user->id);
    app(PaymentService::class)->applyToDocument($payment->fresh(), [
        'document_type' => 'SALES_INVOICE',
        'document_id' => $postedInvoice->id,
        'amount' => 1500,
    ], $user->id);

    $payment->refresh();
    $postedInvoice->refresh();
    $paymentBankEntry = $bankAccount->ledgerEntries()->where('document_no', $payment->payment_number)->firstOrFail();

    expect((float) $postedInvoice->remaining_amount)->toBe(0.0)
        ->and((float) $payment->applied_amount)->toBe(1500.0)
        ->and((float) $payment->unapplied_amount)->toBe(48500.0)
        ->and($paymentBankEntry->relatedGlEntriesQuery()->count())->toBe(2)
        ->and($paymentBankEntry->relatedCustomerLedgerEntriesQuery()->count())->toBe(1)
        ->and((float) $bankAccount->fresh()->current_balance)->toBe(51800.0);
});

it('traces customer payment bank ledger to customer ledger and payment gl entries without relying on direct links only', function (): void {
    $user = salesOrderFilamentUser();
    $fixture = salesOrderFilamentFixture();
    salesOrderFilamentBankLedgerNumberSeries();
    $bankAccount = BankAccount::factory()->receiptOnly()->create([
        'gl_account_id' => salesOrderFilamentAccount('10600', 'Trace Bank', AccountCategory::ASSET)->id,
        'current_balance' => 0,
        'available_balance' => 0,
    ]);
    $payment = Payment::factory()->customerReceipt()->create([
        'party_id' => $fixture['customer']->id,
        'party_name' => $fixture['customer']->name,
        'bank_account_id' => $bankAccount->id,
        'payment_amount' => 50000,
        'payment_amount_lcy' => 50000,
        'applied_amount' => 0,
        'unapplied_amount' => 50000,
        'status' => 'APPROVED',
        'created_by' => $user->id,
    ]);

    app(PaymentService::class)->post($payment, $user->id);

    $bankEntry = $bankAccount->ledgerEntries()->where('document_no', $payment->payment_number)->firstOrFail();
    $customerEntry = CustomerLedgerEntry::query()
        ->where('document_number', $payment->payment_number)
        ->where('customer_id', $fixture['customer']->id)
        ->firstOrFail();

    expect($bankEntry->gl_entry_id)->not->toBeNull()
        ->and($bankEntry->customer_ledger_entry_id)->toBe($customerEntry->id)
        ->and($customerEntry->gl_entry_id)->not->toBeNull()
        ->and($bankEntry->relatedGlEntriesQuery()->count())->toBe(2)
        ->and($bankEntry->relatedCustomerLedgerEntriesQuery()->pluck('id')->all())->toBe([$customerEntry->id])
        ->and(GlEntry::query()->where('cust_ledger_entry_id', $customerEntry->id)->count())->toBe(1);

    $historicalBankEntry = $bankEntry->replicate([
        'entry_number',
        'gl_entry_id',
        'customer_ledger_entry_id',
        'vendor_ledger_entry_id',
    ]);
    $historicalBankEntry->entry_number = $bankEntry->entry_number + 1000;
    $historicalBankEntry->gl_entry_id = null;
    $historicalBankEntry->customer_ledger_entry_id = null;
    $historicalBankEntry->vendor_ledger_entry_id = null;
    $historicalBankEntry->save();

    expect($historicalBankEntry->relatedGlEntriesQuery()->count())->toBe(2)
        ->and($historicalBankEntry->relatedCustomerLedgerEntriesQuery()->pluck('id')->all())->toBe([$customerEntry->id]);
});

function salesOrderFilamentUser(): User
{
    $role = Role::query()->firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);
    $user = User::factory()->create([
        'two_factor_secret' => 'TESTSECRET',
        'two_factor_confirmed_at' => now(),
    ]);
    $user->assignRole($role);

    return $user;
}

/**
 * @return array{
 *     businessGroup: GeneralBusinessPostingGroup,
 *     productGroup: GeneralProductPostingGroup,
 *     inventoryGroup: InventoryPostingGroup,
 *     customerPostingGroup: CustomerPostingGroup,
 *     location: Location,
 *     item: Item,
 *     customer: Customer
 * }
 */
function salesOrderFilamentFixture(): array
{
    $receivablesAccount = salesOrderFilamentAccount('11600', 'Filament Receivables', AccountCategory::RECEIVABLE);
    $inventoryAccount = salesOrderFilamentAccount('12600', 'Filament Inventory', AccountCategory::INVENTORY);
    $revenueAccount = salesOrderFilamentAccount('41600', 'Filament Revenue', AccountCategory::REVENUE);
    $cogsAccount = salesOrderFilamentAccount('51600', 'Filament COGS', AccountCategory::COGS);

    $businessGroup = GeneralBusinessPostingGroup::query()->create([
        'code' => 'SO-FIL',
        'description' => 'Sales Order Filament',
        'blocked' => false,
    ]);
    $productGroup = GeneralProductPostingGroup::query()->create([
        'code' => 'SO-FG',
        'description' => 'Sales Finished Goods',
        'blocked' => false,
    ]);
    $inventoryGroup = InventoryPostingGroup::query()->create([
        'code' => 'SO-FG',
        'description' => 'Sales Finished Goods',
        'blocked' => false,
    ]);
    $customerPostingGroup = CustomerPostingGroup::query()->create([
        'code' => 'SO-CUST',
        'description' => 'Sales Customers',
        'receivables_account_id' => $receivablesAccount->id,
        'blocked' => false,
    ]);
    $location = Location::factory()->create([
        'code' => 'SO-LOC',
        'name' => 'Sales Location',
    ]);

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
        'item_code' => '1000',
        'description' => 'Mai Sasanci',
        'item_type' => ItemType::FINISHED_GOOD,
        'base_uom_id' => $baseUom->id,
        'costing_method' => CostingMethod::FIFO->value,
        'unit_price' => 150,
        'unit_cost' => 50,
        'inventory' => 10,
        'general_product_posting_group_id' => $productGroup->id,
        'inventory_posting_group_id' => $inventoryGroup->id,
        'location_id' => $location->id,
    ]);
    ItemUomAssignment::query()->create([
        'item_id' => $item->id,
        'uom_id' => $baseUom->id,
        'uom_type' => 'SALES',
        'conversion_factor' => 1,
        'is_default' => true,
        'sort_order' => 1,
    ]);
    $customer = Customer::factory()->create([
        'name' => 'Danbaba Alaba',
        'general_business_posting_group_id' => $businessGroup->id,
        'customer_posting_group_id' => $customerPostingGroup->id,
        'vat_bus_posting_group' => null,
        'location_id' => $location->id,
        'is_price_inclusive' => false,
    ]);

    return compact('businessGroup', 'productGroup', 'inventoryGroup', 'customerPostingGroup', 'location', 'item', 'customer');
}

function salesOrderFilamentPayload(array $fixture, SalesOrderStatus $status): array
{
    return [
        'order_type' => SalesOrderType::SalesOrder->value,
        'status' => $status->value,
        'customer_id' => $fixture['customer']->id,
        'customer_name' => $fixture['customer']->name,
        'customer_address' => $fixture['customer']->address,
        'ship_to_name' => $fixture['customer']->name,
        'ship_to_address' => $fixture['customer']->address,
        'external_document_number' => null,
        'order_date' => '2026-08-23',
        'posting_date' => '2026-08-23',
        'shipment_date' => '2026-08-23',
        'location_id' => $fixture['location']->id,
        'currency_code' => 'NGN',
        'currency_factor' => 1,
        'general_business_posting_group_id' => $fixture['businessGroup']->id,
        'customer_posting_group_id' => $fixture['customerPostingGroup']->id,
        'invoice_discount_percent' => 0,
        'lines' => [[
            'item_id' => $fixture['item']->id,
            'item_code' => $fixture['item']->item_code,
            'description' => $fixture['item']->description,
            'quantity' => 10,
            'unit_of_measure_code' => 'PCS',
            'qty_per_unit_of_measure' => 1,
            'unit_price' => 150,
            'line_discount_percent' => 0,
            'price_source' => null,
            'pricing_master_id' => null,
        ]],
    ];
}

function salesOrderFilamentInboundLayer(array $fixture, float $quantity, float $cost): void
{
    ItemLedgerEntry::query()->create([
        'entry_type' => ItemLedgerEntryType::PURCHASE,
        'document_type' => 'PURCHASE_RECEIPT',
        'document_number' => 'PR-SO-FIL-001',
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
        'posting_date' => '2026-08-23',
        'entry_date' => now(),
        'open' => true,
    ]);
}

function salesOrderFilamentClosedInboundLayer(array $fixture, float $quantity, float $cost): void
{
    ItemLedgerEntry::query()->create([
        'entry_type' => ItemLedgerEntryType::PURCHASE,
        'document_type' => 'PURCHASE_RECEIPT',
        'document_number' => 'PR-SO-FIL-CLOSED-001',
        'document_line_number' => 10000,
        'item_id' => $fixture['item']->id,
        'location_id' => $fixture['location']->id,
        'quantity' => $quantity,
        'remaining_quantity' => 0,
        'cost_amount_actual' => $cost,
        'cost_amount_expected' => 0,
        'purchase_amount_actual' => $cost,
        'general_business_posting_group_id' => $fixture['businessGroup']->id,
        'general_product_posting_group_id' => $fixture['productGroup']->id,
        'inventory_posting_group_id' => $fixture['inventoryGroup']->id,
        'posting_date' => '2026-08-23',
        'entry_date' => now(),
        'open' => false,
    ]);
}

function salesOrderFilamentNumberSeries(): void
{
    $series = NumberSeries::query()->create([
        'code' => 'S-ORD',
        'description' => 'Sales Order test series',
        'prefix' => 'SO-',
        'starting_number' => 1,
        'current_number' => 0,
        'year' => 2026,
        'is_active' => true,
        'allow_manual' => false,
        'module' => 'sales',
    ]);

    NumberSeriesLine::query()->create([
        'number_series_id' => $series->id,
        'starting_date' => '2026-01-01',
        'prefix' => 'SO-2026-',
        'suffix' => '',
        'starting_no' => 0,
        'ending_no' => null,
        'increment_by' => 1,
        'last_no_used' => 0,
        'no_of_digits' => 5,
        'blocked' => false,
    ]);
}

function salesOrderFilamentInvoiceNumberSeries(): void
{
    $series = NumberSeries::query()->create([
        'code' => 'S-INV',
        'description' => 'Sales Invoice test series',
        'prefix' => 'SI-',
        'starting_number' => 1,
        'current_number' => 0,
        'year' => 2026,
        'is_active' => true,
        'allow_manual' => false,
        'module' => 'sales',
    ]);

    NumberSeriesLine::query()->create([
        'number_series_id' => $series->id,
        'starting_date' => '2026-01-01',
        'prefix' => 'SI-2026-',
        'suffix' => '',
        'starting_no' => 0,
        'ending_no' => null,
        'increment_by' => 1,
        'last_no_used' => 0,
        'no_of_digits' => 5,
        'blocked' => false,
    ]);
}

function salesOrderFilamentBankLedgerNumberSeries(): void
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
        ],
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
        ],
    );
}

function salesOrderFilamentAccount(string $number, string $name, AccountCategory $category): ChartOfAccount
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

function salesOrderFilamentCreateUnrelatedGlEntry(string $documentType, string $documentNumber, ?int $itemLedgerEntryId = null): GlEntry
{
    return GlEntry::query()->create([
        'entry_number' => app(LedgerSequenceAllocator::class)->nextGlEntryNumber(),
        'transaction_number' => app(LedgerSequenceAllocator::class)->nextGlTransactionNumber(),
        'chart_of_account_id' => salesOrderFilamentAccount('9'.fake()->unique()->numerify('####'), 'Unrelated Trace Account', AccountCategory::ASSET)->id,
        'debit_amount' => 1,
        'debit_amount_lcy' => 1,
        'credit_amount' => 0,
        'credit_amount_lcy' => 0,
        'amount' => 1,
        'amount_lcy' => 1,
        'source_type' => 'CUSTOMER',
        'source_module' => 'test',
        'source_number' => $documentNumber,
        'document_type' => $documentType,
        'document_number' => $documentNumber,
        'posting_date' => '2026-08-23',
        'document_date' => '2026-08-23',
        'description' => 'Unrelated trace entry',
        'item_ledger_entry_id' => $itemLedgerEntryId,
    ]);
}
