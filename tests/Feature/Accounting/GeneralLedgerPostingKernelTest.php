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
use App\Services\Finance\GeneralLedgerService;
use App\Services\PostingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
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

    AccountingPeriod::query()->create([
        'name' => 'FY2026',
        'start_date' => '2026-01-01',
        'end_date' => '2026-12-31',
        'is_closed' => false,
    ]);
});

it('rejects unbalanced posting intents', function (): void {
    [$debitAccount, $creditAccount] = postingKernelAccounts();

    app(GeneralLedgerPostingKernel::class)->post(postingKernelIntent([
        'lines' => [
            ['account_id' => $debitAccount->id, 'debit_amount' => '100.00'],
            ['account_id' => $creditAccount->id, 'credit_amount' => '99.99'],
        ],
    ]));
})->throws(ValidationException::class, 'Posting intent is not balanced');

it('rejects closed period posting dates', function (): void {
    AccountingPeriod::query()->update(['is_closed' => true]);
    [$debitAccount, $creditAccount] = postingKernelAccounts();

    app(GeneralLedgerPostingKernel::class)->post(postingKernelIntent([
        'lines' => [
            ['account_id' => $debitAccount->id, 'debit_amount' => '100.00'],
            ['account_id' => $creditAccount->id, 'credit_amount' => '100.00'],
        ],
    ]));
})->throws(ValidationException::class, 'accounting period for this posting date is closed');

it('rejects accounts that do not allow direct posting', function (): void {
    [$debitAccount, $creditAccount] = postingKernelAccounts();
    $creditAccount->forceFill(['blocked' => true])->save();

    app(GeneralLedgerPostingKernel::class)->post(postingKernelIntent([
        'lines' => [
            ['account_id' => $debitAccount->id, 'debit_amount' => '100.00'],
            ['account_id' => $creditAccount->id, 'credit_amount' => '100.00'],
        ],
    ]));
})->throws(ValidationException::class, 'does not allow direct posting');

it('uses idempotency keys to avoid duplicate gl entries', function (): void {
    [$debitAccount, $creditAccount] = postingKernelAccounts();
    $intent = postingKernelIntent([
        'idempotency_key' => 'phase-1a-idempotent-post',
        'lines' => [
            ['account_id' => $debitAccount->id, 'debit_amount' => '100.00'],
            ['account_id' => $creditAccount->id, 'credit_amount' => '100.00'],
        ],
    ]);

    $first = app(GeneralLedgerPostingKernel::class)->post($intent);
    $second = app(GeneralLedgerPostingKernel::class)->post($intent);

    expect($second->id)->toBe($first->id)
        ->and(PostingTransaction::query()->where('idempotency_key', 'phase-1a-idempotent-post')->count())->toBe(1)
        ->and(GlEntry::query()->where('idempotency_key', 'phase-1a-idempotent-post')->count())->toBe(2);
});

it('assigns unique internal entry and transaction numbers through the kernel', function (): void {
    [$debitAccount, $creditAccount] = postingKernelAccounts();

    foreach (range(1, 3) as $index) {
        app(GeneralLedgerPostingKernel::class)->post(postingKernelIntent([
            'source_number' => "SEQ-{$index}",
            'document_number' => "SEQ-{$index}",
            'idempotency_key' => "phase-1a-seq-{$index}",
            'lines' => [
                ['account_id' => $debitAccount->id, 'debit_amount' => '10.00'],
                ['account_id' => $creditAccount->id, 'credit_amount' => '10.00'],
            ],
        ]));
    }

    expect(GlEntry::query()->count())->toBe(6)
        ->and(GlEntry::query()->distinct('entry_number')->count('entry_number'))->toBe(6)
        ->and(PostingTransaction::query()->distinct('transaction_number')->count('transaction_number'))->toBe(3);
});

it('persists source metadata and links gl entries to the posting transaction', function (): void {
    [$debitAccount, $creditAccount] = postingKernelAccounts();

    $transaction = app(GeneralLedgerPostingKernel::class)->post(postingKernelIntent([
        'business_id' => null,
        'source_module' => 'inventory',
        'source_type' => SourceType::ITEM->value,
        'source_id' => 123,
        'source_number' => 'ITEM-POST',
        'document_type' => 'INVENTORY_ADJUSTMENT',
        'document_number' => 'IA-0001',
        'idempotency_key' => 'phase-1a-source-meta',
        'dimensions' => ['shortcut_dimension_1_code' => 'OPS'],
        'lines' => [
            [
                'account_id' => $debitAccount->id,
                'debit_amount' => '12.345',
                'cost_component' => 'inventory_adjustment',
            ],
            [
                'account_id' => $creditAccount->id,
                'credit_amount' => '12.345',
                'cost_component' => 'inventory_adjustment',
            ],
        ],
    ]));

    $entry = $transaction->glEntries()->firstOrFail();

    expect($entry->posting_transaction_id)->toBe($transaction->id)
        ->and($entry->source_module)->toBe('inventory')
        ->and($entry->source_id)->toBe(123)
        ->and($entry->document_type)->toBe('INVENTORY_ADJUSTMENT')
        ->and($entry->idempotency_key)->toBe('phase-1a-source-meta')
        ->and($entry->shortcut_dimension_1_code)->toBe('OPS');
});

it('routes GeneralLedgerService posting through the kernel', function (): void {
    [$debitAccount, $creditAccount] = postingKernelAccounts();

    app(GeneralLedgerService::class)->post([
        ['account_id' => $debitAccount->id, 'debit' => '50.00'],
        ['account_id' => $creditAccount->id, 'credit' => '50.00'],
    ], [
        'posting_date' => '2026-07-26',
        'document_number' => 'GL-TEST',
        'document_type' => 'GENERAL_JOURNAL',
        'source_type' => SourceType::GENERAL_JOURNAL->value,
        'source_number' => 'GL-TEST',
        'idempotency_key' => 'general-ledger-service-kernel-route',
    ]);

    expect(PostingTransaction::query()->where('idempotency_key', 'general-ledger-service-kernel-route')->exists())->toBeTrue()
        ->and(GlEntry::query()->where('idempotency_key', 'general-ledger-service-kernel-route')->count())->toBe(2);
});

it('guards legacy PostingService direct entries with posting date validation', function (): void {
    AccountingPeriod::query()->update(['is_closed' => true]);
    [$account] = postingKernelAccounts();

    app(PostingService::class)->createGlEntry([
        'chart_of_account_id' => $account->id,
        'debit_amount' => '10.00',
        'credit_amount' => '0.00',
        'posting_date' => '2026-07-26',
        'document_type' => 'LEGACY',
        'document_number' => 'LEGACY-DATE',
    ]);
})->throws(ValidationException::class, 'accounting period for this posting date is closed');

it('guards legacy PostingService direct entries with account validation', function (): void {
    [$account] = postingKernelAccounts();
    $account->forceFill(['blocked' => true])->save();

    app(PostingService::class)->createGlEntry([
        'chart_of_account_id' => $account->id,
        'debit_amount' => '10.00',
        'credit_amount' => '0.00',
        'posting_date' => '2026-07-26',
        'document_type' => 'LEGACY',
        'document_number' => 'LEGACY-ACCOUNT',
    ]);
})->throws(ValidationException::class, 'does not allow direct posting');

/**
 * @return array{0: ChartOfAccount, 1: ChartOfAccount}
 */
function postingKernelAccounts(): array
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
function postingKernelIntent(array $overrides = []): PostingIntent
{
    return PostingIntent::fromArray(array_replace_recursive([
        'business_id' => null,
        'posting_date' => '2026-07-26',
        'document_date' => '2026-07-26',
        'source_module' => 'finance',
        'source_type' => SourceType::GENERAL_JOURNAL->value,
        'source_id' => null,
        'source_number' => 'PHASE-1A',
        'document_type' => 'GENERAL_JOURNAL',
        'document_number' => 'PHASE-1A',
        'transaction_key' => 'phase-1a-kernel-test',
        'idempotency_key' => 'phase-1a-kernel-test',
        'description' => 'Phase 1A kernel test',
        'currency_code' => 'NGN',
        'exchange_rate' => '1',
        'dimensions' => [],
        'lines' => [],
    ], $overrides));
}
