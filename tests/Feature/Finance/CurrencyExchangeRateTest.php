<?php

declare(strict_types=1);

use App\Enums\CurrencyExchangeRateType;
use App\Enums\CurrencyRoundingMethod;
use App\Exceptions\BusinessException;
use App\Filament\Resources\Payments\Pages\CreatePayment;
use App\Models\BankAccount;
use App\Models\Currency;
use App\Models\CurrencyExchangeRate;
use App\Models\GeneralBusinessPostingGroup;
use App\Models\GeneralLedgerSetup;
use App\Models\NumberSeries;
use App\Models\Payment;
use App\Models\User;
use App\Models\Vendor;
use App\Models\VendorPostingGroup;
use App\Support\Filament\PostingFailureNotifier;
use Database\Seeders\NumberSeriesSeeder;
use Filament\Notifications\Notification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    GeneralLedgerSetup::query()->updateOrCreate(
        ['company_name' => 'Default Company'],
        [
            'allow_posting_from' => '2026-01-01',
            'allow_posting_to' => '2026-12-31',
        ],
    );
});

it('returns a float exchange rate and prefers the dated rate over the currency fallback when one is effective', function (): void {
    $currency = Currency::query()->create([
        'code' => 'USD',
        'description' => 'US Dollar',
        'symbol' => '$',
        'decimal_places' => 2,
        'rounding_method' => CurrencyRoundingMethod::NEAREST,
        'amount_rounding_precision' => '0.01',
        'unit_amount_rounding_precision' => '0.00001',
        'is_active' => true,
        'is_lcy' => false,
        'exchange_rate' => '1500.000000',
        'exchange_rate_date' => '2026-07-01',
        'exchange_rate_type' => CurrencyExchangeRateType::SPOT,
    ]);

    CurrencyExchangeRate::query()->create([
        'currency_id' => $currency->id,
        'starting_date' => '2026-07-31',
        'ending_date' => null,
        'exchange_rate_amount' => '1450.000000',
        'relational_exch_rate_amount' => '1.000000',
        'adjustment_exch_rate_amount' => '0.000000',
        'rate_type' => CurrencyExchangeRateType::SPOT,
        'source' => 'manual',
        'source_reference' => null,
        'is_current' => true,
    ]);

    $rate = $currency->getExchangeRate(new DateTime('2026-08-29'));

    expect($rate)->toBeFloat()
        ->and($rate)->toBe(1450.0);
});

it('falls back to the currency master exchange rate when no dated rate matches', function (): void {
    $currency = Currency::query()->create([
        'code' => 'EUR',
        'description' => 'Euro',
        'symbol' => '€',
        'decimal_places' => 2,
        'rounding_method' => CurrencyRoundingMethod::NEAREST,
        'amount_rounding_precision' => '0.01',
        'unit_amount_rounding_precision' => '0.00001',
        'is_active' => true,
        'is_lcy' => false,
        'exchange_rate' => '1600.000000',
        'exchange_rate_date' => '2026-07-01',
        'exchange_rate_type' => CurrencyExchangeRateType::SPOT,
    ]);

    $rate = $currency->getExchangeRate(new DateTime('2026-01-01'));

    expect($rate)->toBeFloat()
        ->and($rate)->toBe(1600.0);
});

it('keeps local currency semantics and round-trip conversions stable', function (): void {
    $currency = Currency::query()->create([
        'code' => 'NGN',
        'description' => 'Nigerian Naira',
        'symbol' => '₦',
        'decimal_places' => 2,
        'rounding_method' => CurrencyRoundingMethod::NEAREST,
        'amount_rounding_precision' => '0.01',
        'unit_amount_rounding_precision' => '0.00001',
        'is_active' => true,
        'is_lcy' => true,
        'exchange_rate' => '1.000000',
        'exchange_rate_type' => CurrencyExchangeRateType::SPOT,
    ]);

    expect($currency->getExchangeRate())->toBeFloat()
        ->and($currency->getExchangeRate())->toBe(1.0)
        ->and($currency->toLCY(250.5))->toBe(250.5)
        ->and($currency->fromLCY(250.5))->toBe(250.5);
});

it('uses the currency fallback when no dated exchange rate exists for a foreign currency', function (): void {
    $currency = Currency::query()->create([
        'code' => 'GBP',
        'description' => 'Pound Sterling',
        'symbol' => '£',
        'decimal_places' => 2,
        'rounding_method' => CurrencyRoundingMethod::NEAREST,
        'amount_rounding_precision' => '0.01',
        'unit_amount_rounding_precision' => '0.00001',
        'is_active' => true,
        'is_lcy' => false,
        'exchange_rate' => '1700.000000',
        'exchange_rate_type' => CurrencyExchangeRateType::SPOT,
    ]);

    $rate = $currency->getExchangeRate();

    expect($rate)->toBeFloat()
        ->and($rate)->toBe(1700.0)
        ->and($currency->toLCY(2, $rate))->toBe(3400.0)
        ->and($currency->fromLCY(3400, $rate))->toBe(2.0);
});

it('renders the payment create flow for a foreign-currency vendor without throwing exchange rate type errors', function (): void {
    Role::query()->firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);

    $user = User::factory()->create([
        'two_factor_secret' => 'TESTSECRET',
        'two_factor_confirmed_at' => now(),
    ]);
    $user->assignRole('super_admin');

    $bankAccount = BankAccount::factory()->create();
    $currency = Currency::query()->create([
        'code' => 'USD',
        'description' => 'US Dollar',
        'symbol' => '$',
        'decimal_places' => 2,
        'rounding_method' => CurrencyRoundingMethod::NEAREST,
        'amount_rounding_precision' => '0.01',
        'unit_amount_rounding_precision' => '0.00001',
        'is_active' => true,
        'is_lcy' => false,
        'exchange_rate' => '1500.000000',
        'exchange_rate_date' => '2026-07-01',
        'exchange_rate_type' => CurrencyExchangeRateType::SPOT,
    ]);
    $currency->exchangeRates()->create([
        'starting_date' => '2026-07-31',
        'ending_date' => null,
        'exchange_rate_amount' => '1450.000000',
        'relational_exch_rate_amount' => '1.000000',
        'adjustment_exch_rate_amount' => '0.000000',
        'rate_type' => CurrencyExchangeRateType::SPOT,
        'source' => 'manual',
        'is_current' => true,
    ]);

    $businessGroup = GeneralBusinessPostingGroup::query()->create([
        'code' => 'DOMESTIC',
        'description' => 'Domestic',
    ]);
    $vendorPostingGroup = VendorPostingGroup::query()->create([
        'code' => 'DOMESTIC-VEND',
        'description' => 'Domestic Vendors',
    ]);
    $vendor = Vendor::factory()->create([
        'general_business_posting_group_id' => $businessGroup->id,
        'vendor_posting_group_id' => $vendorPostingGroup->id,
        'vat_bus_posting_group' => null,
    ]);

    Livewire::actingAs($user)
        ->test(CreatePayment::class)
        ->fillForm([
            'payment_date' => '2026-08-29',
            'posting_date' => '2026-08-29',
            'payment_direction' => 'DISBURSEMENT',
            'payment_method' => 'BANK_TRANSFER',
            'party_type' => 'VENDOR',
            'party_id' => $vendor->id,
            'party_name' => $vendor->vendor_name,
            'counterparty_bank_name' => 'Vendor Bank',
            'counterparty_account_number' => '1234567890',
            'counterparty_routing_number' => '999999999',
            'currency_id' => $currency->id,
            'currency_factor' => 1450.0,
            'payment_amount' => 100.0,
            'payment_amount_lcy' => 145000.0,
            'bank_account_id' => $bankAccount->id,
            'general_business_posting_group_id' => $businessGroup->id,
        ])
        ->assertHasNoFormErrors();
});

it('shows a clear notification and does not create a partial payment when the PAYMENT series is missing', function (): void {
    $this->seed(NumberSeriesSeeder::class);
    NumberSeries::query()->where('code', 'PAYMENT')->delete();

    Role::query()->firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);

    $user = User::factory()->create([
        'two_factor_secret' => 'TESTSECRET',
        'two_factor_confirmed_at' => now(),
    ]);
    $user->assignRole('super_admin');

    $bankAccount = BankAccount::factory()->create();
    $currency = Currency::query()->create([
        'code' => 'USD',
        'description' => 'US Dollar',
        'symbol' => '$',
        'decimal_places' => 2,
        'rounding_method' => CurrencyRoundingMethod::NEAREST,
        'amount_rounding_precision' => '0.01',
        'unit_amount_rounding_precision' => '0.00001',
        'is_active' => true,
        'is_lcy' => false,
        'exchange_rate' => '1500.000000',
        'exchange_rate_date' => '2026-07-01',
        'exchange_rate_type' => CurrencyExchangeRateType::SPOT,
    ]);
    $businessGroup = GeneralBusinessPostingGroup::query()->create([
        'code' => 'DOMESTIC',
        'description' => 'Domestic',
    ]);
    $vendorPostingGroup = VendorPostingGroup::query()->create([
        'code' => 'DOMESTIC-VEND',
        'description' => 'Domestic Vendors',
    ]);
    $vendor = Vendor::factory()->create([
        'general_business_posting_group_id' => $businessGroup->id,
        'vendor_posting_group_id' => $vendorPostingGroup->id,
        'vat_bus_posting_group' => null,
    ]);

    Livewire::actingAs($user)
        ->test(CreatePayment::class)
        ->fillForm([
            'payment_date' => '2026-08-29',
            'posting_date' => '2026-08-29',
            'payment_direction' => 'DISBURSEMENT',
            'payment_method' => 'BANK_TRANSFER',
            'party_type' => 'VENDOR',
            'party_id' => $vendor->id,
            'party_name' => $vendor->vendor_name,
            'counterparty_bank_name' => 'Vendor Bank',
            'counterparty_account_number' => '1234567890',
            'counterparty_routing_number' => '999999999',
            'currency_id' => $currency->id,
            'currency_factor' => 1450.0,
            'payment_amount' => 100.0,
            'payment_amount_lcy' => 145000.0,
            'bank_account_id' => $bankAccount->id,
            'general_business_posting_group_id' => $businessGroup->id,
        ])
        ->call('create')
        ->assertNotified('Payment Number Series is not configured');

    expect(Payment::query()->count())->toBe(0);
});

it('formats validation and business posting failures as persistent danger notifications', function (): void {
    PostingFailureNotifier::notify(
        ValidationException::withMessages(['posting_date' => 'No accounting period exists for the selected posting date.']),
        'Payment was not posted',
    );
    Notification::assertNotified('Payment was not posted');

    PostingFailureNotifier::notify(
        new BusinessException('The selected bank account is not enabled for receipts.', title: 'Payment was not posted'),
        'Payment was not posted',
    );
    Notification::assertNotified('Payment was not posted');
});
