<?php

declare(strict_types=1);

use App\Services\BankReconciliationService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('auto-matches a seeded statement line to a bank ledger entry', function (): void {
    $fixture = $this->createBankReconciliationFixture();

    $matchedEntry = app(BankReconciliationService::class)->autoMatch($fixture['statementLine']);

    $fixture['statementLine']->refresh();
    $fixture['ledgerEntry']->refresh();

    expect($matchedEntry)->not->toBeNull()
        ->and($matchedEntry?->id)->toBe($fixture['ledgerEntry']->id)
        ->and($fixture['statementLine']->reconciled)->toBeTrue()
        ->and($fixture['statementLine']->bank_account_ledger_entry_id)->toBe($fixture['ledgerEntry']->id)
        ->and((string) $fixture['ledgerEntry']->status->value)->toBe('reconciled');
});
