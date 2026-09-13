<?php

declare(strict_types=1);

use App\Enums\CurrencyExchangeRateType;
use App\Models\BankAccount;
use App\Models\Currency;
use App\Models\CurrencyExchangeRate;
use App\Models\NumberSeries;
use App\Models\NumberSeriesLine;
use App\Models\User;
use App\Services\BankAccountLedgerService;
use App\Services\CurrencyService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    Cache::flush();
});

test('getByCode resolves an active foreign currency without eager-load failure', function (): void {
    currencyServiceLocalCurrency();
    $usd = currencyServiceForeignCurrency();

    $resolved = app(CurrencyService::class)->getByCode('USD');

    expect($resolved)->not->toBeNull()
        ->and($resolved->is($usd))->toBeTrue()
        ->and($resolved->code)->toBe('USD')
        ->and($resolved->is_lcy)->toBeFalse();
});

test('getByCode resolves the local currency', function (): void {
    $local = currencyServiceLocalCurrency();

    $resolved = app(CurrencyService::class)->getByCode('NGN');

    expect($resolved)->not->toBeNull()
        ->and($resolved->is($local))->toBeTrue()
        ->and($resolved->is_lcy)->toBeTrue();
});

test('foreign currency getExchangeRate resolves the dated applicable rate', function (): void {
    currencyServiceLocalCurrency();
    $usd = currencyServiceForeignCurrency();

    CurrencyExchangeRate::query()->create([
        'currency_id' => $usd->id,
        'starting_date' => '2026-07-31',
        'ending_date' => null,
        'exchange_rate_amount' => '1450.000000',
        'relational_exch_rate_amount' => '1.000000',
        'adjustment_exch_rate_amount' => '0.000000',
        'rate_type' => CurrencyExchangeRateType::SPOT,
        'source' => 'manual',
        'is_current' => true,
    ]);

    $resolved = app(CurrencyService::class)->getByCode('USD');

    expect($resolved->getExchangeRate(new DateTime('2026-08-29')))->toBe(1450.0)
        ->and($resolved->getExchangeRate(new DateTime('2026-07-01')))->toBe(1500.0);
});

test('getByCode and validateForTransaction preserve unknown and inactive behaviour', function (): void {
    currencyServiceLocalCurrency();

    expect(app(CurrencyService::class)->getByCode('ZZZ'))->toBeNull();

    Currency::query()->create([
        'code' => 'EUR',
        'description' => 'Euro',
        'decimal_places' => 2,
        'is_active' => false,
        'is_lcy' => false,
        'exchange_rate' => 1000,
    ]);

    // getByCode returns the row regardless of active state (contract).
    expect(app(CurrencyService::class)->getByCode('EUR'))->not->toBeNull();

    // Validate via a separate code so the assertion does not depend on a
    // second cache round-trip of the same model.
    Currency::query()->create([
        'code' => 'GBP',
        'description' => 'Pound Sterling',
        'decimal_places' => 2,
        'is_active' => false,
        'is_lcy' => false,
        'exchange_rate' => 1800,
    ]);

    expect(fn () => app(CurrencyService::class)->validateForTransaction('GBP'))
        ->toThrow(InvalidArgumentException::class, 'inactive');

    expect(fn () => app(CurrencyService::class)->validateForTransaction('ZZZ'))
        ->toThrow(InvalidArgumentException::class, 'not found');
});

test('bank payment resolves a same-currency foreign-currency rate without crashing', function (): void {
    currencyServiceLocalCurrency();
    $usd = currencyServiceForeignCurrency();
    currencyServiceBankLedgerNumberSeries();

    $user = User::factory()->create();

    $bankAccount = BankAccount::factory()->paymentOnly()->create([
        'currency_id' => $usd->id,
        'current_balance' => 1000,
        'available_balance' => 1000,
    ]);

    $entry = app(BankAccountLedgerService::class)->postPayment($bankAccount, [
        'amount' => 120,
        'posting_date' => now(),
        'document_date' => now(),
        'document_no' => 'PAY-FCY-0001',
        'description' => 'Foreign-currency same-currency bank payment',
        'currency_code' => 'USD',
        'currency_factor' => 1500,
        'source_type' => 'vendor',
        'source_id' => $user->id,
        'source_no' => 'PAY-FCY-0001',
        'user_id' => $user->id,
    ]);

    expect((float) $entry->amount)->toBe(-120.0)
        ->and($entry->currency_code)->toBe('USD')
        ->and((float) $entry->currency_factor)->toBe(1500.0)
        ->and((float) $bankAccount->fresh()->current_balance)->toBe(880.0);
});

function currencyServiceLocalCurrency(): Currency
{
    return Currency::query()->firstOrCreate(
        ['code' => 'NGN'],
        [
            'description' => 'Nigerian Naira',
            'symbol' => '₦',
            'decimal_places' => 2,
            'is_active' => true,
            'is_lcy' => true,
            'exchange_rate' => 1,
        ],
    );
}

function currencyServiceForeignCurrency(): Currency
{
    return Currency::query()->firstOrCreate(
        ['code' => 'USD'],
        [
            'description' => 'US Dollar',
            'symbol' => '$',
            'decimal_places' => 2,
            'is_active' => true,
            'is_lcy' => false,
            'exchange_rate' => 1500,
        ],
    );
}

function currencyServiceBankLedgerNumberSeries(): void
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
        ['number_series_id' => $series->id, 'starting_date' => '2026-01-01'],
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
