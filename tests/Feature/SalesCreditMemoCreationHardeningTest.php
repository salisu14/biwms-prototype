<?php

declare(strict_types=1);

use App\Enums\ApprovalStatus;
use App\Enums\ItemType;
use App\Filament\Resources\SalesCreditMemos\Pages\CreateSalesCreditMemo;
use App\Filament\Resources\SalesCreditMemos\Pages\ViewSalesCreditMemo;
use App\Filament\Resources\SalesCreditMemos\SalesCreditMemoResource;
use App\Filament\Resources\SalesCreditMemos\Schemas\SalesCreditMemoForm;
use App\Models\ApprovalEntry;
use App\Models\Customer;
use App\Models\GlEntry;
use App\Models\Item;
use App\Models\ItemLedgerEntry;
use App\Models\NumberSeries;
use App\Models\NumberSeriesLine;
use App\Models\PostedSalesInvoice;
use App\Models\Role;
use App\Models\SalesCreditMemo;
use App\Models\SalesCreditMemoLine;
use App\Models\SalesInvoice;
use App\Models\User;
use App\Models\ValueEntry;
use App\Services\Approval\ApprovalService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

it('casts sales credit memo status to approval status draft', function (): void {
    $customer = Customer::factory()->create();

    $memo = SalesCreditMemo::query()->create([
        'memo_number' => 'SCM-CAST-001',
        'customer_id' => $customer->id,
        'status' => ApprovalStatus::DRAFT,
        'effective_date' => now()->toDateString(),
        'currency_code' => 'NGN',
        'total_amount' => 0,
    ]);

    expect($memo->fresh()->status)->toBe(ApprovalStatus::DRAFT);
});

it('does not allocate a sales credit memo number when opening the create page', function (): void {
    $user = salesCreditMemoCreationUser();
    salesCreditMemoCreationNumberSeries();

    $this->actingAs($user)
        ->withSession(['two_factor_passed_at' => now()->timestamp])
        ->get(SalesCreditMemoResource::getUrl('create'))
        ->assertSuccessful();

    expect(salesCreditMemoCreationNumberSeriesLine()->last_no_used)->toBe(0)
        ->and(SalesCreditMemo::query()->count())->toBe(0);
});

it('shows controlled feedback when sales credit memo number series is missing', function (): void {
    $user = salesCreditMemoCreationUser();
    $payload = salesCreditMemoCreationPayload();

    Livewire::actingAs($user)
        ->test(CreateSalesCreditMemo::class)
        ->fillForm($payload)
        ->call('create')
        ->assertNotified('Sales Credit Memo Number Series is not configured');

    expect(SalesCreditMemo::query()->count())->toBe(0)
        ->and(SalesCreditMemoLine::query()->count())->toBe(0);
});

it('creates a draft sales credit memo through the service owned Filament create flow', function (): void {
    $user = salesCreditMemoCreationUser();
    salesCreditMemoCreationNumberSeries();
    $payload = salesCreditMemoCreationPayload();

    Livewire::actingAs($user)
        ->test(CreateSalesCreditMemo::class)
        ->fillForm($payload)
        ->call('create')
        ->assertHasNoFormErrors();

    $memo = SalesCreditMemo::query()
        ->with('items')
        ->where('memo_number', 'SCM-000001')
        ->firstOrFail();

    expect($memo->memo_number)->toBe('SCM-000001')
        ->and($memo->status)->toBe(ApprovalStatus::DRAFT)
        ->and($memo->items)->toHaveCount(1)
        ->and((float) $memo->items->first()->quantity)->toBe(2.0)
        ->and((float) $memo->total_amount)->toBe(215.0)
        ->and(salesCreditMemoCreationNumberSeriesLine()->last_no_used)->toBe(1);
});

it('lists paid posted invoices for the selected customer and excludes unrelated customers', function (): void {
    $customer = Customer::factory()->create();
    $otherCustomer = Customer::factory()->create();
    $item = Item::factory()->create([
        'item_type' => ItemType::FINISHED_GOOD,
        'unit_price' => 150,
    ]);
    $paidInvoice = salesCreditMemoCreationPostedInvoice($customer, $item, 'S-INV-PAID-001', paid: true);
    $otherInvoice = salesCreditMemoCreationPostedInvoice($otherCustomer, $item, 'S-INV-OTHER-001', paid: true);

    $options = salesCreditMemoCreationPostedInvoiceOptions($customer->id);

    expect($options)->toHaveKey($paidInvoice->id)
        ->and($options[$paidInvoice->id])->toContain('S-INV-PAID-001')
        ->and($options[$paidInvoice->id])->toContain('PAID')
        ->and($options)->not->toHaveKey($otherInvoice->id);
});

it('creates a draft sales credit memo with posted invoice and posted invoice line lineage', function (): void {
    $user = salesCreditMemoCreationUser();
    salesCreditMemoCreationNumberSeries();
    $customer = Customer::factory()->create();
    $item = Item::factory()->create([
        'item_type' => ItemType::FINISHED_GOOD,
        'unit_price' => 150,
    ]);
    $postedInvoice = salesCreditMemoCreationPostedInvoice($customer, $item, 'S-INV-LINK-001', paid: true);
    $postedInvoiceLine = $postedInvoice->lines()->firstOrFail();

    Livewire::actingAs($user)
        ->test(CreateSalesCreditMemo::class)
        ->fillForm([
            'customer_id' => $customer->id,
            'sales_invoice_id' => null,
            'posted_sales_invoice_id' => $postedInvoice->id,
            'effective_date' => now()->toDateString(),
            'reason' => 'Customer return',
            'currency_code' => 'NGN',
            'items' => [[
                'posted_sales_invoice_line_id' => $postedInvoiceLine->id,
                'item_id' => $item->id,
                'description' => $item->description,
                'quantity' => 2,
                'unit_price' => 150,
                'vat_percent' => 0,
                'unit_of_measure_code' => $item->base_unit_of_measure,
                'qty_per_unit_of_measure' => 1,
            ]],
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $memo = SalesCreditMemo::query()
        ->with('items')
        ->where('memo_number', 'SCM-000001')
        ->firstOrFail();

    expect($memo->posted_sales_invoice_id)->toBe($postedInvoice->id)
        ->and($memo->sales_invoice_id)->toBeNull()
        ->and($memo->items)->toHaveCount(1)
        ->and($memo->items->first()->posted_sales_invoice_line_id)->toBe($postedInvoiceLine->id)
        ->and((float) $memo->items->first()->unit_price)->toBe(150.0)
        ->and((float) $memo->total_amount)->toBe(300.0);
});

it('renders posted original invoice number on the sales credit memo view page', function (): void {
    $user = salesCreditMemoCreationUser();
    $customer = Customer::factory()->create();
    $item = Item::factory()->create(['item_type' => ItemType::FINISHED_GOOD]);
    $postedInvoice = salesCreditMemoCreationPostedInvoice($customer, $item, 'S-INV-VIEW-001', paid: true);
    $postedInvoiceLine = $postedInvoice->lines()->firstOrFail();
    $memo = salesCreditMemoCreationLinkedMemo($customer, $postedInvoice, $postedInvoiceLine);

    Livewire::actingAs($user)
        ->test(ViewSalesCreditMemo::class, ['record' => $memo->getRouteKey()])
        ->assertSee('S-INV-VIEW-001')
        ->assertDontSee('No linked invoice');
});

it('renders legacy original invoice linkage and no-linkage placeholders safely', function (): void {
    $user = salesCreditMemoCreationUser();
    $customer = Customer::factory()->create();
    $legacyInvoice = SalesInvoice::query()->create([
        'invoice_number' => 'SI-LEGACY-001',
        'customer_id' => $customer->id,
        'status' => ApprovalStatus::APPROVED,
        'invoice_date' => now()->toDateString(),
        'due_date' => now()->addDays(7)->toDateString(),
        'currency_code' => 'NGN',
        'total_amount' => 100,
    ]);
    $legacyMemo = SalesCreditMemo::query()->create([
        'memo_number' => 'SCM-LEGACY-001',
        'customer_id' => $customer->id,
        'sales_invoice_id' => $legacyInvoice->id,
        'status' => ApprovalStatus::DRAFT,
        'effective_date' => now()->toDateString(),
        'currency_code' => 'NGN',
        'total_amount' => 100,
    ]);
    $unlinkedMemo = SalesCreditMemo::query()->create([
        'memo_number' => 'SCM-NOLINK-001',
        'customer_id' => $customer->id,
        'sales_invoice_id' => null,
        'posted_sales_invoice_id' => null,
        'status' => ApprovalStatus::DRAFT,
        'effective_date' => now()->toDateString(),
        'currency_code' => 'NGN',
        'total_amount' => 0,
    ]);

    Livewire::actingAs($user)
        ->test(ViewSalesCreditMemo::class, ['record' => $legacyMemo->getRouteKey()])
        ->assertSee('SI-LEGACY-001');

    Livewire::actingAs($user)
        ->test(ViewSalesCreditMemo::class, ['record' => $unlinkedMemo->getRouteKey()])
        ->assertSee('No linked invoice');
});

it('submits draft sales credit memo through canonical approval without posting side effects', function (): void {
    $user = salesCreditMemoCreationUser();
    $customer = Customer::factory()->create();
    $item = Item::factory()->create(['item_type' => ItemType::FINISHED_GOOD]);
    $postedInvoice = salesCreditMemoCreationPostedInvoice($customer, $item, 'S-INV-SUBMIT-001', paid: true);
    $postedInvoiceLine = $postedInvoice->lines()->firstOrFail();
    $memo = salesCreditMemoCreationLinkedMemo($customer, $postedInvoice, $postedInvoiceLine);

    $this->actingAs($user);

    app(ApprovalService::class)->submitForApproval($memo);

    $memo->refresh();

    expect($memo->status)->toBe(ApprovalStatus::APPROVED)
        ->and($memo->approver_id)->toBe($user->id)
        ->and($memo->approved_at)->not->toBeNull()
        ->and($memo->posted_sales_invoice_id)->toBe($postedInvoice->id)
        ->and($memo->items()->firstOrFail()->posted_sales_invoice_line_id)->toBe($postedInvoiceLine->id)
        ->and($postedInvoiceLine->fresh()->quantity)->toEqual($postedInvoiceLine->quantity)
        ->and(ItemLedgerEntry::query()->where('document_number', $memo->memo_number)->exists())->toBeFalse()
        ->and(ValueEntry::query()->where('document_no', $memo->memo_number)->exists())->toBeFalse()
        ->and(GlEntry::query()->where('document_number', $memo->memo_number)->exists())->toBeFalse();
});

it('failed approval does not partially mutate the sales credit memo', function (): void {
    $user = salesCreditMemoCreationUser();
    $customer = Customer::factory()->create();
    $memo = SalesCreditMemo::query()->create([
        'memo_number' => 'SCM-FAILED-001',
        'customer_id' => $customer->id,
        'status' => ApprovalStatus::PENDING,
        'effective_date' => now()->toDateString(),
        'currency_code' => 'NGN',
        'total_amount' => 100,
    ]);
    $entry = ApprovalEntry::query()->create([
        'approvable_type' => SalesCreditMemo::class,
        'approvable_id' => $memo->id,
        'sequence_no' => 1,
        'approver_id' => $user->id,
        'status' => 'approved',
    ]);

    $this->actingAs($user);

    expect(fn () => app(ApprovalService::class)->approve($entry))
        ->toThrow(RuntimeException::class, 'Approval entry is not in a state that can be approved.');

    $memo->refresh();

    expect($memo->status)->toBe(ApprovalStatus::PENDING)
        ->and($memo->approved_at)->toBeNull()
        ->and($memo->approver_id)->toBeNull()
        ->and(ItemLedgerEntry::query()->where('document_number', $memo->memo_number)->exists())->toBeFalse()
        ->and(ValueEntry::query()->where('document_no', $memo->memo_number)->exists())->toBeFalse()
        ->and(GlEntry::query()->where('document_number', $memo->memo_number)->exists())->toBeFalse();
});

function salesCreditMemoCreationUser(): User
{
    $role = Role::query()->firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);

    $user = User::factory()->create([
        'two_factor_secret' => 'TESTSECRET',
        'two_factor_confirmed_at' => now(),
    ]);

    $user->assignRole($role);

    return $user;
}

function salesCreditMemoCreationNumberSeries(): void
{
    $series = NumberSeries::query()->create([
        'code' => 'S-CM',
        'description' => 'Sales Credit Memo test series',
        'prefix' => 'SCM-',
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
        'starting_no' => 0,
        'ending_no' => null,
        'increment_by' => 1,
        'last_no_used' => 0,
        'no_of_digits' => 6,
        'prefix' => 'SCM-',
        'suffix' => '',
        'blocked' => false,
    ]);
}

function salesCreditMemoCreationNumberSeriesLine(): NumberSeriesLine
{
    return NumberSeriesLine::query()
        ->whereHas('series', fn ($query) => $query->where('code', 'S-CM'))
        ->firstOrFail();
}

/**
 * @return array<string, mixed>
 */
function salesCreditMemoCreationPayload(): array
{
    $customer = Customer::factory()->create();
    $item = Item::factory()->create([
        'item_type' => ItemType::FINISHED_GOOD,
        'unit_price' => 100,
    ]);

    return [
        'customer_id' => $customer->id,
        'sales_invoice_id' => null,
        'posted_sales_invoice_id' => null,
        'effective_date' => now()->toDateString(),
        'reason' => 'Customer return',
        'currency_code' => 'NGN',
        'items' => [[
            'item_id' => $item->id,
            'description' => $item->description,
            'quantity' => 2,
            'unit_price' => 100,
            'vat_percent' => 7.5,
            'unit_of_measure_code' => $item->base_unit_of_measure,
            'qty_per_unit_of_measure' => 1,
        ]],
    ];
}

function salesCreditMemoCreationPostedInvoice(Customer $customer, Item $item, string $documentNumber, bool $paid): PostedSalesInvoice
{
    $user = User::factory()->create();
    $grandTotal = 1500.0;

    $invoice = PostedSalesInvoice::query()->create([
        'document_number' => $documentNumber,
        'customer_id' => $customer->id,
        'customer_name' => $customer->name,
        'posting_date' => now()->toDateString(),
        'document_date' => now()->toDateString(),
        'due_date' => now()->addDays(7)->toDateString(),
        'subtotal' => $grandTotal,
        'total_amount' => $grandTotal,
        'grand_total' => $grandTotal,
        'currency_code' => 'NGN',
        'currency_factor' => 1,
        'amount_paid' => $paid ? $grandTotal : 0,
        'remaining_amount' => $paid ? 0 : $grandTotal,
        'paid_in_full' => $paid,
        'paid_in_full_date' => $paid ? now() : null,
        'posted_by' => $user->id,
        'posted_at' => now(),
        'cancelled' => false,
    ]);

    $invoice->lines()->create([
        'item_id' => $item->id,
        'item_code' => $item->item_code,
        'item_description' => $item->description,
        'posting_date' => now()->toDateString(),
        'quantity' => 10,
        'unit_of_measure_code' => $item->base_unit_of_measure,
        'qty_per_unit_of_measure' => 1,
        'quantity_base' => 10,
        'unit_price' => 150,
        'unit_cost' => 10,
        'unit_cost_lcy' => 10,
        'line_total' => $grandTotal,
        'line_amount' => $grandTotal,
        'vat_percentage' => 0,
        'vat_amount' => 0,
        'amount_including_vat' => $grandTotal,
        'cost_amount' => 100,
        'profit_amount' => 1400,
        'line_number' => 10000,
    ]);

    return $invoice;
}

/**
 * @return array<int, string>
 */
function salesCreditMemoCreationPostedInvoiceOptions(int $customerId): array
{
    $method = new ReflectionMethod(SalesCreditMemoForm::class, 'eligiblePostedInvoiceOptions');
    $method->setAccessible(true);

    return $method->invoke(null, $customerId);
}

function salesCreditMemoCreationLinkedMemo(
    Customer $customer,
    PostedSalesInvoice $postedInvoice,
    mixed $postedInvoiceLine,
): SalesCreditMemo {
    $memo = SalesCreditMemo::query()->create([
        'memo_number' => 'SCM-'.$postedInvoice->document_number,
        'customer_id' => $customer->id,
        'sales_invoice_id' => null,
        'posted_sales_invoice_id' => $postedInvoice->id,
        'status' => ApprovalStatus::DRAFT,
        'effective_date' => now()->toDateString(),
        'currency_code' => 'NGN',
        'total_amount' => 150,
    ]);

    $memo->items()->create([
        'item_id' => $postedInvoiceLine->item_id,
        'posted_sales_invoice_line_id' => $postedInvoiceLine->id,
        'quantity' => 1,
        'unit_of_measure_code' => $postedInvoiceLine->unit_of_measure_code,
        'unit_price' => 150,
    ]);

    return $memo;
}
