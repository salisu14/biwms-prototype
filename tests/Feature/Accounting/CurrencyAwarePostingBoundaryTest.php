<?php

declare(strict_types=1);

use App\Accounting\PostingIntent;
use App\Enums\AccountCategory;
use App\Enums\AccountStructuralType;
use App\Enums\SourceType;
use App\Models\AccountingPeriod;
use App\Models\ChartOfAccount;
use App\Models\GeneralLedgerSetup;
use App\Models\GlEntry;
use App\Models\PostingTransaction;
use App\Services\Accounting\GeneralLedgerPostingKernel;
use App\Support\DecimalMath;
use App\Support\DecimalPrecision;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
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

    AccountingPeriod::query()->updateOrCreate(
        ['name' => 'FY2026'],
        [
            'start_date' => '2026-01-01',
            'end_date' => '2026-12-31',
            'is_closed' => false,
        ],
    );
});

// ---------------------------------------------------------------------------
// A. LCY_ONLY backward compatibility
// ---------------------------------------------------------------------------

it('keeps LCY-only postings unchanged even when foreign-looking header metadata is present', function (): void {
    [$debitAccount, $creditAccount] = currencyAwareAccounts();

    $transaction = app(GeneralLedgerPostingKernel::class)->post(currencyAwareIntent([
        'currency_code' => 'USD',
        'exchange_rate' => '1500',
        'idempotency_key' => '3cb-lcy-only-foreign-metadata',
        'lines' => [
            ['account_id' => $debitAccount->id, 'debit_amount' => '180.00'],
            ['account_id' => $creditAccount->id, 'credit_amount' => '180.00'],
        ],
    ]));

    $debitEntry = $transaction->glEntries()->where('debit_amount', '>', 0)->firstOrFail();

    expect($transaction->currency_code)->toBe('USD')
        ->and($transaction->economic_fingerprint)->toBeNull()
        ->and($debitEntry->debit_amount)->toBe('180.00')
        ->and($debitEntry->debit_amount_lcy)->toBe('180.00')
        ->and($debitEntry->amount)->toBe('180.00')
        ->and($debitEntry->document_currency_code)->toBeNull()
        ->and($debitEntry->document_debit_amount)->toBeNull()
        ->and($debitEntry->currency_factor)->toBeNull()
        ->and($debitEntry->posting_line_type)->toBeNull()
        ->and($debitEntry->lcy_only_reason)->toBeNull();
});

it('preserves legacy LCY-only idempotency replay for a historical null-fingerprint transaction', function (): void {
    [$debitAccount, $creditAccount] = currencyAwareAccounts();
    $intent = currencyAwareIntent([
        'idempotency_key' => '3cb-legacy-lcy-replay',
        'lines' => [
            ['account_id' => $debitAccount->id, 'debit_amount' => '10.00'],
            ['account_id' => $creditAccount->id, 'credit_amount' => '10.00'],
        ],
    ]);

    $first = app(GeneralLedgerPostingKernel::class)->post($intent);
    $second = app(GeneralLedgerPostingKernel::class)->post($intent);

    app(GeneralLedgerPostingKernel::class)->preflight($intent);

    expect($second->id)->toBe($first->id)
        ->and(PostingTransaction::query()->where('idempotency_key', '3cb-legacy-lcy-replay')->count())->toBe(1)
        ->and(GlEntry::query()->where('idempotency_key', '3cb-legacy-lcy-replay')->count())->toBe(2);
});

// ---------------------------------------------------------------------------
// B. Currency-aware basic economics
// ---------------------------------------------------------------------------

it('accepts a currency-aware NGN posting with factor 1', function (): void {
    [$debitAccount, $creditAccount] = currencyAwareAccounts();

    $transaction = app(GeneralLedgerPostingKernel::class)->post(currencyAwareIntent([
        'mode' => 'CURRENCY_AWARE',
        'currency_code' => 'NGN',
        'exchange_rate' => '1',
        'idempotency_key' => '3cb-ngn-factor-1',
        'lines' => [
            documentMonetaryLine($debitAccount->id, '250.00', '0'),
            documentMonetaryLine($creditAccount->id, '0', '250.00'),
        ],
    ]));

    expect($transaction->glEntries)->toHaveCount(2);
});

it('rejects a currency-aware NGN posting with a non-1 factor', function (): void {
    [$debitAccount, $creditAccount] = currencyAwareAccounts();

    app(GeneralLedgerPostingKernel::class)->post(currencyAwareIntent([
        'mode' => 'CURRENCY_AWARE',
        'currency_code' => 'NGN',
        'exchange_rate' => '1.5',
        'idempotency_key' => '3cb-ngn-factor-bad',
        'lines' => [
            documentMonetaryLine($debitAccount->id, '250.00', '0'),
            documentMonetaryLine($creditAccount->id, '0', '250.00'),
        ],
    ]));
})->throws(ValidationException::class, 'must use a currency factor of 1');

it('posts a currency-aware USD document at explicit LCY economics and preserves the document trace', function (): void {
    [$debitAccount, $creditAccount] = currencyAwareAccounts();

    $transaction = app(GeneralLedgerPostingKernel::class)->post(currencyAwareIntent([
        'mode' => 'CURRENCY_AWARE',
        'currency_code' => 'USD',
        'exchange_rate' => '1500',
        'idempotency_key' => '3cb-usd-180-at-1500',
        'lines' => [
            documentMonetaryLine($debitAccount->id, '180.00', '0', lcyDebit: '270000.00'),
            documentMonetaryLine($creditAccount->id, '0', '180.00', lcyCredit: '270000.00'),
        ],
    ]));

    $debitEntry = $transaction->glEntries()->where('debit_amount', '>', 0)->firstOrFail();
    $creditEntry = $transaction->glEntries()->where('credit_amount', '>', 0)->firstOrFail();

    // G/L economics remain LCY; the document amount is trace only.
    expect($debitEntry->debit_amount)->toBe('270000.00')
        ->and($debitEntry->debit_amount_lcy)->toBe('270000.00')
        ->and($debitEntry->amount)->toBe('270000.00')
        ->and($debitEntry->amount_lcy)->toBe('270000.00')
        ->and($debitEntry->document_currency_code)->toBe('USD')
        ->and($debitEntry->document_debit_amount)->toBe('180.0000')
        ->and($debitEntry->document_credit_amount)->toBe('0.0000')
        ->and($debitEntry->document_amount)->toBe('180.0000')
        ->and($debitEntry->currency_factor)->toBe('1500.000000')
        ->and($debitEntry->posting_line_type)->toBe('DOCUMENT_MONETARY')
        ->and($debitEntry->lcy_only_reason)->toBeNull()
        ->and($transaction->exchange_rate)->toBe('1500.00000000');

    expect($creditEntry->credit_amount)->toBe('270000.00')
        ->and($creditEntry->document_credit_amount)->toBe('180.0000')
        ->and($creditEntry->document_amount)->toBe('-180.0000');
});

it('fails closed on missing, zero, negative or malformed foreign factors', function (string $factor): void {
    [$debitAccount, $creditAccount] = currencyAwareAccounts();

    app(GeneralLedgerPostingKernel::class)->post(currencyAwareIntent([
        'mode' => 'CURRENCY_AWARE',
        'currency_code' => 'USD',
        'exchange_rate' => $factor,
        'idempotency_key' => '3cb-bad-factor-'.md5($factor),
        'lines' => [
            documentMonetaryLine($debitAccount->id, '180.00', '0', lcyDebit: '270000.00'),
            documentMonetaryLine($creditAccount->id, '0', '180.00', lcyCredit: '270000.00'),
        ],
    ]));
})->with([
    'missing' => [''],
    'zero' => ['0'],
    'negative' => ['-5'],
    'malformed' => ['abc'],
])->throws(ValidationException::class);

// ---------------------------------------------------------------------------
// C. LCY_ONLY restriction: allowlisted reasons only
// ---------------------------------------------------------------------------

it('rejects a currency-aware line that omits its line type', function (): void {
    [$debitAccount, $creditAccount] = currencyAwareAccounts();

    app(GeneralLedgerPostingKernel::class)->post(currencyAwareIntent([
        'mode' => 'CURRENCY_AWARE',
        'currency_code' => 'USD',
        'exchange_rate' => '1500',
        'idempotency_key' => '3cb-missing-line-type',
        'lines' => [
            ['account_id' => $debitAccount->id, 'debit_amount' => '270000.00'],
            documentMonetaryLine($creditAccount->id, '0', '180.00', lcyCredit: '270000.00'),
        ],
    ]));
})->throws(ValidationException::class, 'must declare a line type');

it('rejects an unrestricted LCY_ONLY line with no approved reason', function (): void {
    [$debitAccount, $creditAccount] = currencyAwareAccounts();

    app(GeneralLedgerPostingKernel::class)->post(currencyAwareIntent([
        'mode' => 'CURRENCY_AWARE',
        'currency_code' => 'USD',
        'exchange_rate' => '1500',
        'idempotency_key' => '3cb-lcy-only-no-reason',
        'lines' => [
            documentMonetaryLine($debitAccount->id, '180.00', '0', lcyDebit: '270000.00'),
            ['account_id' => $creditAccount->id, 'credit_amount' => '270000.00', 'line_type' => 'LCY_ONLY'],
        ],
    ]));
})->throws(ValidationException::class, 'must declare an approved reason');

it('rejects an LCY-only line that carries a document amount', function (): void {
    [$debitAccount, $creditAccount] = currencyAwareAccounts();

    app(GeneralLedgerPostingKernel::class)->post(currencyAwareIntent([
        'mode' => 'CURRENCY_AWARE',
        'currency_code' => 'USD',
        'exchange_rate' => '1500',
        'idempotency_key' => '3cb-lcy-only-with-document-amount',
        'lines' => [
            [
                'account_id' => $debitAccount->id,
                'debit_amount' => '270000.00',
                'line_type' => 'LCY_ONLY',
                'lcy_only_reason' => 'ROUNDING',
                'document_debit_amount' => '180.00',
                'document_credit_amount' => '0',
            ],
            documentMonetaryLine($creditAccount->id, '0', '180.00', lcyCredit: '270000.00'),
        ],
    ]));
})->throws(ValidationException::class, 'is marked LCY_ONLY but carries document-currency amounts');

it('rejects a document-monetary line that carries an LCY-only reason', function (): void {
    [$debitAccount, $creditAccount] = currencyAwareAccounts();

    app(GeneralLedgerPostingKernel::class)->post(currencyAwareIntent([
        'mode' => 'CURRENCY_AWARE',
        'currency_code' => 'USD',
        'exchange_rate' => '1500',
        'idempotency_key' => '3cb-document-monetary-with-reason',
        'lines' => [
            documentMonetaryLine($debitAccount->id, '180.00', '0', lcyDebit: '270000.00', extra: ['lcy_only_reason' => 'ROUNDING']),
            documentMonetaryLine($creditAccount->id, '0', '180.00', lcyCredit: '270000.00'),
        ],
    ]));
})->throws(ValidationException::class, 'must not carry an LCY-only reason');

it('rejects an unsupported LCY-only reason at construction', function (): void {
    currencyAwareIntent([
        'lines' => [
            ['account_id' => 1, 'credit_amount' => '1.00', 'line_type' => 'LCY_ONLY', 'lcy_only_reason' => 'MADE_UP'],
        ],
    ]);
})->throws(InvalidArgumentException::class, 'Unsupported LCY-only reason');

it('accepts allowlisted ROUNDING and VALUATION_ONLY LCY-only lines and persists their classification', function (string $reason): void {
    [$debitAccount, $creditAccount] = currencyAwareAccounts();

    $transaction = app(GeneralLedgerPostingKernel::class)->post(currencyAwareIntent([
        'mode' => 'CURRENCY_AWARE',
        'currency_code' => 'USD',
        'exchange_rate' => '1500',
        'idempotency_key' => '3cb-allowlisted-'.strtolower($reason),
        'lines' => [
            documentMonetaryLine($debitAccount->id, '180.00', '0', lcyDebit: '270000.00'),
            lcyOnlyLine($creditAccount->id, '270000.00', $reason, 'credit'),
        ],
    ]));

    $lcyOnlyEntry = $transaction->glEntries()->where('credit_amount', '>', 0)->firstOrFail();

    expect($lcyOnlyEntry->credit_amount)->toBe('270000.00')
        ->and($lcyOnlyEntry->document_currency_code)->toBe('USD')
        ->and($lcyOnlyEntry->currency_factor)->toBe('1500.000000')
        ->and($lcyOnlyEntry->document_debit_amount)->toBeNull()
        ->and($lcyOnlyEntry->document_credit_amount)->toBeNull()
        ->and($lcyOnlyEntry->document_amount)->toBeNull()
        ->and($lcyOnlyEntry->posting_line_type)->toBe('LCY_ONLY')
        ->and($lcyOnlyEntry->lcy_only_reason)->toBe($reason);
})->with(['ROUNDING', 'VALUATION_ONLY']);

it('rejects a document-monetary line that declares a line type but no document amount', function (): void {
    [$debitAccount, $creditAccount] = currencyAwareAccounts();

    app(GeneralLedgerPostingKernel::class)->post(currencyAwareIntent([
        'mode' => 'CURRENCY_AWARE',
        'currency_code' => 'USD',
        'exchange_rate' => '1500',
        'idempotency_key' => '3cb-line-type-without-document-amount',
        'lines' => [
            ['account_id' => $debitAccount->id, 'debit_amount' => '270000.00', 'line_type' => 'DOCUMENT_MONETARY'],
            documentMonetaryLine($creditAccount->id, '0', '180.00', lcyCredit: '270000.00'),
        ],
    ]));
})->throws(ValidationException::class, 'must supply both a document debit and a document credit');

// ---------------------------------------------------------------------------
// D. Rounds, scale and pairing
// ---------------------------------------------------------------------------

it('rejects a document-monetary line whose LCY economics do not reconcile with the factor', function (): void {
    [$debitAccount, $creditAccount] = currencyAwareAccounts();

    app(GeneralLedgerPostingKernel::class)->post(currencyAwareIntent([
        'mode' => 'CURRENCY_AWARE',
        'currency_code' => 'USD',
        'exchange_rate' => '1500',
        'idempotency_key' => '3cb-lcy-mismatch',
        'lines' => [
            documentMonetaryLine($debitAccount->id, '180.00', '0', lcyDebit: '180.00'),
            documentMonetaryLine($creditAccount->id, '0', '180.00', lcyCredit: '180.00'),
        ],
    ]));
})->throws(ValidationException::class, 'do not reconcile with its document amount');

it('requires exact equality at G/L scale with no implicit tolerance', function (): void {
    [$debitAccount, $creditAccount] = currencyAwareAccounts();

    app(GeneralLedgerPostingKernel::class)->post(currencyAwareIntent([
        'mode' => 'CURRENCY_AWARE',
        'currency_code' => 'USD',
        'exchange_rate' => '1',
        'idempotency_key' => '3cb-exact-equality',
        'lines' => [
            documentMonetaryLine($debitAccount->id, '100.00', '0', lcyDebit: '100.01'),
            lcyOnlyLine($creditAccount->id, '100.01', 'ROUNDING', 'credit'),
        ],
    ]));
})->throws(ValidationException::class, 'do not reconcile');

it('rejects a document line carrying both a document debit and credit', function (): void {
    [$debitAccount, $creditAccount] = currencyAwareAccounts();

    // LCY side is valid (debit only); only the document side conflicts.
    app(GeneralLedgerPostingKernel::class)->post(currencyAwareIntent([
        'mode' => 'CURRENCY_AWARE',
        'currency_code' => 'USD',
        'exchange_rate' => '1500',
        'idempotency_key' => '3cb-doc-conflict',
        'lines' => [
            documentMonetaryLine($debitAccount->id, '180.00', '180.00', lcyDebit: '270000.00', lcyCredit: '0'),
            documentMonetaryLine($creditAccount->id, '0', '180.00', lcyCredit: '270000.00'),
        ],
    ]));
})->throws(ValidationException::class, 'cannot contain both a document debit and a document credit');

it('rejects LCY imbalance in a currency-aware intent', function (): void {
    [$debitAccount, $creditAccount] = currencyAwareAccounts();

    app(GeneralLedgerPostingKernel::class)->post(currencyAwareIntent([
        'mode' => 'CURRENCY_AWARE',
        'currency_code' => 'USD',
        'exchange_rate' => '1500',
        'idempotency_key' => '3cb-lcy-imbalance',
        'lines' => [
            documentMonetaryLine($debitAccount->id, '180.00', '0', lcyDebit: '270000.00'),
            documentMonetaryLine($creditAccount->id, '0', '179.00', lcyCredit: '268500.00'),
        ],
    ]));
})->throws(ValidationException::class, 'is not balanced');

it('accepts a positive midpoint document rounding boundary', function (): void {
    [$debitAccount, $creditAccount] = currencyAwareAccounts();

    // 1.00 USD x 1.005 = 1.005 LCY -> half-up at G/L scale = 1.01
    $transaction = app(GeneralLedgerPostingKernel::class)->post(currencyAwareIntent([
        'mode' => 'CURRENCY_AWARE',
        'currency_code' => 'USD',
        'exchange_rate' => '1.005',
        'idempotency_key' => '3cb-positive-midpoint',
        'lines' => [
            documentMonetaryLine($debitAccount->id, '1.00', '0', lcyDebit: '1.01'),
            lcyOnlyLine($creditAccount->id, '1.01', 'ROUNDING', 'credit'),
        ],
    ]));

    expect($transaction->glEntries)->toHaveCount(2);
});

it('rejects the wrong direction of a positive midpoint document rounding boundary', function (): void {
    [$debitAccount, $creditAccount] = currencyAwareAccounts();

    app(GeneralLedgerPostingKernel::class)->post(currencyAwareIntent([
        'mode' => 'CURRENCY_AWARE',
        'currency_code' => 'USD',
        'exchange_rate' => '1.005',
        'idempotency_key' => '3cb-positive-midpoint-wrong',
        'lines' => [
            documentMonetaryLine($debitAccount->id, '1.00', '0', lcyDebit: '1.00'),
            lcyOnlyLine($creditAccount->id, '1.00', 'ROUNDING', 'credit'),
        ],
    ]));
})->throws(ValidationException::class, 'do not reconcile');

it('accepts a negative midpoint document rounding boundary on a credit line', function (): void {
    [$debitAccount, $creditAccount] = currencyAwareAccounts();

    // -1.00 USD x 1.005 = -1.005 LCY -> half-up (away from zero) = -1.01
    $transaction = app(GeneralLedgerPostingKernel::class)->post(currencyAwareIntent([
        'mode' => 'CURRENCY_AWARE',
        'currency_code' => 'USD',
        'exchange_rate' => '1.005',
        'idempotency_key' => '3cb-negative-midpoint',
        'lines' => [
            lcyOnlyLine($debitAccount->id, '1.01', 'ROUNDING', 'debit'),
            documentMonetaryLine($creditAccount->id, '0', '1.00', lcyCredit: '1.01'),
        ],
    ]));

    expect($transaction->glEntries)->toHaveCount(2);
});

it('requires an explicit ROUNDING line for a multi-line rounding difference', function (): void {
    [$debitA, $debitB] = currencyAwareAccounts();
    $creditAccount = ChartOfAccount::factory()->create([
        'account_category' => AccountCategory::LIABILITY,
        'structural_type' => AccountStructuralType::POSTING,
        'direct_posting' => true,
        'blocked' => false,
    ]);

    $lines = [
        documentMonetaryLine($debitA->id, '1.00', '0', lcyDebit: '1.01'),
        documentMonetaryLine($debitB->id, '1.00', '0', lcyDebit: '1.01'),
        documentMonetaryLine($creditAccount->id, '0', '2.00', lcyCredit: '2.01'),
    ];

    // Without the explicit rounding line the LCY totals do not balance.
    expect(fn () => app(GeneralLedgerPostingKernel::class)->post(currencyAwareIntent([
        'mode' => 'CURRENCY_AWARE',
        'currency_code' => 'USD',
        'exchange_rate' => '1.005',
        'idempotency_key' => '3cb-multi-line-rounding-missing',
        'lines' => $lines,
    ])))->toThrow(ValidationException::class, 'is not balanced');

    $transaction = app(GeneralLedgerPostingKernel::class)->post(currencyAwareIntent([
        'mode' => 'CURRENCY_AWARE',
        'currency_code' => 'USD',
        'exchange_rate' => '1.005',
        'idempotency_key' => '3cb-multi-line-rounding',
        'lines' => [...$lines, lcyOnlyLine($creditAccount->id, '0.01', 'ROUNDING', 'credit')],
    ]));

    $roundingEntry = $transaction->glEntries()->where('lcy_only_reason', 'ROUNDING')->firstOrFail();

    expect($roundingEntry->credit_amount)->toBe('0.01')
        ->and($roundingEntry->posting_line_type)->toBe('LCY_ONLY')
        ->and($roundingEntry->document_amount)->toBeNull();
});

// ---------------------------------------------------------------------------
// E. Normalized factor persistence
// ---------------------------------------------------------------------------

it('normalizes NGN with an omitted factor to 1 everywhere', function (): void {
    [$debitAccount, $creditAccount] = currencyAwareAccounts();

    $transaction = app(GeneralLedgerPostingKernel::class)->post(currencyAwareIntent([
        'mode' => 'CURRENCY_AWARE',
        'currency_code' => 'NGN',
        'exchange_rate' => '',
        'idempotency_key' => '3cb-ngn-omitted-factor',
        'lines' => [
            documentMonetaryLine($debitAccount->id, '250.00', '0'),
            documentMonetaryLine($creditAccount->id, '0', '250.00'),
        ],
    ]));

    expect($transaction->exchange_rate)->toBe('1.00000000');

    foreach ($transaction->glEntries as $entry) {
        expect($entry->exchange_rate)->toBe('1.000000')
            ->and($entry->currency_factor)->toBe('1.000000');
    }
});

it('persists the same normalized factor on header, exchange rate and currency factor for USD', function (): void {
    [$debitAccount, $creditAccount] = currencyAwareAccounts();

    $transaction = app(GeneralLedgerPostingKernel::class)->post(currencyAwareIntent([
        'mode' => 'CURRENCY_AWARE',
        'currency_code' => 'USD',
        'exchange_rate' => '1500',
        'idempotency_key' => '3cb-normalized-usd',
        'lines' => [
            documentMonetaryLine($debitAccount->id, '180.00', '0', lcyDebit: '270000.00'),
            documentMonetaryLine($creditAccount->id, '0', '180.00', lcyCredit: '270000.00'),
        ],
    ]));

    expect($transaction->exchange_rate)->toBe('1500.00000000');

    foreach ($transaction->glEntries as $entry) {
        expect($entry->exchange_rate)->toBe('1500.000000')
            ->and($entry->currency_factor)->toBe('1500.000000');
    }
});

it('preserves a factor below 1 consistently', function (): void {
    [$debitAccount, $creditAccount] = currencyAwareAccounts();

    $transaction = app(GeneralLedgerPostingKernel::class)->post(currencyAwareIntent([
        'mode' => 'CURRENCY_AWARE',
        'currency_code' => 'USD',
        'exchange_rate' => '0.5',
        'idempotency_key' => '3cb-factor-below-one',
        'lines' => [
            documentMonetaryLine($debitAccount->id, '200.00', '0', lcyDebit: '100.00'),
            documentMonetaryLine($creditAccount->id, '0', '200.00', lcyCredit: '100.00'),
        ],
    ]));

    expect($transaction->exchange_rate)->toBe('0.50000000');

    foreach ($transaction->glEntries as $entry) {
        expect($entry->exchange_rate)->toBe('0.500000')
            ->and($entry->currency_factor)->toBe('0.500000');
    }
});

it('preserves a fractional factor consistently at schema scale', function (): void {
    [$debitAccount, $creditAccount] = currencyAwareAccounts();

    // 1.00 USD x 1234.567891 = 1234.567891 -> 1234.57 at G/L scale
    $transaction = app(GeneralLedgerPostingKernel::class)->post(currencyAwareIntent([
        'mode' => 'CURRENCY_AWARE',
        'currency_code' => 'USD',
        'exchange_rate' => '1234.567891',
        'idempotency_key' => '3cb-fractional-factor',
        'lines' => [
            documentMonetaryLine($debitAccount->id, '1.00', '0', lcyDebit: '1234.57'),
            documentMonetaryLine($creditAccount->id, '0', '1.00', lcyCredit: '1234.57'),
        ],
    ]));

    expect($transaction->exchange_rate)->toBe('1234.56789100');

    foreach ($transaction->glEntries as $entry) {
        expect($entry->exchange_rate)->toBe('1234.567891')
            ->and($entry->currency_factor)->toBe('1234.567891');
    }
});

// ---------------------------------------------------------------------------
// F. Idempotency fingerprint ownership
// ---------------------------------------------------------------------------

it('returns the existing transaction for an identical currency-aware replay', function (): void {
    [$debitAccount, $creditAccount] = currencyAwareAccounts();
    $intent = usdIntentFor([$debitAccount, $creditAccount], '3cb-idempotent-same');

    $first = app(GeneralLedgerPostingKernel::class)->post($intent);
    $second = app(GeneralLedgerPostingKernel::class)->post($intent);

    expect($second->id)->toBe($first->id)
        ->and(PostingTransaction::query()->where('idempotency_key', '3cb-idempotent-same')->count())->toBe(1)
        ->and(GlEntry::query()->where('idempotency_key', '3cb-idempotent-same')->count())->toBe(2);
});

it('fails closed when a currency-aware idempotency key is replayed with changed economics', function (array $arguments): void {
    [$debitAccount, $creditAccount] = currencyAwareAccounts();
    $accounts = [$debitAccount, $creditAccount];

    app(GeneralLedgerPostingKernel::class)->post(usdIntentFor($accounts, '3cb-idempotent-conflict'));

    app(GeneralLedgerPostingKernel::class)->post(usdIntentFor($accounts, '3cb-idempotent-conflict', ...$arguments));
})->with([
    'changed factor' => [['factor' => '1400', 'lcyAmount' => '252000.00']],
    'changed document amount' => [['documentAmount' => '181.00', 'lcyAmount' => '271500.00']],
    'changed lcy economics only' => [['lcyAmount' => '271000.00']],
])->throws(ValidationException::class, 'different currency-aware economics');

it('fails closed when a replay changes analytical or subledger ownership', function (string $field, mixed $value): void {
    [$debitAccount, $creditAccount] = currencyAwareAccounts();
    $accounts = [$debitAccount, $creditAccount];
    $key = '3cb-ownership-'.$field;

    $baseLine = documentMonetaryLine($debitAccount->id, '180.00', '0', lcyDebit: '270000.00', extra: $field === 'dimensions' ? ['dimensions' => ['department' => 'A']] : []);
    $creditLine = documentMonetaryLine($creditAccount->id, '0', '180.00', lcyCredit: '270000.00');

    app(GeneralLedgerPostingKernel::class)->post(usdIntentWithLines($accounts, $key, [$baseLine, $creditLine]));

    $changedLine = $field === 'dimensions'
        ? documentMonetaryLine($debitAccount->id, '180.00', '0', lcyDebit: '270000.00', extra: ['dimensions' => ['department' => $value]])
        : documentMonetaryLine($debitAccount->id, '180.00', '0', lcyDebit: '270000.00', extra: [$field => $value]);

    expect(fn () => app(GeneralLedgerPostingKernel::class)->post(usdIntentWithLines($accounts, $key, [$changedLine, $creditLine])))
        ->toThrow(ValidationException::class, 'different currency-aware economics');

    expect(PostingTransaction::query()->where('idempotency_key', $key)->count())->toBe(1)
        ->and(GlEntry::query()->where('idempotency_key', $key)->count())->toBe(2);
})->with([
    'dimension value' => ['dimensions', 'B'],
    'customer ledger link' => ['customer_ledger_entry_id', 7],
    'vendor ledger link' => ['vendor_ledger_entry_id', 7],
    'item ledger entry link' => ['item_ledger_entry_id', 7],
]);

it('treats equivalent dimension maps with different key ordering as identical', function (): void {
    [$debitAccount, $creditAccount] = currencyAwareAccounts();
    $accounts = [$debitAccount, $creditAccount];
    $key = '3cb-dimension-canonicalization';

    app(GeneralLedgerPostingKernel::class)->post(usdIntentWithLines($accounts, $key, [
        documentMonetaryLine($debitAccount->id, '180.00', '0', lcyDebit: '270000.00', extra: ['dimensions' => ['department' => 'A', 'cost_center' => 'C1']]),
        documentMonetaryLine($creditAccount->id, '0', '180.00', lcyCredit: '270000.00'),
    ]));

    $replay = usdIntentWithLines($accounts, $key, [
        documentMonetaryLine($debitAccount->id, '180.00', '0', lcyDebit: '270000.00', extra: ['dimensions' => ['cost_center' => 'C1', 'department' => 'A']]),
        documentMonetaryLine($creditAccount->id, '0', '180.00', lcyCredit: '270000.00'),
    ]);

    app(GeneralLedgerPostingKernel::class)->preflight($replay);

    expect(PostingTransaction::query()->where('idempotency_key', $key)->count())->toBe(1);
});

it('preserves duplicate-line multiplicity and dimension distinctness in the fingerprint', function (): void {
    [$debitAccount, $creditAccount] = currencyAwareAccounts();
    $accounts = [$debitAccount, $creditAccount];
    $key = '3cb-multiplicity';

    $twice = [
        documentMonetaryLine($debitAccount->id, '90.00', '0', lcyDebit: '135000.00', extra: ['dimensions' => ['department' => 'A']]),
        documentMonetaryLine($debitAccount->id, '90.00', '0', lcyDebit: '135000.00', extra: ['dimensions' => ['department' => 'B']]),
        documentMonetaryLine($creditAccount->id, '0', '180.00', lcyCredit: '270000.00'),
    ];

    app(GeneralLedgerPostingKernel::class)->post(usdIntentWithLines($accounts, $key, $twice));

    // Same totals but one line instead of two duplicates -> different ownership.
    $single = [
        documentMonetaryLine($debitAccount->id, '180.00', '0', lcyDebit: '270000.00', extra: ['dimensions' => ['department' => 'A']]),
        documentMonetaryLine($creditAccount->id, '0', '180.00', lcyCredit: '270000.00'),
    ];

    expect(fn () => app(GeneralLedgerPostingKernel::class)->post(usdIntentWithLines($accounts, $key, $single)))
        ->toThrow(ValidationException::class, 'different currency-aware economics');

    // Different dimension values on otherwise-identical lines stay distinct.
    $sameDimensions = [
        documentMonetaryLine($debitAccount->id, '90.00', '0', lcyDebit: '135000.00', extra: ['dimensions' => ['department' => 'A']]),
        documentMonetaryLine($debitAccount->id, '90.00', '0', lcyDebit: '135000.00', extra: ['dimensions' => ['department' => 'A']]),
        documentMonetaryLine($creditAccount->id, '0', '180.00', lcyCredit: '270000.00'),
    ];

    expect(fn () => app(GeneralLedgerPostingKernel::class)->post(usdIntentWithLines($accounts, $key, $sameDimensions)))
        ->toThrow(ValidationException::class, 'different currency-aware economics');

    expect(PostingTransaction::query()->where('idempotency_key', $key)->count())->toBe(1)
        ->and(GlEntry::query()->where('idempotency_key', $key)->count())->toBe(3);
});

it('never duplicates posting transaction or gl entries on a conflicting currency-aware replay', function (): void {
    [$debitAccount, $creditAccount] = currencyAwareAccounts();
    $accounts = [$debitAccount, $creditAccount];

    app(GeneralLedgerPostingKernel::class)->post(usdIntentFor($accounts, '3cb-idempotent-no-duplicate'));

    try {
        app(GeneralLedgerPostingKernel::class)->post(usdIntentFor($accounts, '3cb-idempotent-no-duplicate', factor: '1400', lcyAmount: '252000.00'));
    } catch (ValidationException) {
        // expected conflict; nothing may be written
    }

    expect(PostingTransaction::query()->where('idempotency_key', '3cb-idempotent-no-duplicate')->count())->toBe(1)
        ->and(GlEntry::query()->where('idempotency_key', '3cb-idempotent-no-duplicate')->count())->toBe(2);
});

// ---------------------------------------------------------------------------
// G. Preflight / post replay parity
// ---------------------------------------------------------------------------

it('preflights a fresh valid intent without writing', function (): void {
    [$debitAccount, $creditAccount] = currencyAwareAccounts();

    app(GeneralLedgerPostingKernel::class)->preflight(usdIntentFor([$debitAccount, $creditAccount], '3cb-preflight'));

    expect(PostingTransaction::query()->count())->toBe(0)
        ->and(GlEntry::query()->count())->toBe(0);
});

it('fails preflight for a fresh invalid intent', function (): void {
    [$debitAccount, $creditAccount] = currencyAwareAccounts();

    expect(fn () => app(GeneralLedgerPostingKernel::class)->preflight(currencyAwareIntent([
        'mode' => 'CURRENCY_AWARE',
        'currency_code' => 'USD',
        'exchange_rate' => '1500',
        'idempotency_key' => '3cb-preflight-bad',
        'lines' => [
            documentMonetaryLine($debitAccount->id, '180.00', '0', lcyDebit: '180.00'),
            documentMonetaryLine($creditAccount->id, '0', '180.00', lcyCredit: '180.00'),
        ],
    ])))->toThrow(ValidationException::class, 'do not reconcile');
});

it('preflights an identical currency-aware replay successfully without writes', function (): void {
    [$debitAccount, $creditAccount] = currencyAwareAccounts();
    $accounts = [$debitAccount, $creditAccount];

    app(GeneralLedgerPostingKernel::class)->post(usdIntentFor($accounts, '3cb-preflight-replay-same'));
    app(GeneralLedgerPostingKernel::class)->preflight(usdIntentFor($accounts, '3cb-preflight-replay-same'));

    expect(PostingTransaction::query()->where('idempotency_key', '3cb-preflight-replay-same')->count())->toBe(1)
        ->and(GlEntry::query()->where('idempotency_key', '3cb-preflight-replay-same')->count())->toBe(2);
});

it('fails preflight on a changed currency-aware replay without writes', function (): void {
    [$debitAccount, $creditAccount] = currencyAwareAccounts();
    $accounts = [$debitAccount, $creditAccount];

    app(GeneralLedgerPostingKernel::class)->post(usdIntentFor($accounts, '3cb-preflight-replay-changed'));

    expect(fn () => app(GeneralLedgerPostingKernel::class)->preflight(
        usdIntentFor($accounts, '3cb-preflight-replay-changed', factor: '1400', lcyAmount: '252000.00')
    ))->toThrow(ValidationException::class, 'different currency-aware economics');

    expect(PostingTransaction::query()->where('idempotency_key', '3cb-preflight-replay-changed')->count())->toBe(1)
        ->and(GlEntry::query()->where('idempotency_key', '3cb-preflight-replay-changed')->count())->toBe(2);
});

it('fails preflight when an existing LCY-only transaction is replayed as currency-aware', function (): void {
    [$debitAccount, $creditAccount] = currencyAwareAccounts();
    $accounts = [$debitAccount, $creditAccount];
    $key = '3cb-preflight-lcy-to-aware';

    app(GeneralLedgerPostingKernel::class)->post(currencyAwareIntent([
        'idempotency_key' => $key,
        'lines' => [
            ['account_id' => $debitAccount->id, 'debit_amount' => '270000.00'],
            ['account_id' => $creditAccount->id, 'credit_amount' => '270000.00'],
        ],
    ]));

    expect(fn () => app(GeneralLedgerPostingKernel::class)->preflight(usdIntentFor($accounts, $key)))
        ->toThrow(ValidationException::class, 'different currency-aware economics');
});

it('fails preflight when an existing currency-aware transaction is replayed as LCY-only', function (): void {
    [$debitAccount, $creditAccount] = currencyAwareAccounts();
    $accounts = [$debitAccount, $creditAccount];
    $key = '3cb-preflight-aware-to-lcy';

    app(GeneralLedgerPostingKernel::class)->post(usdIntentFor($accounts, $key));

    expect(fn () => app(GeneralLedgerPostingKernel::class)->preflight(currencyAwareIntent([
        'idempotency_key' => $key,
        'lines' => [
            ['account_id' => $debitAccount->id, 'debit_amount' => '270000.00'],
            ['account_id' => $creditAccount->id, 'credit_amount' => '270000.00'],
        ],
    ])))->toThrow(ValidationException::class, 'refusing to reuse it for an LCY-only intent');
});

// ---------------------------------------------------------------------------
// H. Reversal trace
// ---------------------------------------------------------------------------

it('persists enough trace to reconstruct currency-aware economics without current FX data', function (): void {
    [$debitAccount, $creditAccount] = currencyAwareAccounts();
    $accounts = [$debitAccount, $creditAccount];
    $key = '3cb-reversal-reconstruction';

    app(GeneralLedgerPostingKernel::class)->post(usdIntentWithLines($accounts, $key, [
        documentMonetaryLine($debitAccount->id, '180.00', '0', lcyDebit: '270000.00'),
        lcyOnlyLine($creditAccount->id, '270000.00', 'ROUNDING', 'credit'),
    ]));

    foreach (GlEntry::query()->where('idempotency_key', $key)->get() as $entry) {
        expect($entry->document_currency_code)->toBe('USD')
            ->and($entry->posting_line_type)->not->toBeNull();

        if ($entry->posting_line_type === 'LCY_ONLY') {
            expect($entry->lcy_only_reason)->toBe('ROUNDING')
                ->and($entry->document_amount)->toBeNull();

            continue;
        }

        expect($entry->lcy_only_reason)->toBeNull()
            ->and(DecimalMath::compare(
                DecimalMath::mul(
                    DecimalMath::amount($entry->document_amount ?? '0'),
                    (string) $entry->currency_factor,
                    DecimalPrecision::CURRENCY_SCALE,
                ),
                (string) $entry->amount,
            ))->toBe(0);
    }
});

// ---------------------------------------------------------------------------
// I. Migration safety / schema
// ---------------------------------------------------------------------------

it('adds nullable, defaultless currency-aware trace columns', function (): void {
    $columns = collect(DB::select(
        "select column_name, is_nullable, column_default from information_schema.columns where table_schema = current_schema() and table_name = 'gl_entries'"
    ))->keyBy('column_name');

    foreach ([
        'document_currency_code',
        'document_debit_amount',
        'document_credit_amount',
        'document_amount',
        'currency_factor',
        'posting_line_type',
        'lcy_only_reason',
    ] as $column) {
        expect($columns->has($column))->toBeTrue()
            ->and($columns->get($column)->is_nullable)->toBe('YES')
            ->and($columns->get($column)->column_default)->toBeNull();
    }

    $postingColumns = collect(DB::select(
        "select column_name, is_nullable, column_default from information_schema.columns where table_schema = current_schema() and table_name = 'posting_transactions'"
    ))->keyBy('column_name');

    expect($postingColumns->has('economic_fingerprint'))->toBeTrue()
        ->and($postingColumns->get('economic_fingerprint')->is_nullable)->toBe('YES')
        ->and($postingColumns->get('economic_fingerprint')->column_default)->toBeNull();
});

it('rolls the currency-aware trace migration down and back up without backfilling history', function (): void {
    $migration = require database_path('migrations/2026_09_15_090000_add_currency_aware_trace_to_posting_tables.php');

    $names = fn (string $table): array => collect(DB::select(
        'select column_name from information_schema.columns where table_schema = current_schema() and table_name = ?',
        [$table]
    ))->pluck('column_name')->all();

    $migration->down();
    expect($names('gl_entries'))->not->toContain('currency_factor', 'document_amount', 'posting_line_type', 'lcy_only_reason')
        ->and($names('posting_transactions'))->not->toContain('economic_fingerprint');

    $migration->up();
    expect($names('gl_entries'))->toContain('currency_factor', 'document_amount', 'posting_line_type', 'lcy_only_reason')
        ->and($names('posting_transactions'))->toContain('economic_fingerprint');

    // Historical rows are never reinterpreted: an LCY-only posting stays null.
    [$debitAccount, $creditAccount] = currencyAwareAccounts();

    $transaction = app(GeneralLedgerPostingKernel::class)->post(currencyAwareIntent([
        'idempotency_key' => '3cb-no-backfill',
        'lines' => [
            ['account_id' => $debitAccount->id, 'debit_amount' => '5.00'],
            ['account_id' => $creditAccount->id, 'credit_amount' => '5.00'],
        ],
    ]));

    expect($transaction->glEntries()->whereNotNull('document_currency_code')->count())->toBe(0)
        ->and($transaction->glEntries()->whereNotNull('posting_line_type')->count())->toBe(0)
        ->and($transaction->glEntries()->whereNotNull('lcy_only_reason')->count())->toBe(0);
});

// ---------------------------------------------------------------------------
// Helpers
// ---------------------------------------------------------------------------

/**
 * @return array{0: ChartOfAccount, 1: ChartOfAccount}
 */
function currencyAwareAccounts(): array
{
    return [
        ChartOfAccount::factory()->create([
            'account_category' => AccountCategory::ASSET,
            'structural_type' => AccountStructuralType::POSTING,
            'direct_posting' => true,
            'blocked' => false,
        ]),
        ChartOfAccount::factory()->create([
            'account_category' => AccountCategory::LIABILITY,
            'structural_type' => AccountStructuralType::POSTING,
            'direct_posting' => true,
            'blocked' => false,
        ]),
    ];
}

/**
 * @param  array<string, mixed>  $overrides
 */
function currencyAwareIntent(array $overrides = []): PostingIntent
{
    return PostingIntent::fromArray(array_replace_recursive([
        'business_id' => null,
        'posting_date' => '2026-07-26',
        'document_date' => '2026-07-26',
        'source_module' => 'finance',
        'source_type' => SourceType::GENERAL_JOURNAL->value,
        'source_id' => null,
        'source_number' => 'PHASE-3CB',
        'document_type' => 'GENERAL_JOURNAL',
        'document_number' => 'PHASE-3CB',
        'transaction_key' => 'phase-3cb-kernel-test',
        'idempotency_key' => 'phase-3cb-kernel-test',
        'description' => 'Phase 3C-B boundary test',
        'currency_code' => 'NGN',
        'exchange_rate' => '1',
        'dimensions' => [],
        'lines' => [],
    ], $overrides));
}

/**
 * @param  array<string, mixed>  $extra
 * @return array<string, mixed>
 */
function documentMonetaryLine(
    int $accountId,
    string $documentDebit,
    string $documentCredit,
    ?string $lcyDebit = null,
    ?string $lcyCredit = null,
    array $extra = [],
): array {
    return array_merge([
        'account_id' => $accountId,
        'debit_amount' => $lcyDebit ?? $documentDebit,
        'credit_amount' => $lcyCredit ?? $documentCredit,
        'line_type' => 'DOCUMENT_MONETARY',
        'document_debit_amount' => $documentDebit,
        'document_credit_amount' => $documentCredit,
    ], $extra);
}

/**
 * @return array<string, mixed>
 */
function lcyOnlyLine(int $accountId, string $lcyAmount, string $reason, string $side = 'debit'): array
{
    return [
        'account_id' => $accountId,
        'debit_amount' => $side === 'debit' ? $lcyAmount : '0',
        'credit_amount' => $side === 'credit' ? $lcyAmount : '0',
        'line_type' => 'LCY_ONLY',
        'lcy_only_reason' => $reason,
    ];
}

/**
 * @param  array{0: ChartOfAccount, 1: ChartOfAccount}  $accounts
 * @param  array<int, array<string, mixed>>  $lines
 */
function usdIntentWithLines(array $accounts, string $idempotencyKey, array $lines, string $factor = '1500'): PostingIntent
{
    return currencyAwareIntent([
        'mode' => 'CURRENCY_AWARE',
        'currency_code' => 'USD',
        'exchange_rate' => $factor,
        'idempotency_key' => $idempotencyKey,
        'lines' => $lines,
    ]);
}

/**
 * @param  array{0: ChartOfAccount, 1: ChartOfAccount}  $accounts
 */
function usdIntentFor(
    array $accounts,
    string $idempotencyKey,
    string $factor = '1500',
    string $documentAmount = '180.00',
    string $lcyAmount = '270000.00',
): PostingIntent {
    return usdIntentWithLines($accounts, $idempotencyKey, [
        documentMonetaryLine($accounts[0]->id, $documentAmount, '0', lcyDebit: $lcyAmount),
        documentMonetaryLine($accounts[1]->id, '0', $documentAmount, lcyCredit: $lcyAmount),
    ], $factor);
}
