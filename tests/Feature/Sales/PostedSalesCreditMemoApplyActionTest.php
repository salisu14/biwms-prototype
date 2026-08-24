<?php

declare(strict_types=1);

use App\Enums\ApprovalStatus;
use App\Filament\Resources\SalesCreditMemos\Pages\ViewSalesCreditMemo;
use App\Filament\Resources\SalesInvoices\Pages\ViewPostedSalesCreditMemo;
use App\Filament\Resources\SalesInvoices\Pages\ViewPostedSalesInvoice;
use App\Models\CustomerLedgerApplication;
use App\Models\CustomerLedgerEntry;
use App\Models\GlEntry;
use App\Models\ItemLedgerEntry;
use App\Models\Permission;
use App\Models\PostedSalesInvoice;
use App\Models\Role;
use App\Models\SalesCreditMemo;
use App\Models\User;
use App\Models\ValueEntry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\PermissionRegistrar;

uses(RefreshDatabase::class);

it('shows the apply action only for authorized users with open posted sales credit memo credit', function (): void {
    $fixture = postedSalesCreditMemoApplyActionFixture($this, 300.00, 1000.00);
    $authorizedUser = postedSalesCreditMemoApplyActionUser();
    $unauthorizedUser = postedSalesCreditMemoApplyActionUser(canApply: false);

    Livewire::actingAs($authorizedUser)
        ->test(ViewPostedSalesCreditMemo::class, ['record' => $fixture['postedCreditMemo']])
        ->assertActionVisible('applyCreditMemo');

    Livewire::actingAs($unauthorizedUser)
        ->test(ViewPostedSalesCreditMemo::class, ['record' => $fixture['postedCreditMemo']])
        ->assertActionHidden('applyCreditMemo');

    $fixture['postedCreditMemo']->forceFill([
        'remaining_amount' => 0,
        'amount_applied' => 300.00,
        'fully_applied' => true,
    ])->save();

    Livewire::actingAs($authorizedUser)
        ->test(ViewPostedSalesCreditMemo::class, ['record' => $fixture['postedCreditMemo']])
        ->assertActionHidden('applyCreditMemo');
});

it('rejects unauthorized direct attempts to invoke the apply action', function (): void {
    $fixture = postedSalesCreditMemoApplyActionFixture($this, 300.00, 1000.00);
    $exception = null;

    try {
        Livewire::actingAs(postedSalesCreditMemoApplyActionUser(canApply: false))
            ->test(ViewPostedSalesCreditMemo::class, ['record' => $fixture['postedCreditMemo']])
            ->callAction('applyCreditMemo', data: [
                'target_invoice_id' => $fixture['postedInvoice']->id,
                'amount' => 100.00,
            ]);
    } catch (Throwable $caughtException) {
        $exception = $caughtException;
    }

    expect($exception)->not->toBeNull()
        ->and($exception->getMessage())->toContain('visible')
        ->and(CustomerLedgerApplication::query()->count())->toBe(0)
        ->and((float) $fixture['postedCreditMemo']->fresh()->remaining_amount)->toBe(300.00)
        ->and((float) $fixture['postedInvoice']->fresh()->remaining_amount)->toBe(1000.00);
});

it('filters eligible target invoices to open same customer posted invoices', function (): void {
    $fixture = postedSalesCreditMemoApplyActionFixture($this, 300.00, 1000.00);
    $sameCustomerOpenInvoice = $fixture['postedInvoice'];
    $paidFixture = postedSalesCreditMemoApplyActionFixture($this, 300.00, 1000.00);
    $otherCustomerFixture = postedSalesCreditMemoApplyActionFixture($this, 300.00, 1000.00);

    $paidFixture['postedInvoice']->forceFill([
        'customer_id' => $fixture['customer']->id,
        'remaining_amount' => 0,
        'paid_in_full' => true,
    ])->save();

    postedSalesCreditMemoApplyActionInvoiceEntry($paidFixture)->forceFill([
        'remaining_amount' => 0,
        'open' => false,
        'fully_applied' => true,
    ])->save();

    $component = Livewire::actingAs(postedSalesCreditMemoApplyActionUser())
        ->test(ViewPostedSalesCreditMemo::class, ['record' => $fixture['postedCreditMemo']])
        ->instance();

    $options = $component->eligiblePostedInvoiceOptions();

    expect($options)->toHaveKey($sameCustomerOpenInvoice->id)
        ->and($options)->not->toHaveKey($paidFixture['postedInvoice']->id)
        ->and($options)->not->toHaveKey($otherCustomerFixture['postedInvoice']->id);
});

it('shows the apply action on the normal posted sales credit memo view even when no eligible invoice exists', function (): void {
    $fixture = postedSalesCreditMemoApplyActionFixture($this, 300.00, 1000.00);
    postedSalesCreditMemoApplyActionCloseInvoice($fixture);

    Livewire::actingAs(postedSalesCreditMemoApplyActionUser())
        ->test(ViewSalesCreditMemo::class, ['record' => $fixture['salesCreditMemo']->getRouteKey()])
        ->assertActionVisible('applyCreditMemo')
        ->mountAction('applyCreditMemo')
        ->assertActionMounted('applyCreditMemo')
        ->assertMountedActionModalSee('No eligible open invoices are available for this customer.');

    expect(CustomerLedgerApplication::query()->count())->toBe(0)
        ->and((float) $fixture['postedCreditMemo']->fresh()->remaining_amount)->toBe(300.00)
        ->and((float) $fixture['postedInvoice']->fresh()->remaining_amount)->toBe(0.00);
});

it('filters eligible invoices on the normal sales credit memo view', function (): void {
    $fixture = postedSalesCreditMemoApplyActionFixture($this, 300.00, 1000.00);
    $otherCustomerFixture = postedSalesCreditMemoApplyActionFixture($this, 300.00, 1000.00);
    $paidFixture = postedSalesCreditMemoApplyActionFixture($this, 300.00, 1000.00);

    $paidFixture['postedInvoice']->forceFill([
        'customer_id' => $fixture['customer']->id,
        'remaining_amount' => 0,
        'paid_in_full' => true,
    ])->save();

    postedSalesCreditMemoApplyActionInvoiceEntry($paidFixture)->forceFill([
        'remaining_amount' => 0,
        'open' => false,
        'fully_applied' => true,
    ])->save();

    $component = Livewire::actingAs(postedSalesCreditMemoApplyActionUser())
        ->test(ViewSalesCreditMemo::class, ['record' => $fixture['salesCreditMemo']->getRouteKey()])
        ->instance();

    $options = $component->eligiblePostedInvoiceOptions();

    expect($options)->toHaveKey($fixture['postedInvoice']->id)
        ->and($options)->not->toHaveKey($paidFixture['postedInvoice']->id)
        ->and($options)->not->toHaveKey($otherCustomerFixture['postedInvoice']->id);
});

it('applies credit to a posted invoice through the Filament action without creating accounting rows', function (): void {
    $fixture = postedSalesCreditMemoApplyActionFixture($this, 300.00, 1000.00);
    $ledgerEntryCount = CustomerLedgerEntry::query()->count();
    $glEntryCount = GlEntry::query()->count();
    $itemLedgerEntryCount = ItemLedgerEntry::query()->count();
    $valueEntryCount = ValueEntry::query()->count();

    Livewire::actingAs(postedSalesCreditMemoApplyActionUser())
        ->test(ViewPostedSalesCreditMemo::class, ['record' => $fixture['postedCreditMemo']])
        ->callAction('applyCreditMemo', data: [
            'target_invoice_id' => $fixture['postedInvoice']->id,
            'amount' => 150.00,
        ])
        ->assertHasNoActionErrors();

    $fixture['postedCreditMemo']->refresh();
    $fixture['postedInvoice']->refresh();

    $application = CustomerLedgerApplication::query()->firstOrFail();

    expect((float) $fixture['postedCreditMemo']->amount_applied)->toBe(150.00)
        ->and((float) $fixture['postedCreditMemo']->remaining_amount)->toBe(150.00)
        ->and((float) $fixture['postedInvoice']->remaining_amount)->toBe(850.00)
        ->and((float) $application->amount)->toBe(150.00)
        ->and($application->source_posted_sales_credit_memo_id)->toBe($fixture['postedCreditMemo']->id)
        ->and($application->target_posted_sales_invoice_id)->toBe($fixture['postedInvoice']->id)
        ->and(CustomerLedgerEntry::query()->count())->toBe($ledgerEntryCount)
        ->and(CustomerLedgerEntry::query()->where('document_type', 'CREDIT_MEMO_APPLICATION')->count())->toBe(0)
        ->and(GlEntry::query()->count())->toBe($glEntryCount)
        ->and(ItemLedgerEntry::query()->count())->toBe($itemLedgerEntryCount)
        ->and(ValueEntry::query()->count())->toBe($valueEntryCount);
});

it('applies credit through the normal sales credit memo view without changing accounting economics', function (): void {
    $fixture = postedSalesCreditMemoApplyActionFixture($this, 300.00, 1000.00);
    $customerBalanceBefore = CustomerLedgerEntry::getBalance($fixture['customer']->id);
    $ledgerEntryCount = CustomerLedgerEntry::query()->count();
    $glEntryCount = GlEntry::query()->count();
    $itemLedgerEntryCount = ItemLedgerEntry::query()->count();
    $valueEntryCount = ValueEntry::query()->count();

    Livewire::actingAs(postedSalesCreditMemoApplyActionUser())
        ->test(ViewSalesCreditMemo::class, ['record' => $fixture['salesCreditMemo']->getRouteKey()])
        ->callAction('applyCreditMemo', data: [
            'target_invoice_id' => $fixture['postedInvoice']->id,
            'amount' => 300.00,
        ])
        ->assertHasNoActionErrors();

    $fixture['postedCreditMemo']->refresh();
    $fixture['postedInvoice']->refresh();

    expect((float) $fixture['postedCreditMemo']->remaining_amount)->toBe(0.00)
        ->and((float) $fixture['postedInvoice']->remaining_amount)->toBe(700.00)
        ->and(CustomerLedgerApplication::query()->count())->toBe(1)
        ->and(CustomerLedgerEntry::getBalance($fixture['customer']->id))->toBe($customerBalanceBefore)
        ->and(CustomerLedgerEntry::query()->count())->toBe($ledgerEntryCount)
        ->and(CustomerLedgerEntry::query()->where('document_type', 'CREDIT_MEMO_APPLICATION')->count())->toBe(0)
        ->and(GlEntry::query()->count())->toBe($glEntryCount)
        ->and(ItemLedgerEntry::query()->count())->toBe($itemLedgerEntryCount)
        ->and(ValueEntry::query()->count())->toBe($valueEntryCount);
});

it('hides the normal sales credit memo apply action for closed states and unauthorized users', function (): void {
    $fixture = postedSalesCreditMemoApplyActionFixture($this, 300.00, 1000.00);

    Livewire::actingAs(postedSalesCreditMemoApplyActionUser(canApply: false))
        ->test(ViewSalesCreditMemo::class, ['record' => $fixture['salesCreditMemo']->getRouteKey()])
        ->assertActionHidden('applyCreditMemo');

    foreach ([
        ['fully_applied' => true, 'remaining_amount' => 0],
        ['refunded' => true],
        ['corrected' => true],
    ] as $attributes) {
        $fixture = postedSalesCreditMemoApplyActionFixture($this, 300.00, 1000.00);
        $fixture['postedCreditMemo']->forceFill($attributes)->save();

        Livewire::actingAs(postedSalesCreditMemoApplyActionUser())
            ->test(ViewSalesCreditMemo::class, ['record' => $fixture['salesCreditMemo']->getRouteKey()])
            ->assertActionHidden('applyCreditMemo');
    }
});

it('renders canonical credit memo application history on the credit memo and invoice pages', function (): void {
    $fixture = postedSalesCreditMemoApplyActionFixture($this, 300.00, 1000.00);

    $fixture['postedCreditMemo']->applyToInvoices([
        [
            'invoice_id' => $fixture['postedInvoice']->id,
            'amount' => 300.00,
        ],
    ]);

    Livewire::actingAs(postedSalesCreditMemoApplyActionUser())
        ->test(ViewPostedSalesCreditMemo::class, ['record' => $fixture['postedCreditMemo']])
        ->assertSee($fixture['postedInvoice']->document_number)
        ->assertSee('Credit Before')
        ->assertSee('Invoice After')
        ->assertSee('NGN 300.00');

    Livewire::actingAs(postedSalesCreditMemoApplyActionUser())
        ->test(ViewPostedSalesInvoice::class, ['record' => $fixture['postedInvoice']])
        ->assertSee($fixture['postedCreditMemo']->document_number)
        ->assertSee('Credit Memo');
});

function postedSalesCreditMemoApplyActionFixture($testCase, float $creditAmount, float $invoiceAmount): array
{
    $fixtureFactory = Closure::bind(
        fn (float $amount): array => $this->createPostedSalesCreditMemoFixture($amount),
        $testCase,
        $testCase::class,
    );

    $fixture = $fixtureFactory($creditAmount);

    $fixture['salesCreditMemo'] = SalesCreditMemo::query()->create([
        'memo_number' => $fixture['postedCreditMemo']->document_number,
        'customer_id' => $fixture['customer']->id,
        'posted_sales_invoice_id' => $fixture['postedInvoice']->id,
        'status' => ApprovalStatus::POSTED,
        'effective_date' => now()->toDateString(),
        'currency_code' => 'NGN',
        'total_amount' => $creditAmount,
        'posted_by' => $fixture['user']->id,
        'posted_at' => now(),
    ]);

    $fixture['postedInvoice']->forceFill([
        'subtotal' => $invoiceAmount,
        'total_amount' => $invoiceAmount,
        'grand_total' => $invoiceAmount,
        'amount_paid' => 0,
        'remaining_amount' => $invoiceAmount,
        'paid_in_full' => false,
        'paid_in_full_date' => null,
        'cancelled' => false,
    ])->save();

    postedSalesCreditMemoApplyActionInvoiceEntry($fixture)->forceFill([
        'debit_amount' => $invoiceAmount,
        'credit_amount' => 0,
        'amount' => $invoiceAmount,
        'running_balance' => $invoiceAmount,
        'remaining_amount' => $invoiceAmount,
        'open' => true,
        'fully_applied' => false,
        'reversed' => false,
    ])->save();

    $fixture['documentEntry']->forceFill([
        'credit_amount' => $creditAmount,
        'amount' => -$creditAmount,
        'remaining_amount' => $creditAmount,
        'open' => true,
        'fully_applied' => false,
        'reversed' => false,
    ])->save();

    return $fixture;
}

function postedSalesCreditMemoApplyActionUser(bool $canApply = true): User
{
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    $permissions = [
        'sales.invoice.view_any',
        'sales.invoice.view',
        'sales.credit_memo.view_any',
        'sales.credit_memo.view',
        'sales.posted_sales_invoice.view_any',
        'sales.posted_sales_invoice.view',
    ];

    if ($canApply) {
        $permissions[] = 'sales.credit_memo.apply';
    }

    foreach ($permissions as $permission) {
        Permission::query()->firstOrCreate([
            'name' => $permission,
            'guard_name' => 'web',
        ]);
    }

    $role = Role::query()->firstOrCreate([
        'name' => 'sales-manager',
        'guard_name' => 'web',
    ]);

    $user = User::factory()->create();
    $user->assignRole($role);
    $user->givePermissionTo($permissions);

    app(PermissionRegistrar::class)->forgetCachedPermissions();

    return $user;
}

function postedSalesCreditMemoApplyActionInvoiceEntry(array $fixture): CustomerLedgerEntry
{
    return CustomerLedgerEntry::query()
        ->where('customer_id', $fixture['customer']->id)
        ->where('document_type', 'SALES_INVOICE')
        ->where('source_type', PostedSalesInvoice::class)
        ->where('source_id', $fixture['postedInvoice']->id)
        ->firstOrFail();
}

function postedSalesCreditMemoApplyActionCloseInvoice(array $fixture): void
{
    $fixture['postedInvoice']->forceFill([
        'amount_paid' => (float) $fixture['postedInvoice']->grand_total,
        'remaining_amount' => 0,
        'paid_in_full' => true,
        'paid_in_full_date' => now(),
    ])->save();

    postedSalesCreditMemoApplyActionInvoiceEntry($fixture)->forceFill([
        'remaining_amount' => 0,
        'open' => false,
        'fully_applied' => true,
    ])->save();
}
