<?php

declare(strict_types=1);

use App\Enums\AccountCategory;
use App\Enums\AccountStructuralType;
use App\Models\ChartOfAccount;
use App\Models\GlEntry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;

uses(RefreshDatabase::class);

it('does not double count a single gl entry create', function (): void {
    $account = glBalanceCacheAccount();

    glBalanceCacheEntry($account, 1800);

    expect((float) $account->fresh()->balance)->toBe(1800.0);
});

it('caches the signed ledger sum for debit and credit entries', function (): void {
    $account = glBalanceCacheAccount();

    glBalanceCacheEntry($account, 1800);
    glBalanceCacheEntry($account, -500);

    expect((float) $account->fresh()->balance)->toBe(1300.0);
});

it('recalculates when a gl entry amount changes', function (): void {
    $account = glBalanceCacheAccount();
    $entry = glBalanceCacheEntry($account, 100);

    glBalanceCacheUpdateEntryAmount($entry, 150);

    expect((float) $account->fresh()->balance)->toBe(150.0);
});

it('recalculates old and new accounts when a gl entry moves account', function (): void {
    $oldAccount = glBalanceCacheAccount();
    $newAccount = glBalanceCacheAccount();
    $entry = glBalanceCacheEntry($oldAccount, 100);

    $entry->update(['chart_of_account_id' => $newAccount->id]);

    expect((float) $oldAccount->fresh()->balance)->toBe(0.0)
        ->and((float) $newAccount->fresh()->balance)->toBe(100.0);
});

it('recalculates old and new accounts when account and amount change together', function (): void {
    $oldAccount = glBalanceCacheAccount();
    $newAccount = glBalanceCacheAccount();
    $entry = glBalanceCacheEntry($oldAccount, 100);

    $entry->update([
        'chart_of_account_id' => $newAccount->id,
        'debit_amount' => 150,
        'credit_amount' => 0,
        'amount' => 150,
    ]);

    expect((float) $oldAccount->fresh()->balance)->toBe(0.0)
        ->and((float) $newAccount->fresh()->balance)->toBe(150.0);
});

it('recalculates when a gl entry is deleted', function (): void {
    $account = glBalanceCacheAccount();
    $entry = glBalanceCacheEntry($account, 100);

    $entry->delete();

    expect((float) $account->fresh()->balance)->toBe(0.0);
});

it('reports cached account balance drift without mutating data', function (): void {
    $account = glBalanceCacheAccount();
    glBalanceCacheEntry($account, 1800);
    $account->forceFill(['balance' => 3600])->saveQuietly();

    Artisan::call('biwms:gl-balance-reconcile', ['--json' => true]);

    $report = json_decode(Artisan::output(), true);

    expect($report['summary']['mismatch_count'])->toBe(1)
        ->and($report['findings'][0]['account_id'])->toBe($account->id)
        ->and((float) $account->fresh()->balance)->toBe(3600.0);
});

it('sync repair command restores cached account balances from gl entries', function (): void {
    $account = glBalanceCacheAccount();
    glBalanceCacheEntry($account, 1800);
    $account->forceFill(['balance' => 3600])->saveQuietly();

    Artisan::call('accounting:sync-balances');

    expect((float) $account->fresh()->balance)->toBe(1800.0);
});

function glBalanceCacheAccount(): ChartOfAccount
{
    return ChartOfAccount::factory()->create([
        'account_category' => AccountCategory::ASSET,
        'structural_type' => AccountStructuralType::POSTING,
        'direct_posting' => true,
        'blocked' => false,
        'balance' => 0,
    ]);
}

function glBalanceCacheEntry(ChartOfAccount $account, float $amount): GlEntry
{
    return GlEntry::query()->create([
        'entry_number' => glBalanceCacheNextEntryNumber(),
        'transaction_number' => glBalanceCacheNextTransactionNumber(),
        'chart_of_account_id' => $account->id,
        'debit_amount' => $amount > 0 ? $amount : 0,
        'credit_amount' => $amount < 0 ? abs($amount) : 0,
        'amount' => $amount,
        'source_type' => 'GENERAL_JOURNAL',
        'document_type' => 'TEST',
        'document_number' => 'GL-CACHE-'.glBalanceCacheNextEntryNumber(),
        'document_date' => '2026-08-14',
        'posting_date' => '2026-08-14',
        'description' => 'G/L cache regression entry',
    ]);
}

function glBalanceCacheUpdateEntryAmount(GlEntry $entry, float $amount): void
{
    $entry->update([
        'debit_amount' => $amount > 0 ? $amount : 0,
        'credit_amount' => $amount < 0 ? abs($amount) : 0,
        'amount' => $amount,
    ]);
}

function glBalanceCacheNextEntryNumber(): int
{
    return ((int) GlEntry::query()->max('entry_number')) + 1;
}

function glBalanceCacheNextTransactionNumber(): int
{
    return ((int) GlEntry::query()->max('transaction_number')) + 1;
}
