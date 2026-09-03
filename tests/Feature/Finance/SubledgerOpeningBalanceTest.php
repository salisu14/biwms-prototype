<?php

declare(strict_types=1);

use App\Enums\AccountCategory;
use App\Enums\IncomeBalanceType;
use App\Exceptions\BusinessException;
use App\Filament\Resources\Customers\Pages\ViewCustomer;
use App\Filament\Resources\SubledgerOpeningBalances\Pages\ListSubledgerOpeningBalances;
use App\Filament\Resources\SubledgerOpeningBalances\Pages\ViewSubledgerOpeningBalance;
use App\Filament\Resources\Vendors\Pages\ViewVendor;
use App\Models\AccountingPeriod;
use App\Models\Business;
use App\Models\ChartOfAccount;
use App\Models\CompanyInformation;
use App\Models\Customer;
use App\Models\GeneralLedgerSetup;
use App\Models\GlEntry;
use App\Models\NumberSeries;
use App\Models\NumberSeriesLine;
use App\Models\Permission;
use App\Models\PostingTransaction;
use App\Models\Role;
use App\Models\SubledgerOpeningBalance;
use App\Models\User;
use App\Models\Vendor;
use App\Models\VendorLedgerEntry;
use App\Models\VendorPostingGroup;
use App\Services\Finance\SubledgerOpeningBalanceService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Gate;
use Livewire\Livewire;
use Spatie\Permission\PermissionRegistrar;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->business = Business::query()->create(['code' => 'B-001', 'name' => 'Test Business']);
    session(['active_business_id' => $this->business->id]);

    AccountingPeriod::query()->create([
        'name' => 'FY2026',
        'start_date' => '2026-01-01',
        'end_date' => '2026-12-31',
    ]);
    GeneralLedgerSetup::instance()->update([
        'allow_posting_from' => '2026-01-01',
        'allow_posting_to' => '2026-12-31',
    ]);
    subledgerOpeningTestNumberSeries();
});

it('creates and posts a balanced customer opening balance idempotently', function (): void {
    $user = subledgerOpeningUser();
    $customer = Customer::factory()->create();
    $equity = subledgerOpeningEquity();
    GeneralLedgerSetup::instance()->update(['opening_balance_equity_account_id' => $equity->id]);

    $opening = app(SubledgerOpeningBalanceService::class)->createDraft([
        'business_id' => $this->business->id,
        'party_type' => 'CUSTOMER',
        'party_id' => $customer->id,
        'original_amount' => '1500.00',
        'currency_code' => 'NGN',
        'currency_factor' => '1',
        'posting_date' => '2026-08-30',
        'document_date' => '2026-08-30',
        'description' => 'Customer cutover balance',
    ], $user->id);

    expect($opening->status)->toBe(SubledgerOpeningBalance::STATUS_DRAFT)
        ->and($opening->document_number)->toBe('00001');

    expect($user->can('finance.subledger_opening_balance.post'))->toBeTrue()
        ->and(Gate::forUser($user)->allows('post', $opening))->toBeTrue();

    $posted = app(SubledgerOpeningBalanceService::class)->post($opening, $user->id);
    $retry = app(SubledgerOpeningBalanceService::class)->post($posted, $user->id);

    expect($posted->fresh()->status)->toBe(SubledgerOpeningBalance::STATUS_POSTED)
        ->and($retry->id)->toBe($posted->id)
        ->and($posted->customerLedgerEntry)->not->toBeNull()
        ->and($posted->postingTransaction->glEntries)->toHaveCount(2)
        ->and((float) $posted->postingTransaction->glEntries->sum('debit_amount'))->toBe(1500.0)
        ->and((float) $posted->postingTransaction->glEntries->sum('credit_amount'))->toBe(1500.0)
        ->and($posted->customerLedgerEntry->gl_entry_id)->not->toBeNull();

    $controlLine = $posted->postingTransaction->glEntries->firstWhere('chart_of_account_id', $posted->control_account_id);
    expect($controlLine?->cust_ledger_entry_id)->toBe($posted->customer_ledger_entry_id);
});

it('creates and posts a vendor opening balance with the opposite subledger direction', function (): void {
    $user = subledgerOpeningUser();
    $payables = ChartOfAccount::factory()->create([
        'account_category' => AccountCategory::LIABILITY,
        'income_balance' => IncomeBalanceType::BALANCE_SHEET,
    ]);
    $vendor = Vendor::factory()->create();
    VendorPostingGroup::query()->findOrFail($vendor->vendor_posting_group_id)->update(['payables_account_id' => $payables->id]);
    $equity = subledgerOpeningEquity();
    GeneralLedgerSetup::instance()->update(['opening_balance_equity_account_id' => $equity->id]);

    $opening = app(SubledgerOpeningBalanceService::class)->createDraft([
        'business_id' => $this->business->id,
        'party_type' => 'VENDOR',
        'party_id' => $vendor->id,
        'original_amount' => '2500.00',
        'currency_code' => 'NGN',
        'currency_factor' => '1',
        'posting_date' => '2026-08-30',
        'document_date' => '2026-08-30',
    ], $user->id);

    $posted = app(SubledgerOpeningBalanceService::class)->post($opening, $user->id);

    expect($posted->vendorLedgerEntry)->not->toBeNull()
        ->and((float) $posted->vendorLedgerEntry->amount)->toBe(-2500.0)
        ->and((float) $posted->postingTransaction->glEntries->sum('debit_amount'))->toBe(2500.0)
        ->and((float) $posted->postingTransaction->glEntries->sum('credit_amount'))->toBe(2500.0)
        ->and((float) $posted->postingTransaction->glEntries->firstWhere('chart_of_account_id', $equity->id)->debit_amount)->toBe(2500.0)
        ->and((float) $posted->postingTransaction->glEntries->firstWhere('chart_of_account_id', $payables->id)->credit_amount)->toBe(2500.0);
});

it('reverses a posted opening balance without mutating its original ledger facts', function (): void {
    $user = subledgerOpeningUser();
    $customer = Customer::factory()->create();
    $equity = subledgerOpeningEquity();
    GeneralLedgerSetup::instance()->update(['opening_balance_equity_account_id' => $equity->id]);
    $opening = app(SubledgerOpeningBalanceService::class)->post(
        app(SubledgerOpeningBalanceService::class)->createDraft([
            'business_id' => $this->business->id,
            'party_type' => 'CUSTOMER',
            'party_id' => $customer->id,
            'original_amount' => 800,
            'posting_date' => '2026-08-30',
            'document_date' => '2026-08-30',
        ], $user->id),
        $user->id,
    );
    $originalLedgerId = $opening->customer_ledger_entry_id;

    $reversed = app(SubledgerOpeningBalanceService::class)->reverse($opening, 'Cutover correction', $user->id);

    expect($reversed->status)->toBe(SubledgerOpeningBalance::STATUS_REVERSED)
        ->and($reversed->customerLedgerEntry->id)->toBe($originalLedgerId)
        ->and($reversed->customerLedgerEntry->reversed)->toBeTrue()
        ->and(GlEntry::query()->where('document_number', 'REV-'.$opening->document_number)->count())->toBe(2);

    expect(fn () => $reversed->fresh()->update(['description' => 'Tamper']))
        ->toThrow(BusinessException::class, 'immutable');
});

it('keeps view actions aligned with draft, posted, and reversed lifecycle states', function (): void {
    $user = subledgerOpeningUser();
    $customer = Customer::factory()->create();
    $equity = subledgerOpeningEquity();
    GeneralLedgerSetup::instance()->update(['opening_balance_equity_account_id' => $equity->id]);
    $service = app(SubledgerOpeningBalanceService::class);
    $draft = $service->createDraft([
        'business_id' => $this->business->id,
        'party_type' => 'CUSTOMER',
        'party_id' => $customer->id,
        'original_amount' => 800,
        'posting_date' => '2026-08-30',
        'document_date' => '2026-08-30',
    ], $user->id);

    $draftPage = Livewire::actingAs($user)
        ->test(ViewSubledgerOpeningBalance::class, ['record' => $draft->id])
        ->assertSee('DRAFT')
        ->assertActionVisible('edit')
        ->assertActionVisible('post')
        ->assertActionHidden('reverse')
        ->callAction('post', data: ['security_password_confirmation' => 'password'])
        ->assertSee('POSTED')
        ->assertActionHidden('edit')
        ->assertActionHidden('post')
        ->assertActionVisible('reverse');

    $draftPage
        ->callAction('reverse', data: [
            'reason' => 'Lifecycle test',
            'security_password_confirmation' => 'password',
        ])
        ->assertSee('REVERSED')
        ->assertActionHidden('edit')
        ->assertActionHidden('post')
        ->assertActionHidden('reverse');

    expect($draft->fresh()->status)->toBe(SubledgerOpeningBalance::STATUS_REVERSED);
});

it('keeps vendor view actions aligned through the complete lifecycle', function (): void {
    $user = subledgerOpeningUser();
    $vendor = Vendor::factory()->create();
    $payables = ChartOfAccount::factory()->create([
        'account_category' => AccountCategory::LIABILITY,
        'income_balance' => IncomeBalanceType::BALANCE_SHEET,
    ]);
    VendorPostingGroup::query()->findOrFail($vendor->vendor_posting_group_id)->update([
        'payables_account_id' => $payables->id,
    ]);
    $equity = subledgerOpeningEquity();
    GeneralLedgerSetup::instance()->update(['opening_balance_equity_account_id' => $equity->id]);
    $service = app(SubledgerOpeningBalanceService::class);
    $draft = $service->createDraft([
        'business_id' => $this->business->id,
        'party_type' => 'VENDOR',
        'party_id' => $vendor->id,
        'original_amount' => 2500,
        'currency_code' => 'NGN',
        'currency_factor' => 1,
        'posting_date' => '2026-08-30',
        'document_date' => '2026-08-30',
    ], $user->id);

    $vendorPage = Livewire::actingAs($user)
        ->test(ViewSubledgerOpeningBalance::class, ['record' => $draft->id])
        ->assertSee('DRAFT')
        ->assertActionVisible('edit')
        ->assertActionVisible('post')
        ->assertActionHidden('reverse')
        ->callAction('post', data: ['security_password_confirmation' => 'password'])
        ->assertSee('POSTED')
        ->assertActionHidden('edit')
        ->assertActionHidden('post')
        ->assertActionVisible('reverse');

    $vendorPage
        ->callAction('reverse', data: [
            'reason' => 'Vendor lifecycle test',
            'security_password_confirmation' => 'password',
        ])
        ->assertSee('REVERSED')
        ->assertActionHidden('edit')
        ->assertActionHidden('post')
        ->assertActionHidden('reverse');

    expect($draft->fresh()->party_type)->toBe('VENDOR')
        ->and($draft->fresh()->status)->toBe(SubledgerOpeningBalance::STATUS_REVERSED);
});

it('resolves a posted vendor opening balance without an active session context', function (): void {
    $user = subledgerOpeningUser();
    $user->givePermissionTo([
        Permission::query()->firstOrCreate(['name' => 'procurement.vendor.view_any', 'guard_name' => 'web']),
        Permission::query()->firstOrCreate(['name' => 'procurement.vendor.view', 'guard_name' => 'web']),
    ]);
    $vendor = Vendor::factory()->create();
    $payables = ChartOfAccount::factory()->create([
        'account_category' => AccountCategory::LIABILITY,
        'income_balance' => IncomeBalanceType::BALANCE_SHEET,
    ]);
    VendorPostingGroup::query()->findOrFail($vendor->vendor_posting_group_id)->update([
        'payables_account_id' => $payables->id,
    ]);
    $equity = subledgerOpeningEquity();
    GeneralLedgerSetup::instance()->update(['opening_balance_equity_account_id' => $equity->id]);
    $service = app(SubledgerOpeningBalanceService::class);
    $opening = $service->post($service->createDraft([
        'business_id' => $this->business->id,
        'party_type' => 'VENDOR',
        'party_id' => $vendor->id,
        'original_amount' => 2500,
        'posting_date' => '2026-08-30',
        'document_date' => '2026-08-30',
    ], $user->id), $user->id);

    session()->forget('active_business_id');

    $this->actingAs($user)
        ->get('/admin/vendors/'.$vendor->id.'?business_id='.$this->business->id)
        ->assertOk()
        ->assertSee('View Opening Balance')
        ->assertDontSee('Enter Opening Balance');

    expect($opening->status)->toBe(SubledgerOpeningBalance::STATUS_POSTED);
});

it('formats vendor financial summaries in the business base currency', function (): void {
    $user = subledgerOpeningUser();
    $user->givePermissionTo([
        Permission::query()->firstOrCreate(['name' => 'procurement.vendor.view_any', 'guard_name' => 'web']),
        Permission::query()->firstOrCreate(['name' => 'procurement.vendor.view', 'guard_name' => 'web']),
    ]);
    CompanyInformation::query()->create([
        'business_id' => $this->business->id,
        'company_name' => 'Test Business',
        'base_currency_code' => 'NGN',
    ]);
    $vendor = Vendor::factory()->create(['currency' => 'USD']);
    $payables = ChartOfAccount::factory()->create([
        'account_category' => AccountCategory::LIABILITY,
        'income_balance' => IncomeBalanceType::BALANCE_SHEET,
    ]);
    VendorPostingGroup::query()->findOrFail($vendor->vendor_posting_group_id)->update([
        'payables_account_id' => $payables->id,
    ]);
    $equity = subledgerOpeningEquity();
    GeneralLedgerSetup::instance()->update(['opening_balance_equity_account_id' => $equity->id]);
    $service = app(SubledgerOpeningBalanceService::class);
    $opening = $service->post($service->createDraft([
        'business_id' => $this->business->id,
        'party_type' => 'VENDOR',
        'party_id' => $vendor->id,
        'original_amount' => 70,
        'currency_code' => 'USD',
        'currency_factor' => 1450,
        'posting_date' => '2026-08-30',
        'document_date' => '2026-08-30',
    ], $user->id), $user->id);

    $this->actingAs($user)
        ->get('/admin/vendors/'.$vendor->id.'?business_id='.$this->business->id)
        ->assertOk()
        ->assertSee('NGN')
        ->assertSee('101,500.00')
        ->assertDontSee('$101,500.00');

    expect((float) $opening->amount_lcy)->toBe(101500.0);
});

it('reverses a long-number vendor opening without truncation overflow', function (): void {
    $user = subledgerOpeningUser();
    NumberSeries::query()->where('code', 'VENDOR-OPENING')->firstOrFail()->lines()->update([
        'prefix' => 'VENOPB-2026-',
    ]);
    $vendor = Vendor::factory()->create();
    $payables = ChartOfAccount::factory()->create([
        'account_category' => AccountCategory::LIABILITY,
        'income_balance' => IncomeBalanceType::BALANCE_SHEET,
    ]);
    VendorPostingGroup::query()->findOrFail($vendor->vendor_posting_group_id)->update([
        'payables_account_id' => $payables->id,
    ]);
    $equity = subledgerOpeningEquity();
    GeneralLedgerSetup::instance()->update(['opening_balance_equity_account_id' => $equity->id]);
    $service = app(SubledgerOpeningBalanceService::class);
    $opening = $service->post($service->createDraft([
        'business_id' => $this->business->id,
        'party_type' => 'VENDOR',
        'party_id' => $vendor->id,
        'original_amount' => 101500,
        'currency_code' => 'NGN',
        'posting_date' => '2026-08-30',
        'document_date' => '2026-08-30',
    ], $user->id), $user->id);

    $reversed = $service->reverse($opening, 'Legacy correction', $user->id);
    $reversal = VendorLedgerEntry::query()
        ->where('vendor_id', $vendor->id)
        ->where('document_number', 'REV-VENOPB-2026-0000')
        ->firstOrFail();
    $transaction = PostingTransaction::query()->findOrFail($reversal->glEntry->posting_transaction_id);

    expect($reversal->document_number)->toBe('REV-VENOPB-2026-0000')
        ->and($transaction->glEntries)->toHaveCount(2)
        ->and((float) $transaction->glEntries->where('chart_of_account_id', $payables->id)->sum('debit_amount'))->toBe(101500.0)
        ->and((float) $transaction->glEntries->where('chart_of_account_id', $equity->id)->sum('credit_amount'))->toBe(101500.0)
        ->and($reversed->status)->toBe(SubledgerOpeningBalance::STATUS_REVERSED);
});

it('keeps foreign-currency customer and vendor opening balances auditable through reversal', function (): void {
    $user = subledgerOpeningUser();
    $user->givePermissionTo(Permission::query()->firstOrCreate([
        'name' => 'finance.subledger_opening_balance.view_any',
        'guard_name' => 'web',
    ]));
    CompanyInformation::query()->create([
        'business_id' => $this->business->id,
        'company_name' => 'Test Business',
        'base_currency_code' => 'NGN',
    ]);

    $equity = subledgerOpeningEquity();
    GeneralLedgerSetup::instance()->update(['opening_balance_equity_account_id' => $equity->id]);
    $vendor = Vendor::factory()->create(['currency' => 'USD']);
    $vendorPayables = ChartOfAccount::factory()->create([
        'account_category' => AccountCategory::LIABILITY,
        'income_balance' => IncomeBalanceType::BALANCE_SHEET,
    ]);
    VendorPostingGroup::query()->findOrFail($vendor->vendor_posting_group_id)->update([
        'payables_account_id' => $vendorPayables->id,
    ]);
    $service = app(SubledgerOpeningBalanceService::class);

    $vendorOpening = $service->post($service->createDraft([
        'business_id' => $this->business->id,
        'party_type' => 'VENDOR',
        'party_id' => $vendor->id,
        'original_amount' => 70,
        'currency_code' => 'USD',
        'currency_factor' => 1450,
        'posting_date' => '2026-08-30',
        'document_date' => '2026-08-30',
    ], $user->id), $user->id);
    expect((float) $vendorOpening->vendorLedgerEntry->remaining_amount)->toBe(101500.0)
        ->and((float) $vendorOpening->vendorLedgerEntry->original_credit_amount)->toBe(70.0)
        ->and((float) $vendor->fresh()->balance)->toBe(-101500.0);

    Livewire::actingAs($user)
        ->test(ListSubledgerOpeningBalances::class)
        ->assertSee('USD 70.00')
        ->assertSee('101,500.00')
        ->assertSee('NGN');

    $vendorReversal = $service->reverse($vendorOpening, 'FX correction', $user->id);
    $vendorReversalEntry = VendorLedgerEntry::query()
        ->where('vendor_id', $vendor->id)
        ->where('document_number', 'REV-'.$vendorOpening->document_number)
        ->firstOrFail();
    expect((float) $vendor->fresh()->balance)->toBe(0.0)
        ->and((float) $vendor->fresh()->open_balance)->toBe(0.0)
        ->and((float) $vendor->fresh()->overdue_balance)->toBe(0.0)
        ->and((float) $vendorReversalEntry->original_debit_amount)->toBe(70.0)
        ->and((float) $vendorReversalEntry->original_credit_amount)->toBe(0.0)
        ->and($vendorReversalEntry->currency_code)->toBe('USD')
        ->and($vendorReversal->status)->toBe(SubledgerOpeningBalance::STATUS_REVERSED);
});

it('keeps foreign-currency customer opening balance auditable through reversal', function (): void {
    $user = subledgerOpeningUser();
    $equity = subledgerOpeningEquity();
    GeneralLedgerSetup::instance()->update(['opening_balance_equity_account_id' => $equity->id]);
    $customer = Customer::factory()->create();
    $receivables = ChartOfAccount::factory()->create([
        'account_category' => AccountCategory::ASSET,
        'income_balance' => IncomeBalanceType::BALANCE_SHEET,
    ]);
    $customer->customerPostingGroup->update(['receivables_account_id' => $receivables->id]);
    $service = app(SubledgerOpeningBalanceService::class);
    $opening = $service->post($service->createDraft([
        'business_id' => $this->business->id,
        'party_type' => 'CUSTOMER',
        'party_id' => $customer->id,
        'original_amount' => 70,
        'currency_code' => 'USD',
        'currency_factor' => 1450,
        'posting_date' => '2026-08-30',
        'document_date' => '2026-08-30',
    ], $user->id), $user->id);

    $service->reverse($opening, 'FX correction', $user->id);

    expect((float) $customer->fresh()->balance)->toBe(0.0)
        ->and((float) $customer->fresh()->open_balance)->toBe(0.0)
        ->and((float) $opening->customerLedgerEntry->original_debit_amount)->toBe(70.0)
        ->and($opening->customerLedgerEntry->currency_code)->toBe('USD');
});

it('rejects cross-business creation at the service boundary', function (): void {
    $user = subledgerOpeningUser();
    $otherBusiness = Business::query()->create(['code' => 'B-002', 'name' => 'Other Business']);
    $customer = Customer::factory()->create();

    expect(fn () => app(SubledgerOpeningBalanceService::class)->createDraft([
        'business_id' => $otherBusiness->id,
        'party_type' => 'CUSTOMER',
        'party_id' => $customer->id,
        'original_amount' => 100,
        'posting_date' => '2026-08-30',
        'document_date' => '2026-08-30',
    ], $user->id))->toThrow(BusinessException::class, 'active business');
});

it('allows only one draft or posted opening balance per party and business', function (): void {
    $user = subledgerOpeningUser();
    $customer = Customer::factory()->create();
    $equity = subledgerOpeningEquity();
    GeneralLedgerSetup::instance()->update(['opening_balance_equity_account_id' => $equity->id]);
    $service = app(SubledgerOpeningBalanceService::class);
    $payload = [
        'business_id' => $this->business->id,
        'party_type' => 'CUSTOMER',
        'party_id' => $customer->id,
        'original_amount' => 100,
        'posting_date' => '2026-08-30',
        'document_date' => '2026-08-30',
    ];

    $draft = $service->createDraft($payload, $user->id);

    expect(fn () => $service->createDraft($payload, $user->id))
        ->toThrow(BusinessException::class, 'active CUSTOMER opening balance');

    $posted = $service->post($draft, $user->id);
    expect(fn () => $service->createDraft($payload, $user->id))
        ->toThrow(BusinessException::class, 'active CUSTOMER opening balance');

    $service->reverse($posted, 'Controlled replacement', $user->id);
    expect($service->createDraft($payload, $user->id)->status)
        ->toBe(SubledgerOpeningBalance::STATUS_DRAFT);
});

it('exposes lifecycle-aware opening balance actions on customer and vendor profiles', function (): void {
    $user = subledgerOpeningUser();
    $user->givePermissionTo([
        Permission::query()->firstOrCreate(['name' => 'sales.customer.view_any', 'guard_name' => 'web']),
        Permission::query()->firstOrCreate(['name' => 'sales.customer.view', 'guard_name' => 'web']),
        Permission::query()->firstOrCreate(['name' => 'procurement.vendor.view_any', 'guard_name' => 'web']),
        Permission::query()->firstOrCreate(['name' => 'procurement.vendor.view', 'guard_name' => 'web']),
    ]);
    $equity = subledgerOpeningEquity();
    GeneralLedgerSetup::instance()->update(['opening_balance_equity_account_id' => $equity->id]);
    $customer = Customer::factory()->create();
    $vendor = Vendor::factory()->create();
    $payables = ChartOfAccount::factory()->create([
        'account_category' => AccountCategory::LIABILITY,
        'income_balance' => IncomeBalanceType::BALANCE_SHEET,
    ]);
    VendorPostingGroup::query()->findOrFail($vendor->vendor_posting_group_id)->update([
        'payables_account_id' => $payables->id,
    ]);
    $service = app(SubledgerOpeningBalanceService::class);

    Livewire::actingAs($user)
        ->test(ViewCustomer::class, ['record' => $customer->id])
        ->assertActionVisible('enterOpeningBalance')
        ->assertActionHidden('viewOpeningBalance');

    $customerOpening = $service->createDraft([
        'business_id' => $this->business->id,
        'party_type' => 'CUSTOMER',
        'party_id' => $customer->id,
        'original_amount' => 100,
        'posting_date' => '2026-08-30',
        'document_date' => '2026-08-30',
    ], $user->id);

    Livewire::actingAs($user)
        ->test(ViewCustomer::class, ['record' => $customer->id])
        ->assertActionHidden('enterOpeningBalance')
        ->assertActionVisible('viewOpeningBalance')
        ->assertActionHasLabel('viewOpeningBalance', 'Continue Opening Balance');

    NumberSeries::query()->where('code', 'VENDOR-OPENING')->firstOrFail()->lines()->update(['prefix' => 'VOB-']);

    Livewire::actingAs($user)
        ->test(ViewVendor::class, ['record' => $vendor->id])
        ->assertActionVisible('enterOpeningBalance')
        ->assertActionHidden('viewOpeningBalance');

    $vendorOpening = $service->createDraft([
        'business_id' => $this->business->id,
        'party_type' => 'VENDOR',
        'party_id' => $vendor->id,
        'original_amount' => 100,
        'posting_date' => '2026-08-30',
        'document_date' => '2026-08-30',
    ], $user->id);

    Livewire::actingAs($user)
        ->test(ViewVendor::class, ['record' => $vendor->id])
        ->assertActionHidden('enterOpeningBalance')
        ->assertActionVisible('viewOpeningBalance')
        ->assertActionHasLabel('viewOpeningBalance', 'Continue Opening Balance');

    expect($customerOpening->status)->toBe(SubledgerOpeningBalance::STATUS_DRAFT)
        ->and($vendorOpening->status)->toBe(SubledgerOpeningBalance::STATUS_DRAFT);
});

it('rejects an unauthorized user at the subledger opening post boundary', function (): void {
    $authorized = subledgerOpeningUser();
    $customer = Customer::factory()->create();
    $equity = subledgerOpeningEquity();
    GeneralLedgerSetup::instance()->update(['opening_balance_equity_account_id' => $equity->id]);
    $opening = app(SubledgerOpeningBalanceService::class)->createDraft([
        'business_id' => $this->business->id,
        'party_type' => 'CUSTOMER',
        'party_id' => $customer->id,
        'original_amount' => 100,
        'posting_date' => '2026-08-30',
        'document_date' => '2026-08-30',
    ], $authorized->id);
    $unauthorized = User::factory()->create();

    expect(fn () => app(SubledgerOpeningBalanceService::class)->post($opening, $unauthorized->id))
        ->toThrow(AuthorizationException::class);
});

it('keeps customer and vendor opening permissions party-specific', function (): void {
    $customerUser = User::factory()->create();
    $customerPermission = Permission::query()->firstOrCreate([
        'name' => 'finance.customer_opening_balance.create',
        'guard_name' => 'web',
    ]);
    $customerUser->givePermissionTo($customerPermission);

    $vendorUser = User::factory()->create();
    $vendorPermission = Permission::query()->firstOrCreate([
        'name' => 'finance.vendor_opening_balance.create',
        'guard_name' => 'web',
    ]);
    $vendorUser->givePermissionTo($vendorPermission);

    expect(Gate::forUser($customerUser)->allows('createCustomer', SubledgerOpeningBalance::class))->toBeTrue()
        ->and(Gate::forUser($customerUser)->allows('createVendor', SubledgerOpeningBalance::class))->toBeFalse()
        ->and(Gate::forUser($vendorUser)->allows('createVendor', SubledgerOpeningBalance::class))->toBeTrue()
        ->and(Gate::forUser($vendorUser)->allows('createCustomer', SubledgerOpeningBalance::class))->toBeFalse();
});

it('adds approved subledger permissions without removing unrelated role permissions', function (): void {
    $permissionNames = [
        'finance.subledger_opening_balance.view_any',
        'finance.subledger_opening_balance.view',
        'finance.subledger_opening_balance.create',
        'finance.subledger_opening_balance.update',
        'finance.subledger_opening_balance.delete',
        'finance.subledger_opening_balance.delete_any',
        'finance.subledger_opening_balance.restore',
        'finance.subledger_opening_balance.restore_any',
        'finance.subledger_opening_balance.force_delete',
        'finance.subledger_opening_balance.force_delete_any',
        'finance.subledger_opening_balance.post',
        'finance.subledger_opening_balance.reverse',
    ];
    foreach ([...$permissionNames, 'finance.unrelated.existing'] as $name) {
        Permission::query()->firstOrCreate(['name' => $name, 'guard_name' => 'web']);
    }
    $accountant = Role::query()->create(['name' => 'finance-accountant', 'guard_name' => 'web']);
    $manager = Role::query()->create(['name' => 'finance-manager', 'guard_name' => 'web']);
    $admin = Role::query()->create(['name' => 'admin', 'guard_name' => 'web']);
    Role::query()->create(['name' => 'super_admin', 'guard_name' => 'web']);
    $accountant->givePermissionTo('finance.unrelated.existing');

    expect(Artisan::call('biwms:subledger-opening-permissions-sync', ['--dry-run' => true]))->toBe(0)
        ->and($accountant->fresh()->hasPermissionTo('finance.subledger_opening_balance.post'))->toBeFalse()
        ->and(Artisan::call('biwms:subledger-opening-permissions-sync', ['--apply' => true]))->toBe(0)
        ->and($accountant->fresh()->hasPermissionTo('finance.subledger_opening_balance.post'))->toBeTrue()
        ->and($accountant->fresh()->hasPermissionTo('finance.subledger_opening_balance.delete'))->toBeFalse()
        ->and($accountant->fresh()->hasPermissionTo('finance.unrelated.existing'))->toBeTrue()
        ->and($manager->fresh()->hasPermissionTo('finance.subledger_opening_balance.delete'))->toBeTrue()
        ->and($manager->fresh()->hasPermissionTo('finance.subledger_opening_balance.force_delete'))->toBeFalse()
        ->and($admin->fresh()->hasPermissionTo('finance.subledger_opening_balance.force_delete'))->toBeTrue();
});

it('does not report a valid reversed vendor opening balance as an active mismatch', function (): void {
    $user = subledgerOpeningUser();
    $vendor = Vendor::factory()->create();
    $payables = ChartOfAccount::factory()->create([
        'account_category' => AccountCategory::LIABILITY,
        'income_balance' => IncomeBalanceType::BALANCE_SHEET,
    ]);
    VendorPostingGroup::query()->findOrFail($vendor->vendor_posting_group_id)->update([
        'payables_account_id' => $payables->id,
    ]);
    $equity = subledgerOpeningEquity();
    GeneralLedgerSetup::instance()->update(['opening_balance_equity_account_id' => $equity->id]);
    $service = app(SubledgerOpeningBalanceService::class);
    $opening = $service->post($service->createDraft([
        'business_id' => $this->business->id,
        'party_type' => 'VENDOR',
        'party_id' => $vendor->id,
        'original_amount' => 101500,
        'currency_code' => 'NGN',
        'posting_date' => '2026-08-30',
        'document_date' => '2026-08-30',
    ], $user->id), $user->id);
    $service->reverse($opening, 'Reconciliation test', $user->id);

    Artisan::call('biwms:subledger-reconcile', ['--details' => true]);
    $output = Artisan::output();

    expect($output)->not->toContain('opening_balance_remaining_mismatch')
        ->and($output)->not->toContain('opening_balance_reversal_exposure');

    Artisan::call('biwms:finance-reconcile', ['--details' => true]);
    expect(Artisan::output())->not->toContain('vendor_ledger_gl_mismatch');
});

it('still reports an incomplete reversed vendor opening balance', function (): void {
    $user = subledgerOpeningUser();
    $vendor = Vendor::factory()->create();
    $payables = ChartOfAccount::factory()->create([
        'account_category' => AccountCategory::LIABILITY,
        'income_balance' => IncomeBalanceType::BALANCE_SHEET,
    ]);
    VendorPostingGroup::query()->findOrFail($vendor->vendor_posting_group_id)->update([
        'payables_account_id' => $payables->id,
    ]);
    $equity = subledgerOpeningEquity();
    GeneralLedgerSetup::instance()->update(['opening_balance_equity_account_id' => $equity->id]);
    $service = app(SubledgerOpeningBalanceService::class);
    $opening = $service->post($service->createDraft([
        'business_id' => $this->business->id,
        'party_type' => 'VENDOR',
        'party_id' => $vendor->id,
        'original_amount' => 100,
        'currency_code' => 'NGN',
        'posting_date' => '2026-08-30',
        'document_date' => '2026-08-30',
    ], $user->id), $user->id);
    SubledgerOpeningBalance::allowServiceTransition(fn (): bool => $opening->update([
        'status' => SubledgerOpeningBalance::STATUS_REVERSED,
    ]));

    Artisan::call('biwms:subledger-reconcile', ['--details' => true]);
    $output = Artisan::output();

    expect($output)->toContain('opening_balance_missing_reversal_subledger')
        ->and($output)->toContain('opening_balance_reversal_exposure');
});

function subledgerOpeningUser(): User
{
    $user = User::factory()->create();
    $permissions = collect([
        'finance.subledger_opening_balance.view',
        'finance.subledger_opening_balance.create',
        'finance.subledger_opening_balance.update',
        'finance.subledger_opening_balance.post',
        'finance.subledger_opening_balance.reverse',
    ])->map(fn (string $name): Permission => Permission::query()->firstOrCreate([
        'name' => $name,
        'guard_name' => 'web',
    ]));
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    $user->givePermissionTo($permissions->all());
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    $user->refresh();

    return $user;
}

function subledgerOpeningEquity(): ChartOfAccount
{
    return ChartOfAccount::factory()->create([
        'account_category' => AccountCategory::EQUITY,
        'income_balance' => IncomeBalanceType::BALANCE_SHEET,
        'direct_posting' => true,
        'blocked' => false,
    ]);
}

function subledgerOpeningTestNumberSeries(): void
{
    foreach ([['CUSTOMER-OPENING', 'COB'], ['VENDOR-OPENING', 'VOB']] as [$code, $prefix]) {
        $series = NumberSeries::query()->create([
            'code' => $code,
            'description' => $code,
            'prefix' => $prefix,
            'starting_number' => 1,
            'ending_number' => 999999,
            'current_number' => 0,
            'year' => 2026,
            'is_active' => true,
            'module' => 'finance',
        ]);
        NumberSeriesLine::query()->create([
            'number_series_id' => $series->id,
            'starting_date' => '2026-01-01',
            'starting_no' => 0,
            'increment_by' => 1,
            'last_no_used' => 0,
            'no_of_digits' => 5,
            'blocked' => false,
        ]);
    }
}
