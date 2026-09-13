<?php

declare(strict_types=1);

use App\Enums\ApprovalStatus;
use App\Enums\IncomeBalanceType;
use App\Enums\ItemLedgerEntryType;
use App\Enums\ItemType;
use App\Enums\PurchaseOrderStatus;
use App\Enums\SourceType;
use App\Exceptions\BusinessException;
use App\Models\AccountingPeriod;
use App\Models\AuditTrail;
use App\Models\Business;
use App\Models\ChartOfAccount;
use App\Models\Currency;
use App\Models\GeneralBusinessPostingGroup;
use App\Models\GeneralLedgerSetup;
use App\Models\GeneralPostingSetup;
use App\Models\GeneralProductPostingGroup;
use App\Models\GlEntry;
use App\Models\InventoryPostingGroup;
use App\Models\InventoryPostingSetup;
use App\Models\Item;
use App\Models\ItemLedgerEntry;
use App\Models\Location;
use App\Models\NumberSeries;
use App\Models\NumberSeriesLine;
use App\Models\Payment;
use App\Models\PostedPurchaseCreditMemo;
use App\Models\PostedPurchaseInvoice;
use App\Models\PostingTransaction;
use App\Models\PurchaseInvoice;
use App\Models\PurchaseOrder;
use App\Models\UnitOfMeasure;
use App\Models\User;
use App\Models\ValueEntry;
use App\Models\Vendor;
use App\Models\VendorLedgerEntry;
use App\Models\VendorPostingGroup;
use App\Services\Accounting\GeneralLedgerPostingKernel;
use App\Services\Finance\GeneralLedgerService;
use App\Services\Finance\VendorLedgerHistoricalNormalizationService;
use App\Services\Purchase\PurchaseInvoiceIncidentRepairService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

test('purchase invoice incident dry run reports plan and writes nothing', function (): void {
    $fixture = purchaseInvoiceIncidentFixture();

    $before = purchaseInvoiceIncidentCounts();

    $result = app(PurchaseInvoiceIncidentRepairService::class)->analyze();

    expect($result['already_repaired'])->toBeFalse()
        ->and($result['execution_allowed'])->toBeTrue()
        ->and($result['amount'])->toBe('262866.20')
        ->and($result['gl']['malformed_entry_count'])->toBe(3)
        ->and($result['gl']['debit_total'])->toBe('262866.20')
        ->and($result['gl']['credit_total'])->toBe('262866.20')
        ->and($result['vendor_ledger']['known_bad_state'])->toBeTrue()
        ->and($result['item_ledger_entries'])->toHaveCount(2);

    expect(purchaseInvoiceIncidentCounts())->toBe($before)
        ->and($fixture['numberSeriesLine']->fresh()->last_no_used)->toBe(7);

    Artisan::call('biwms:repair-purchase-incident', ['--dry-run' => true]);
    expect(Artisan::output())->toContain('Mode: dry-run. No data was changed.')
        ->and(purchaseInvoiceIncidentCounts())->toBe($before);
});

test('purchase invoice incident execute repairs gl vendor ledger and ile actual costs', function (): void {
    $fixture = purchaseInvoiceIncidentFixture();

    $result = app(PurchaseInvoiceIncidentRepairService::class)->execute(actorId: $fixture['user']->id);

    expect($result['executed'])->toBeTrue()
        ->and($result['neutralizer_gl_entry_ids'])->toHaveCount(3)
        ->and($result['replacement_posting_transaction_id'])->not->toBeNull()
        ->and(PurchaseInvoice::query()->where('document_number', 'PI-2026-00001')->count())->toBe(1)
        ->and(PurchaseOrder::query()->where('order_number', 'PO-2026-00002')->count())->toBe(1)
        ->and(ValueEntry::query()->where('document_no', 'PI-2026-00001')->where('value_entry_state', 'actual')->count())->toBe(2)
        ->and(ItemLedgerEntry::query()->where('document_type', 'PURCHASE_RECEIPT')->where('document_number', 'PO-2026-00002')->count())->toBe(2)
        ->and($fixture['numberSeriesLine']->fresh()->last_no_used)->toBe(7);

    purchaseInvoiceIncidentExpectTransactionGroupsBalanced();
    purchaseInvoiceIncidentExpectVendorExposureReconciles($fixture['payablesAccount']->id);
    purchaseInvoiceIncidentExpectReceiptCostsSynced();

    $vendorLedger = VendorLedgerEntry::query()->where('document_number', 'PI-2026-00001')->firstOrFail();
    expect((float) $vendorLedger->debit_amount)->toBe(0.0)
        ->and((float) $vendorLedger->credit_amount)->toBe(262866.2)
        ->and((float) $vendorLedger->amount)->toBe(262866.2)
        ->and((float) $vendorLedger->remaining_amount)->toBe(262866.2)
        ->and($vendorLedger->open)->toBeTrue()
        ->and($vendorLedger->fully_applied)->toBeFalse()
        ->and($vendorLedger->glEntry?->posting_transaction_id)->toBe($result['replacement_posting_transaction_id']);

    $replacement = PostingTransaction::query()->findOrFail($result['replacement_posting_transaction_id']);
    expect($replacement->idempotency_key)->toBe('PURCHASE_INVOICE_INCIDENT_REPAIR:PI-2026-00001:REPLACEMENT')
        ->and(round((float) $replacement->glEntries()->sum('debit_amount'), 2))->toBe(262866.2)
        ->and(round((float) $replacement->glEntries()->sum('credit_amount'), 2))->toBe(262866.2);

    expect(AuditTrail::query()
        ->where('action', 'purchase_invoice_incident_repaired')
        ->where('document_no', 'PI-2026-00001')
        ->exists())->toBeTrue();
});

test('purchase invoice incident repair is idempotent after success', function (): void {
    $fixture = purchaseInvoiceIncidentFixture();

    app(PurchaseInvoiceIncidentRepairService::class)->execute();
    $afterFirst = purchaseInvoiceIncidentCounts();

    $dryRun = app(PurchaseInvoiceIncidentRepairService::class)->analyze();
    $second = app(PurchaseInvoiceIncidentRepairService::class)->execute();

    expect($dryRun['already_repaired'])->toBeTrue()
        ->and($second['idempotent'])->toBeTrue()
        ->and($second['executed'])->toBeFalse()
        ->and(purchaseInvoiceIncidentCounts())->toBe($afterFirst);
});

test('purchase invoice incident repair aborts when malformed gl amount changes', function (): void {
    $fixture = purchaseInvoiceIncidentFixture();

    GlEntry::query()->where('transaction_number', 101)->firstOrFail()->update(['debit_amount' => 49000, 'amount' => 49000]);

    expect(fn () => app(PurchaseInvoiceIncidentRepairService::class)->execute())
        ->toThrow(BusinessException::class, 'G/L accounts or amounts');
});

test('purchase invoice incident repair aborts when malformed gl account changes', function (): void {
    $fixture = purchaseInvoiceIncidentFixture();
    $wrongAccount = purchaseInvoiceIncidentAccount('99998', 'Wrong AP', 'payable');

    GlEntry::query()->where('transaction_number', 104)->firstOrFail()->update([
        'chart_of_account_id' => $wrongAccount->id,
    ]);

    expect(fn () => app(PurchaseInvoiceIncidentRepairService::class)->execute())
        ->toThrow(BusinessException::class, 'G/L accounts or amounts');
});

test('purchase invoice incident repair aborts when a required gl row is missing', function (): void {
    $fixture = purchaseInvoiceIncidentFixture();

    GlEntry::query()->where('transaction_number', 103)->delete();

    expect(fn () => app(PurchaseInvoiceIncidentRepairService::class)->execute())
        ->toThrow(BusinessException::class, 'G/L entry shape');
});

test('purchase invoice incident repair aborts when an extra malformed gl row exists', function (): void {
    $fixture = purchaseInvoiceIncidentFixture();

    purchaseInvoiceIncidentMalformedGlEntry(
        entryNumber: 999,
        transactionNumber: 101,
        account: $fixture['grniAccount'],
        debit: '1.00',
        credit: '0.00',
        itemLedgerEntryId: null,
    );

    expect(fn () => app(PurchaseInvoiceIncidentRepairService::class)->execute())
        ->toThrow(BusinessException::class, 'G/L entry shape');
});

test('purchase invoice incident repair aborts when vendor ledger state changed', function (): void {
    $fixture = purchaseInvoiceIncidentFixture();

    VendorLedgerEntry::query()->where('document_number', 'PI-2026-00001')->update([
        'remaining_amount' => '100.00',
    ]);

    expect(fn () => app(PurchaseInvoiceIncidentRepairService::class)->execute())
        ->toThrow(BusinessException::class, 'vendor ledger entry');
});

test('purchase invoice incident repair rolls back when an internal repair step fails', function (): void {
    $fixture = purchaseInvoiceIncidentFixture();
    $before = purchaseInvoiceIncidentCounts();

    expect(fn () => app(PurchaseInvoiceIncidentRepairService::class)->execute(simulateFailureAfterGlCorrection: true))
        ->toThrow(BusinessException::class, 'Simulated repair failure');

    expect(purchaseInvoiceIncidentCounts())->toBe($before);
    purchaseInvoiceIncidentExpectTransactionGroupsUnbalanced();
});

test('legacy neutralizer mirrors a one-sided debit legacy gl entry', function (): void {
    purchaseInvoiceIncidentFixture();

    $original = GlEntry::query()->where('entry_number', 1)->firstOrFail();

    $correction = app(GeneralLedgerService::class)->appendLegacyNeutralizer(
        $original,
        reason: 'Focused legacy neutralizer test',
    );

    expect((float) $correction->debit_amount)->toBe(0.0)
        ->and((float) $correction->credit_amount)->toBe(50000.0)
        ->and((int) $correction->chart_of_account_id)->toBe((int) $original->chart_of_account_id)
        ->and((int) $correction->business_id)->toBe((int) $original->business_id)
        ->and((int) $correction->reversal_of_gl_entry_id)->toBe((int) $original->getKey())
        ->and((int) $correction->transaction_number)->toBe((int) $original->transaction_number)
        ->and($correction->posting_transaction_id)->not->toBeNull();

    $original->refresh();
    expect((float) $original->debit_amount)->toBe(50000.0)
        ->and($original->posting_transaction_id)->toBeNull()
        ->and($original->reversal_of_gl_entry_id)->toBeNull();

    purchaseInvoiceIncidentExpectTransactionGroupBalancedFor((int) $original->transaction_number);
});

test('legacy neutralizer mirrors a one-sided credit legacy gl entry', function (): void {
    purchaseInvoiceIncidentFixture();

    $original = GlEntry::query()->where('entry_number', 3)->firstOrFail();

    $correction = app(GeneralLedgerService::class)->appendLegacyNeutralizer($original);

    expect((float) $correction->debit_amount)->toBe(262866.2)
        ->and((float) $correction->credit_amount)->toBe(0.0)
        ->and((int) $correction->chart_of_account_id)->toBe((int) $original->chart_of_account_id)
        ->and((int) $correction->reversal_of_gl_entry_id)->toBe((int) $original->getKey())
        ->and((int) $correction->transaction_number)->toBe((int) $original->transaction_number);

    $original->refresh();
    expect($original->posting_transaction_id)->toBeNull()
        ->and($original->reversal_of_gl_entry_id)->toBeNull();

    purchaseInvoiceIncidentExpectTransactionGroupBalancedFor((int) $original->transaction_number);
});

test('legacy neutralizer api exposes no caller-controlled accounting shape', function (): void {
    $parameterNames = fn (ReflectionMethod $method): array => array_map(
        fn (ReflectionParameter $parameter): string => $parameter->getName(),
        $method->getParameters(),
    );

    $hasArrayParameter = fn (ReflectionMethod $method): bool => collect($method->getParameters())
        ->contains(fn (ReflectionParameter $parameter): bool => $parameter->getType()?->getName() === 'array');

    $kernel = new ReflectionMethod(GeneralLedgerPostingKernel::class, 'appendLegacyNeutralizer');
    $service = new ReflectionMethod(GeneralLedgerService::class, 'appendLegacyNeutralizer');

    expect($parameterNames($kernel))->toBe(['originalEntry', 'actorId', 'incidentIdentifier', 'reason', 'incidentCurrencyCode'])
        ->and($parameterNames($service))->toBe(['originalEntry', 'actorId', 'incidentIdentifier', 'reason', 'incidentCurrencyCode'])
        ->and($hasArrayParameter($kernel))->toBeFalse()
        ->and($hasArrayParameter($service))->toBeFalse();
});

test('legacy neutralizer rejects gl entries already linked to a posting transaction', function (): void {
    purchaseInvoiceIncidentFixture();

    $postingTransaction = PostingTransaction::query()->create([
        'business_id' => 1,
        'source_module' => 'finance',
        'source_type' => SourceType::VENDOR->value,
        'source_number' => 'LEGACY-GUARD',
        'document_type' => 'PURCHASE_INVOICE',
        'document_number' => 'PI-2026-00001',
        'transaction_key' => 'legacy-guard-test',
        'idempotency_key' => 'legacy-guard-test',
        'transaction_number' => 900,
        'posting_date' => '2026-09-10',
        'status' => 'completed',
    ]);

    $original = GlEntry::query()->where('entry_number', 1)->firstOrFail();
    $original->forceFill(['posting_transaction_id' => $postingTransaction->id])->saveQuietly();

    expect(fn () => app(GeneralLedgerService::class)->appendLegacyNeutralizer($original))
        ->toThrow(BusinessException::class, 'not a pre-kernel legacy row');
});

test('legacy neutralizer rejects non one-sided legacy gl entries', function (): void {
    $fixture = purchaseInvoiceIncidentFixture();

    $balanced = purchaseInvoiceIncidentRawGlEntry(9001, 9001, $fixture['grniAccount'], '10.00', '10.00');
    $zero = purchaseInvoiceIncidentRawGlEntry(9002, 9002, $fixture['grniAccount'], '0.00', '0.00');

    expect(fn () => app(GeneralLedgerService::class)->appendLegacyNeutralizer($balanced))
        ->toThrow(BusinessException::class, 'not one-sided');

    expect(fn () => app(GeneralLedgerService::class)->appendLegacyNeutralizer($zero))
        ->toThrow(BusinessException::class, 'not one-sided');
});

test('legacy neutralizer is idempotent and never appends a duplicate', function (): void {
    purchaseInvoiceIncidentFixture();

    $original = GlEntry::query()->where('entry_number', 1)->firstOrFail();

    $first = app(GeneralLedgerService::class)->appendLegacyNeutralizer($original);
    $second = app(GeneralLedgerService::class)->appendLegacyNeutralizer($original->fresh());

    expect((int) $second->getKey())->toBe((int) $first->getKey())
        ->and(GlEntry::query()->where('reversal_of_gl_entry_id', $original->getKey())->count())->toBe(1);
});

test('legacy neutralizer refuses to hijack an unrelated posting transaction', function (): void {
    purchaseInvoiceIncidentFixture();

    $original = GlEntry::query()->where('entry_number', 1)->firstOrFail();

    PostingTransaction::query()->create([
        'business_id' => 1,
        'source_module' => 'finance',
        'source_type' => SourceType::VENDOR->value,
        'source_number' => 'UNRELATED',
        'document_type' => 'PURCHASE_INVOICE',
        'document_number' => 'PI-2026-00001',
        'transaction_key' => 'unrelated:hijack',
        'idempotency_key' => 'unrelated:hijack',
        'transaction_number' => (int) $original->transaction_number,
        'posting_date' => '2026-09-10',
        'status' => 'completed',
    ]);

    expect(fn () => app(GeneralLedgerService::class)->appendLegacyNeutralizer($original))
        ->toThrow(BusinessException::class, 'already owned by another posting transaction');
});

test('purchase invoice incident repair preserves original malformed gl rows', function (): void {
    purchaseInvoiceIncidentFixture();

    $columns = ['id', 'debit_amount', 'credit_amount', 'posting_transaction_id', 'reversal_of_gl_entry_id'];

    $originals = GlEntry::query()
        ->whereIn('transaction_number', [101, 103, 104])
        ->orderBy('entry_number')
        ->get($columns)
        ->toArray();

    app(PurchaseInvoiceIncidentRepairService::class)->execute();

    $after = GlEntry::query()
        ->whereIn('id', collect($originals)->pluck('id'))
        ->orderBy('entry_number')
        ->get($columns)
        ->toArray();

    expect($after)->toBe($originals)
        ->and(collect($after)->every(fn (array $row): bool => $row['reversal_of_gl_entry_id'] === null))->toBeTrue();
});

test('replacement invoice liability posting uses the canonical kernel path', function (): void {
    purchaseInvoiceIncidentFixture();

    $result = app(PurchaseInvoiceIncidentRepairService::class)->execute();

    $replacement = PostingTransaction::query()->findOrFail($result['replacement_posting_transaction_id']);
    $lines = $replacement->glEntries()->get();

    expect($replacement->transaction_key)->toBe('PURCHASE_INVOICE_INCIDENT_REPAIR:PI-2026-00001:REPLACEMENT')
        ->and($replacement->idempotency_key)->toBe('PURCHASE_INVOICE_INCIDENT_REPAIR:PI-2026-00001:REPLACEMENT')
        ->and($replacement->idempotency_key)->not->toStartWith('LEGACY_GL_NEUTRALIZER_TX')
        ->and($lines)->toHaveCount(2)
        ->and(in_array((int) $replacement->transaction_number, [101, 103, 104], true))->toBeFalse()
        ->and(round((float) $lines->sum('debit_amount'), 2))->toBe(262866.2)
        ->and(round((float) $lines->sum('credit_amount'), 2))->toBe(262866.2);
});

test('vendor ledger historical normalization converts the malformed invoice representation to canonical', function (): void {
    $fixture = purchaseInvoiceIncidentFixture();
    $expectedBefore = purchaseInvoiceIncidentExpectedVendorBefore();
    $entry = VendorLedgerEntry::query()->where('document_number', 'PI-2026-00001')->firstOrFail();
    $payablesGl = purchaseInvoiceIncidentPayablesControlEntry($fixture);

    $normalized = app(VendorLedgerHistoricalNormalizationService::class)->normalizeMalformedPurchaseInvoice(
        $entry->getKey(),
        $expectedBefore,
        $payablesGl->getKey(),
        'focused-test-incident',
        'Focused historical normalization test',
    );

    expect((float) $normalized->debit_amount)->toBe(0.0)
        ->and((float) $normalized->credit_amount)->toBe(262866.2)
        ->and((float) $normalized->amount)->toBe(262866.2)
        ->and((float) $normalized->remaining_amount)->toBe(262866.2)
        ->and((float) $normalized->original_debit_amount)->toBe(0.0)
        ->and((float) $normalized->original_credit_amount)->toBe(262866.2)
        ->and((float) $normalized->running_balance)->toBe(262866.2)
        ->and($normalized->open)->toBeTrue()
        ->and($normalized->fully_applied)->toBeFalse()
        ->and((int) $normalized->gl_entry_id)->toBe((int) $payablesGl->getKey())
        ->and(VendorLedgerEntry::query()->where('document_number', 'PI-2026-00001')->count())->toBe(1)
        ->and(round((float) VendorLedgerEntry::query()->where('document_number', 'PI-2026-00001')->sum(DB::raw('credit_amount - debit_amount')), 2))->toBe(262866.2);
});

test('vendor ledger historical normalization is idempotent', function (): void {
    $fixture = purchaseInvoiceIncidentFixture();
    $expectedBefore = purchaseInvoiceIncidentExpectedVendorBefore();
    $entry = VendorLedgerEntry::query()->where('document_number', 'PI-2026-00001')->firstOrFail();
    $payablesGl = purchaseInvoiceIncidentPayablesControlEntry($fixture);
    $service = app(VendorLedgerHistoricalNormalizationService::class);

    $service->normalizeMalformedPurchaseInvoice($entry->getKey(), $expectedBefore, $payablesGl->getKey(), 'focused-test-incident', 'reason');
    $second = $service->normalizeMalformedPurchaseInvoice($entry->getKey(), $expectedBefore, $payablesGl->getKey(), 'focused-test-incident', 'reason');

    expect((float) $second->debit_amount)->toBe(0.0)
        ->and((float) $second->credit_amount)->toBe(262866.2)
        ->and(VendorLedgerEntry::query()->where('document_number', 'PI-2026-00001')->count())->toBe(1)
        ->and(AuditTrail::query()->where('action', 'vendor_ledger_historical_normalization_applied')->count())->toBe(1);
});

test('vendor ledger historical normalization fails closed on a mismatched expected before-state', function (string $key, mixed $wrong): void {
    $fixture = purchaseInvoiceIncidentFixture();
    $expectedBefore = purchaseInvoiceIncidentExpectedVendorBefore();
    $expectedBefore[$key] = $wrong;
    $entry = VendorLedgerEntry::query()->where('document_number', 'PI-2026-00001')->firstOrFail();
    $payablesGl = purchaseInvoiceIncidentPayablesControlEntry($fixture);

    expect(fn () => app(VendorLedgerHistoricalNormalizationService::class)->normalizeMalformedPurchaseInvoice(
        $entry->getKey(),
        $expectedBefore,
        $payablesGl->getKey(),
        'focused-test-incident',
        'reason',
    ))->toThrow(BusinessException::class, 'expected before-state');
})->with([
    'vendor' => ['vendor_id', 987654],
    'business' => ['business_id', 987654],
    'document' => ['document_number', 'PI-WRONG'],
    'source' => ['source_id', 987654],
    'source_type' => ['source_type', 'Wrong\\Source'],
    'debit' => ['debit_amount', '1.00'],
    'credit' => ['credit_amount', '1.00'],
    'amount' => ['amount', '1.00'],
    'remaining' => ['remaining_amount', '1.00'],
    'open' => ['open', false],
]);

test('vendor ledger historical normalization rejects a non purchase-invoice ledger entry', function (): void {
    $fixture = purchaseInvoiceIncidentFixture();

    $paymentEntry = VendorLedgerEntry::query()->create([
        'entry_number' => 9001,
        'vendor_id' => $fixture['vendor']->id,
        'business_id' => 1,
        'document_type' => 'PAYMENT',
        'document_number' => 'PAY-2026-00004',
        'description' => 'Unrelated historical payment',
        'posting_date' => '2026-09-10',
        'document_date' => '2026-09-10',
        'debit_amount' => '100.00',
        'credit_amount' => '0',
        'amount' => '-100.00',
        'running_balance' => '262766.20',
        'remaining_amount' => '0',
        'open' => false,
        'fully_applied' => false,
        'currency_code' => 'NGN',
        'original_debit_amount' => '100.00',
        'original_credit_amount' => '0',
        'currency_factor' => '1',
        'general_business_posting_group_id' => $fixture['vendor']->general_business_posting_group_id,
        'vendor_posting_group_id' => $fixture['vendor']->vendor_posting_group_id,
        'created_by' => $fixture['user']->id,
    ]);

    expect(fn () => app(VendorLedgerHistoricalNormalizationService::class)->normalizeMalformedPurchaseInvoice(
        $paymentEntry->getKey(),
        [],
        1,
        'focused-test-incident',
        'reason',
    ))->toThrow(BusinessException::class, 'purchase-invoice');
});

test('vendor ledger historical normalization fails closed when the invoice has duplicate ledger rows', function (): void {
    $fixture = purchaseInvoiceIncidentFixture();
    $expectedBefore = purchaseInvoiceIncidentExpectedVendorBefore();
    $entry = VendorLedgerEntry::query()->where('document_number', 'PI-2026-00001')->firstOrFail();
    $payablesGl = purchaseInvoiceIncidentPayablesControlEntry($fixture);

    VendorLedgerEntry::query()->create([
        'entry_number' => 9002,
        'vendor_id' => $fixture['vendor']->id,
        'business_id' => 1,
        'document_type' => 'PURCHASE_INVOICE',
        'document_number' => 'PI-2026-00001',
        'description' => 'Duplicate historical invoice row',
        'posting_date' => '2026-09-10',
        'document_date' => '2026-09-10',
        'debit_amount' => '262866.20',
        'credit_amount' => '0',
        'amount' => '262866.20',
        'running_balance' => '262866.20',
        'remaining_amount' => '262866.20',
        'open' => true,
        'fully_applied' => false,
        'currency_code' => 'NGN',
        'original_debit_amount' => '262866.20',
        'original_credit_amount' => '0',
        'currency_factor' => '1',
        'general_business_posting_group_id' => $fixture['vendor']->general_business_posting_group_id,
        'vendor_posting_group_id' => $fixture['vendor']->vendor_posting_group_id,
        'created_by' => $fixture['user']->id,
    ]);

    expect(fn () => app(VendorLedgerHistoricalNormalizationService::class)->normalizeMalformedPurchaseInvoice(
        $entry->getKey(),
        $expectedBefore,
        $payablesGl->getKey(),
        'focused-test-incident',
        'reason',
    ))->toThrow(BusinessException::class, 'not uniquely identifiable');
});

test('vendor ledger historical normalization records complete before and after audit evidence', function (): void {
    $fixture = purchaseInvoiceIncidentFixture();
    $expectedBefore = purchaseInvoiceIncidentExpectedVendorBefore();
    $entry = VendorLedgerEntry::query()->where('document_number', 'PI-2026-00001')->firstOrFail();
    $payablesGl = purchaseInvoiceIncidentPayablesControlEntry($fixture);

    app(VendorLedgerHistoricalNormalizationService::class)->normalizeMalformedPurchaseInvoice(
        $entry->getKey(),
        $expectedBefore,
        $payablesGl->getKey(),
        'focused-test-incident',
        'Focused audit test',
        $fixture['user']->id,
    );

    $audit = AuditTrail::query()->where('action', 'vendor_ledger_historical_normalization_applied')->latest('id')->firstOrFail();

    expect((float) $audit->old_values['debit_amount'])->toBe(262866.2)
        ->and((float) $audit->old_values['credit_amount'])->toBe(0.0)
        ->and((float) $audit->old_values['original_credit_amount'])->toBe(0.0)
        ->and((float) $audit->new_values['debit_amount'])->toBe(0.0)
        ->and((float) $audit->new_values['credit_amount'])->toBe(262866.2)
        ->and((float) $audit->new_values['original_credit_amount'])->toBe(262866.2)
        ->and($audit->metadata['incident_identifier'])->toBe('focused-test-incident')
        ->and((int) $audit->metadata['vendor_ledger_entry_id'])->toBe((int) $entry->getKey())
        ->and((int) $audit->metadata['payables_gl_entry_id'])->toBe((int) $payablesGl->getKey())
        ->and($audit->document_no)->toBe('PI-2026-00001');
});

test('vendor ledger historical normalization creates no fake business documents', function (): void {
    $fixture = purchaseInvoiceIncidentFixture();
    $expectedBefore = purchaseInvoiceIncidentExpectedVendorBefore();
    $entry = VendorLedgerEntry::query()->where('document_number', 'PI-2026-00001')->firstOrFail();
    $payablesGl = purchaseInvoiceIncidentPayablesControlEntry($fixture);

    $before = [
        Payment::query()->count(),
        PostedPurchaseCreditMemo::query()->count(),
        PurchaseInvoice::query()->count(),
        VendorLedgerEntry::query()->count(),
    ];

    app(VendorLedgerHistoricalNormalizationService::class)->normalizeMalformedPurchaseInvoice(
        $entry->getKey(),
        $expectedBefore,
        $payablesGl->getKey(),
        'focused-test-incident',
        'reason',
    );

    expect([
        Payment::query()->count(),
        PostedPurchaseCreditMemo::query()->count(),
        PurchaseInvoice::query()->count(),
        VendorLedgerEntry::query()->count(),
    ])->toBe($before)
        ->and(Payment::query()->count())->toBe(0)
        ->and(PostedPurchaseCreditMemo::query()->count())->toBe(0);
});

test('posted vendor ledger entries remain immutable for ordinary application code', function (): void {
    purchaseInvoiceIncidentFixture();

    $entry = VendorLedgerEntry::query()->where('document_number', 'PI-2026-00001')->firstOrFail();

    expect(fn () => $entry->update(['debit_amount' => '0', 'credit_amount' => '262866.20']))
        ->toThrow(BusinessException::class, 'immutable');
});

test('purchase invoice incident repair repairs the exact known incident identity', function (): void {
    $fixture = purchaseInvoiceIncidentFixture();

    expect((int) $fixture['invoice']->getKey())->toBe(3)
        ->and((int) $fixture['order']->getKey())->toBe(2)
        ->and((int) $fixture['invoice']->vendor_id)->toBe(2)
        ->and((string) $fixture['invoice']->currency_code)->toBe('USD')
        ->and($fixture['invoice']->posting_date->toDateString())->toBe('2026-09-11')
        ->and((string) $fixture['order']->currency_code)->toBe('USD');

    $result = app(PurchaseInvoiceIncidentRepairService::class)->execute();

    expect($result['executed'])->toBeTrue();
});

test('purchase invoice incident repair rejects a wrong invoice id', function (): void {
    purchaseInvoiceIncidentFixture(['invoice_id' => 4]);

    expect(fn () => app(PurchaseInvoiceIncidentRepairService::class)->execute())
        ->toThrow(BusinessException::class, 'purchase invoice id');
});

test('purchase invoice incident repair rejects a wrong invoice vendor', function (): void {
    purchaseInvoiceIncidentFixture(['invoice_vendor_id' => 5]);

    expect(fn () => app(PurchaseInvoiceIncidentRepairService::class)->execute())
        ->toThrow(BusinessException::class, 'purchase invoice vendor');
});

test('purchase invoice incident repair rejects a wrong currency', function (): void {
    purchaseInvoiceIncidentFixture(['currency_code' => 'EUR']);

    expect(fn () => app(PurchaseInvoiceIncidentRepairService::class)->execute())
        ->toThrow(BusinessException::class, 'purchase invoice currency');
});

test('purchase invoice incident repair rejects a wrong posting date', function (): void {
    purchaseInvoiceIncidentFixture(['posting_date' => '2026-09-12']);

    expect(fn () => app(PurchaseInvoiceIncidentRepairService::class)->execute())
        ->toThrow(BusinessException::class, 'posting date');
});

test('purchase invoice incident repair rejects a wrong purchase order id', function (): void {
    purchaseInvoiceIncidentFixture(['order_id' => 4]);

    expect(fn () => app(PurchaseInvoiceIncidentRepairService::class)->execute())
        ->toThrow(BusinessException::class, 'purchase order id');
});

test('purchase invoice incident repair rejects a purchase order vendor mismatch', function (): void {
    purchaseInvoiceIncidentFixture(['order_vendor_id' => 6]);

    expect(fn () => app(PurchaseInvoiceIncidentRepairService::class)->execute())
        ->toThrow(BusinessException::class, 'purchase order vendor');
});

test('purchase invoice incident repair rejects a symmetric grni account substitution', function (): void {
    purchaseInvoiceIncidentFixture(['grni_account_number' => '99998']);

    expect(fn () => app(PurchaseInvoiceIncidentRepairService::class)->execute())
        ->toThrow(BusinessException::class, 'GRNI account');
});

test('purchase invoice incident repair rejects a wrong configured payables account', function (): void {
    purchaseInvoiceIncidentFixture(['payables_account_number' => '99997']);

    expect(fn () => app(PurchaseInvoiceIncidentRepairService::class)->execute())
        ->toThrow(BusinessException::class, 'payables account');
});

test('purchase invoice incident repair aborts on an inconsistent receipt expected-cost state', function (): void {
    purchaseInvoiceIncidentFixture();

    ItemLedgerEntry::query()->where('entry_number', 50)->update(['cost_amount_expected' => '60000.00']);

    expect(fn () => app(PurchaseInvoiceIncidentRepairService::class)->execute())
        ->toThrow(BusinessException::class, 'expected-cost state');
});

test('legacy neutralizer posting transactions record the incident currency', function (): void {
    purchaseInvoiceIncidentFixture();

    app(PurchaseInvoiceIncidentRepairService::class)->execute();

    $markers = PostingTransaction::query()
        ->where('idempotency_key', 'like', 'LEGACY_GL_NEUTRALIZER_TX:%')
        ->get();

    expect($markers)->toHaveCount(3)
        ->and($markers->every(fn (PostingTransaction $marker): bool => (string) $marker->currency_code === 'USD'))->toBeTrue();
});

test('purchase invoice incident fixture reuses and normalizes an existing FY2026 accounting period', function (): void {
    $preexisting = AccountingPeriod::query()->updateOrCreate(
        [
            'start_date' => '2026-01-01',
            'end_date' => '2026-12-31',
        ],
        [
            'name' => 'Preexisting FY2026',
            'is_closed' => true,
            'closed_at' => now(),
            'closed_by' => null,
        ],
    );

    purchaseInvoiceIncidentFixture();

    $periods = AccountingPeriod::query()
        ->whereDate('start_date', '2026-01-01')
        ->whereDate('end_date', '2026-12-31')
        ->get();

    expect($periods)->toHaveCount(1)
        ->and($periods->first()->getKey())->toBe($preexisting->getKey())
        ->and($periods->first()->name)->toBe('FY2026')
        ->and($periods->first()->is_closed)->toBeFalse()
        ->and($periods->first()->closed_at)->toBeNull()
        ->and($periods->first()->closed_by)->toBeNull();

    $result = app(PurchaseInvoiceIncidentRepairService::class)->analyze();

    expect($result['already_repaired'])->toBeFalse()
        ->and($result['execution_allowed'])->toBeTrue();
});

test('purchase invoice incident fixture reuses an existing USD currency', function (): void {
    Currency::query()->create([
        'code' => 'USD',
        'description' => 'US Dollar',
        'symbol' => '$',
        'is_active' => true,
        'is_lcy' => false,
    ]);

    $fixture = purchaseInvoiceIncidentFixture();

    expect(Currency::query()->where('code', 'USD')->count())->toBe(1)
        ->and((string) $fixture['incidentCurrency']->code)->toBe('USD');

    $result = app(PurchaseInvoiceIncidentRepairService::class)->analyze();

    expect($result['already_repaired'])->toBeFalse()
        ->and($result['execution_allowed'])->toBeTrue();
});

test('purchase invoice incident fixture normalizes an existing general ledger setup', function (): void {
    GeneralLedgerSetup::query()->create([
        'company_name' => 'Default Company',
        'allow_posting_from' => '2025-01-01',
        'allow_posting_to' => '2025-12-31',
    ]);

    purchaseInvoiceIncidentFixture();

    $setups = GeneralLedgerSetup::query()->where('company_name', 'Default Company')->get();

    expect($setups)->toHaveCount(1)
        ->and($setups->first()->allow_posting_from?->toDateString())->toBe('2026-01-01')
        ->and($setups->first()->allow_posting_to?->toDateString())->toBe('2026-12-31');

    $result = app(PurchaseInvoiceIncidentRepairService::class)->analyze();

    expect($result['already_repaired'])->toBeFalse()
        ->and($result['execution_allowed'])->toBeTrue();
});

function purchaseInvoiceIncidentFixture(array $overrides = []): array
{
    $invoiceId = (int) ($overrides['invoice_id'] ?? 3);
    $orderId = (int) ($overrides['order_id'] ?? 2);
    $invoiceVendorId = (int) ($overrides['invoice_vendor_id'] ?? 2);
    $orderVendorId = (int) ($overrides['order_vendor_id'] ?? $invoiceVendorId);
    $currencyCode = (string) ($overrides['currency_code'] ?? 'USD');
    $postingDate = (string) ($overrides['posting_date'] ?? '2026-09-11');
    $payablesAccountNumber = (string) ($overrides['payables_account_number'] ?? '31202');
    $grniAccountNumber = (string) ($overrides['grni_account_number'] ?? '20200');

    $business = Business::unguarded(fn (): Business => Business::query()->create([
        'id' => 1,
        'code' => 'PIINC'.random_int(100000, 999999),
        'name' => 'PI Incident Business',
        'is_active' => true,
    ]));
    session(['active_business_id' => $business->id]);

    $incidentCurrency = Currency::query()->updateOrCreate(
        ['code' => $currencyCode],
        [
            'description' => $currencyCode.' currency',
            'symbol' => $currencyCode === 'USD' ? '$' : null,
            'is_active' => true,
            'is_lcy' => false,
        ],
    );

    GeneralLedgerSetup::query()->updateOrCreate(
        ['company_name' => 'Default Company'],
        [
            'allow_posting_from' => '2026-01-01',
            'allow_posting_to' => '2026-12-31',
        ],
    );

    AccountingPeriod::query()->updateOrCreate(
        [
            'start_date' => '2026-01-01',
            'end_date' => '2026-12-31',
        ],
        [
            'name' => 'FY2026',
            'is_closed' => false,
            'closed_at' => null,
            'closed_by' => null,
        ],
    );

    $user = User::factory()->create();
    $location = Location::factory()->create(['code' => 'MAIN']);
    $payablesAccount = purchaseInvoiceIncidentAccount($payablesAccountNumber, 'Trade Payables - Foreign', 'payable');
    $grniAccount = purchaseInvoiceIncidentAccount($grniAccountNumber, 'Purchase Clearing / GRNI', 'liability');
    $inventoryAccount = purchaseInvoiceIncidentAccount('13110', 'Raw Material Inventory', 'inventory');
    $businessGroup = GeneralBusinessPostingGroup::query()->create(['code' => 'FOREIGN', 'description' => 'Foreign', 'blocked' => false]);
    $productGroup = GeneralProductPostingGroup::query()->create(['code' => 'RAW', 'description' => 'Raw Materials', 'blocked' => false]);
    $inventoryGroup = InventoryPostingGroup::query()->create(['code' => 'RAW', 'description' => 'Raw Materials', 'blocked' => false]);
    $vendorPostingGroup = VendorPostingGroup::query()->create([
        'code' => 'FOREIGN',
        'description' => 'Foreign Vendors',
        'payables_account_id' => $payablesAccount->id,
        'blocked' => false,
    ]);

    InventoryPostingSetup::query()->create([
        'inventory_posting_group_id' => $inventoryGroup->id,
        'inventory_account_id' => $inventoryAccount->id,
    ]);

    GeneralPostingSetup::query()->create([
        'general_business_posting_group_id' => $businessGroup->id,
        'general_product_posting_group_id' => $productGroup->id,
        'purchase_account_id' => $grniAccount->id,
        'blocked' => false,
    ]);

    $uom = UnitOfMeasure::query()->create(['uom_code' => 'KG', 'description' => 'Kilogram', 'is_base_uom' => true]);
    $item2100 = purchaseInvoiceIncidentItem('2100', 'Ginseng', $uom->id, $productGroup->id, $inventoryGroup->id, $location->id);
    $item2200 = purchaseInvoiceIncidentItem('2200', 'Bottle', $uom->id, $productGroup->id, $inventoryGroup->id, $location->id);
    $invoiceVendor = Vendor::unguarded(fn (): Vendor => Vendor::factory()->create([
        'id' => $invoiceVendorId,
        'vendor_code' => 'VEND-FOREIGN',
        'vendor_name' => 'Foreign Vendor',
        'general_business_posting_group_id' => $businessGroup->id,
        'vendor_posting_group_id' => $vendorPostingGroup->id,
        'currency' => $currencyCode,
    ]));

    $orderVendor = $invoiceVendor;
    if ($orderVendorId !== $invoiceVendorId) {
        $orderVendor = Vendor::unguarded(fn (): Vendor => Vendor::factory()->create([
            'id' => $orderVendorId,
            'vendor_code' => 'VEND-FOREIGN-ALT',
            'vendor_name' => 'Foreign Vendor Alt',
            'general_business_posting_group_id' => $businessGroup->id,
            'vendor_posting_group_id' => $vendorPostingGroup->id,
            'currency' => $currencyCode,
        ]));
    }

    $vendor = $invoiceVendor;

    $numberSeries = NumberSeries::query()->create([
        'code' => 'P-INV',
        'description' => 'Purchase invoices',
        'prefix' => 'PI-',
        'starting_number' => 1,
        'current_number' => 7,
        'year' => 2026,
        'is_active' => true,
        'allow_manual' => false,
        'module' => 'purchase',
    ]);
    $numberSeriesLine = NumberSeriesLine::query()->create([
        'number_series_id' => $numberSeries->id,
        'starting_date' => '2026-01-01',
        'starting_no' => 0,
        'increment_by' => 1,
        'last_no_used' => 7,
        'no_of_digits' => 5,
        'prefix' => 'PI-',
        'blocked' => false,
    ]);

    $order = PurchaseOrder::unguarded(fn (): PurchaseOrder => PurchaseOrder::query()->create([
        'id' => $orderId,
        'business_id' => $business->id,
        'order_number' => 'PO-2026-00002',
        'status' => PurchaseOrderStatus::APPROVED,
        'vendor_id' => $orderVendor->id,
        'vendor_name' => $orderVendor->vendor_name,
        'order_date' => '2026-09-10',
        'posting_date' => '2026-09-10',
        'location_id' => $location->id,
        'payment_terms' => 30,
        'currency_code' => $currencyCode,
        'general_business_posting_group_id' => $businessGroup->id,
        'vendor_posting_group_id' => $vendorPostingGroup->id,
        'total_amount' => '262866.20',
        'total_vat' => '0',
        'grand_total' => '262866.20',
        'created_by' => $user->id,
    ]));
    $line2100 = $order->lines()->create([
        'line_number' => 10000,
        'item_id' => $item2100->id,
        'item_code' => $item2100->item_code,
        'description' => $item2100->description,
        'quantity' => 5000,
        'received_quantity' => 5000,
        'invoiced_quantity' => 5000,
        'unit_of_measure' => 'KG',
        'unit_cost' => '10.00',
        'general_product_posting_group_id' => $productGroup->id,
    ]);
    $line2200 = $order->lines()->create([
        'line_number' => 20000,
        'item_id' => $item2200->id,
        'item_code' => $item2200->item_code,
        'description' => $item2200->description,
        'quantity' => 300,
        'received_quantity' => 300,
        'invoiced_quantity' => 300,
        'unit_of_measure' => 'KG',
        'unit_cost' => '709.554',
        'general_product_posting_group_id' => $productGroup->id,
    ]);
    $order->forceFill(['status' => PurchaseOrderStatus::CLOSED])->saveQuietly();

    $invoice = PurchaseInvoice::unguarded(fn (): PurchaseInvoice => PurchaseInvoice::query()->create([
        'id' => $invoiceId,
        'business_id' => $business->id,
        'document_number' => 'PI-2026-00001',
        'order_id' => $order->id,
        'order_number' => $order->order_number,
        'vendor_id' => $invoiceVendor->id,
        'vendor_name' => $invoiceVendor->vendor_name,
        'general_business_posting_group_id' => $businessGroup->id,
        'vendor_posting_group_id' => $vendorPostingGroup->id,
        'location_id' => $location->id,
        'posting_date' => $postingDate,
        'document_date' => $postingDate,
        'due_date' => '2026-10-10',
        'status' => ApprovalStatus::POSTED,
        'total_amount' => '262866.20',
        'total_vat' => '0',
        'grand_total' => '262866.20',
        'amount_paid' => '0',
        'remaining_amount' => '262866.20',
        'paid_in_full' => false,
        'currency_code' => $currencyCode,
        'currency_factor' => '1',
        'posted_by' => $user->id,
        'posted_at' => now(),
        'cancelled' => false,
    ]));

    $invoice->lines()->create([
        'line_number' => 10000,
        'po_line_id' => $line2100->id,
        'po_line_number' => $line2100->line_number,
        'item_id' => $item2100->id,
        'item_code' => $item2100->item_code,
        'item_description' => $item2100->description,
        'general_product_posting_group_id' => $productGroup->id,
        'inventory_posting_group_id' => $inventoryGroup->id,
        'quantity' => 5000,
        'unit_of_measure_code' => 'KG',
        'qty_per_unit_of_measure' => 1,
        'quantity_base' => 5000,
        'unit_cost' => '10.00',
        'unit_cost_lcy' => '10.00',
        'line_total' => '50000.00',
        'vat_percentage' => 0,
        'vat_amount' => 0,
        'vat_amount_lcy' => 0,
        'amount_including_vat' => '50000.00',
        'amount_including_vat_lcy' => '50000.00',
        'item_ledger_entry_id' => null,
        'posting_date' => '2026-09-10',
    ]);
    $invoice->lines()->create([
        'line_number' => 20000,
        'po_line_id' => $line2200->id,
        'po_line_number' => $line2200->line_number,
        'item_id' => $item2200->id,
        'item_code' => $item2200->item_code,
        'item_description' => $item2200->description,
        'general_product_posting_group_id' => $productGroup->id,
        'inventory_posting_group_id' => $inventoryGroup->id,
        'quantity' => 300,
        'unit_of_measure_code' => 'KG',
        'qty_per_unit_of_measure' => 1,
        'quantity_base' => 300,
        'unit_cost' => '709.554',
        'unit_cost_lcy' => '709.554',
        'line_total' => '212866.20',
        'vat_percentage' => 0,
        'vat_amount' => 0,
        'vat_amount_lcy' => 0,
        'amount_including_vat' => '212866.20',
        'amount_including_vat_lcy' => '212866.20',
        'item_ledger_entry_id' => null,
        'posting_date' => '2026-09-10',
    ]);

    $postedInvoice = PostedPurchaseInvoice::query()->create($invoice->only([
        'business_id',
        'document_number',
        'order_id',
        'order_number',
        'vendor_id',
        'vendor_name',
        'general_business_posting_group_id',
        'vendor_posting_group_id',
        'location_id',
        'posting_date',
        'document_date',
        'due_date',
        'total_amount',
        'total_vat',
        'grand_total',
        'currency_code',
        'currency_factor',
        'amount_paid',
        'remaining_amount',
        'paid_in_full',
        'posted_by',
        'posted_at',
        'cancelled',
    ]));
    foreach ($invoice->lines as $line) {
        $postedInvoice->lines()->create([
            ...$line->only([
                'po_line_id',
                'po_line_number',
                'item_id',
                'item_code',
                'item_description',
                'general_product_posting_group_id',
                'inventory_posting_group_id',
                'quantity',
                'unit_of_measure_code',
                'qty_per_unit_of_measure',
                'quantity_base',
                'unit_cost',
                'unit_cost_lcy',
                'line_total',
                'vat_percentage',
                'vat_amount',
                'vat_amount_lcy',
                'amount_including_vat',
                'amount_including_vat_lcy',
                'line_number',
            ]),
            'posted_purchase_invoice_id' => $postedInvoice->id,
        ]);
    }

    $ile50 = ItemLedgerEntry::query()->create([
        'entry_number' => 50,
        'business_id' => $business->id,
        'entry_type' => ItemLedgerEntryType::PURCHASE,
        'document_type' => 'PURCHASE_RECEIPT',
        'document_line_number' => 10000,
        'item_id' => $item2100->id,
        'location_id' => $location->id,
        'quantity' => 5000,
        'remaining_quantity' => 5000,
        'cost_amount_actual' => 0,
        'cost_amount_expected' => '50000.00',
        'purchase_amount_actual' => 0,
        'general_business_posting_group_id' => $businessGroup->id,
        'general_product_posting_group_id' => $productGroup->id,
        'inventory_posting_group_id' => $inventoryGroup->id,
        'posting_date' => '2026-09-10',
        'entry_date' => now(),
        'open' => true,
        'source_type' => PurchaseOrder::class,
        'source_id' => $order->id,
        'document_number' => $order->order_number,
    ]);
    $ile51 = ItemLedgerEntry::query()->create([
        'entry_number' => 51,
        'business_id' => $business->id,
        'entry_type' => ItemLedgerEntryType::PURCHASE,
        'document_type' => 'PURCHASE_RECEIPT',
        'document_line_number' => 20000,
        'item_id' => $item2200->id,
        'location_id' => $location->id,
        'quantity' => 300,
        'remaining_quantity' => 300,
        'cost_amount_actual' => 0,
        'cost_amount_expected' => '212866.20',
        'purchase_amount_actual' => 0,
        'general_business_posting_group_id' => $businessGroup->id,
        'general_product_posting_group_id' => $productGroup->id,
        'inventory_posting_group_id' => $inventoryGroup->id,
        'posting_date' => '2026-09-10',
        'entry_date' => now(),
        'open' => true,
        'source_type' => PurchaseOrder::class,
        'source_id' => $order->id,
        'document_number' => $order->order_number,
    ]);

    purchaseInvoiceIncidentActualValueEntry(97, $ile50, $invoice, $item2100, '50000.00', 5000);
    purchaseInvoiceIncidentActualValueEntry(98, $ile51, $invoice, $item2200, '212866.20', 300);

    purchaseInvoiceIncidentMalformedGlEntry(1, 101, $grniAccount, '50000.00', '0.00', $ile50->id, $incidentCurrency->id);
    purchaseInvoiceIncidentMalformedGlEntry(2, 103, $grniAccount, '212866.20', '0.00', $ile51->id, $incidentCurrency->id);
    $legacyPayablesGl = purchaseInvoiceIncidentMalformedGlEntry(3, 104, $payablesAccount, '0.00', '262866.20', null, $incidentCurrency->id);

    VendorLedgerEntry::query()->create([
        'entry_number' => 1,
        'vendor_id' => $vendor->id,
        'business_id' => $business->id,
        'document_type' => 'PURCHASE_INVOICE',
        'document_number' => 'PI-2026-00001',
        'description' => 'Invoice PI-2026-00001',
        'posting_date' => '2026-09-10',
        'document_date' => '2026-09-10',
        'due_date' => '2026-10-10',
        'debit_amount' => '262866.20',
        'credit_amount' => '0',
        'amount' => '262866.20',
        'running_balance' => '262866.20',
        'remaining_amount' => '262866.20',
        'open' => true,
        'fully_applied' => false,
        'currency_code' => $currencyCode,
        'original_debit_amount' => '262866.20',
        'original_credit_amount' => '0',
        'currency_factor' => '1',
        'general_business_posting_group_id' => $businessGroup->id,
        'vendor_posting_group_id' => $vendorPostingGroup->id,
        'gl_entry_id' => $legacyPayablesGl->id,
        'source_id' => $postedInvoice->id,
        'source_type' => PostedPurchaseInvoice::class,
        'created_by' => $user->id,
    ]);

    return compact(
        'business',
        'user',
        'vendor',
        'invoiceVendor',
        'orderVendor',
        'incidentCurrency',
        'order',
        'invoice',
        'postedInvoice',
        'payablesAccount',
        'grniAccount',
        'inventoryAccount',
        'numberSeries',
        'numberSeriesLine',
        'ile50',
        'ile51',
    );
}

function purchaseInvoiceIncidentAccount(string $number, string $name, string $category): ChartOfAccount
{
    return ChartOfAccount::query()->create([
        'account_number' => $number,
        'name' => $name,
        'account_category' => $category,
        'income_balance' => IncomeBalanceType::BALANCE_SHEET,
        'structural_type' => 'posting',
        'direct_posting' => true,
        'blocked' => false,
    ]);
}

function purchaseInvoiceIncidentItem(string $code, string $description, int $uomId, int $productGroupId, int $inventoryGroupId, int $locationId): Item
{
    return Item::query()->create([
        'item_code' => $code,
        'description' => $description,
        'item_type' => ItemType::RAW_MATERIAL,
        'base_uom_id' => $uomId,
        'unit_cost' => 0,
        'inventory' => 0,
        'location_id' => $locationId,
        'general_product_posting_group_id' => $productGroupId,
        'inventory_posting_group_id' => $inventoryGroupId,
    ]);
}

function purchaseInvoiceIncidentActualValueEntry(int $entryNo, ItemLedgerEntry $ile, PurchaseInvoice $invoice, Item $item, string $amount, float $quantity): ValueEntry
{
    return ValueEntry::query()->create([
        'entry_no' => $entryNo,
        'item_ledger_entry_no' => $ile->entry_number,
        'item_ledger_entry_type' => 1,
        'item_no' => $item->item_code,
        'location_code' => 'MAIN',
        'posting_date' => $invoice->posting_date,
        'valuation_date' => $invoice->posting_date,
        'document_type' => 'PURCHASE_INVOICE',
        'document_no' => $invoice->document_number,
        'document_line_no' => $ile->document_line_number,
        'quantity' => $quantity,
        'invoiced_quantity' => $quantity,
        'valued_quantity' => $quantity,
        'remaining_quantity' => 0,
        'value_entry_state' => 'actual',
        'expected_cost' => false,
        'cost_amount_actual' => $amount,
        'cost_amount_expected' => 0,
        'unit_cost' => (float) $amount / $quantity,
        'source_type' => PurchaseInvoice::class,
        'source_module' => 'purchases',
        'source_id' => $invoice->id,
        'source_number' => $invoice->document_number,
        'purchase_order_no' => $invoice->order_number,
        'purchase_order_line_no' => $ile->document_line_number,
        'vendor_no' => $invoice->vendor?->vendor_code,
        'business_id' => $invoice->business_id,
        'gl_posted' => true,
        'gl_posting_date' => $invoice->posting_date,
        'completely_invoiced' => true,
    ]);
}

function purchaseInvoiceIncidentMalformedGlEntry(
    int $entryNumber,
    int $transactionNumber,
    ChartOfAccount $account,
    string $debit,
    string $credit,
    ?int $itemLedgerEntryId,
    ?int $currencyId = null,
): GlEntry {
    return GlEntry::query()->create([
        'entry_number' => $entryNumber,
        'transaction_number' => $transactionNumber,
        'posting_transaction_id' => null,
        'business_id' => session('active_business_id'),
        'chart_of_account_id' => $account->id,
        'currency_id' => $currencyId,
        'debit_amount' => $debit,
        'debit_amount_lcy' => $debit,
        'credit_amount' => $credit,
        'credit_amount_lcy' => $credit,
        'amount' => (float) $debit - (float) $credit,
        'amount_lcy' => (float) $debit - (float) $credit,
        'source_type' => $credit === '0.00' ? SourceType::ITEM : SourceType::VENDOR,
        'source_module' => 'purchases',
        'source_number' => 'PI-2026-00001',
        'document_type' => 'PURCHASE_INVOICE',
        'document_number' => 'PI-2026-00001',
        'document_date' => '2026-09-10',
        'posting_date' => '2026-09-10',
        'description' => 'Malformed historical purchase invoice row',
        'item_ledger_entry_id' => $itemLedgerEntryId,
    ]);
}

function purchaseInvoiceIncidentCounts(): array
{
    return [
        'purchase_invoices' => PurchaseInvoice::query()->where('document_number', 'PI-2026-00001')->count(),
        'purchase_orders' => PurchaseOrder::query()->where('order_number', 'PO-2026-00002')->count(),
        'item_ledger_entries' => ItemLedgerEntry::query()->where('document_number', 'PO-2026-00002')->count(),
        'actual_value_entries' => ValueEntry::query()->where('document_no', 'PI-2026-00001')->where('value_entry_state', 'actual')->count(),
        'gl_entries' => GlEntry::query()->where('document_number', 'PI-2026-00001')->count(),
        'posting_transactions' => PostingTransaction::query()->where('document_number', 'PI-2026-00001')->count(),
        'vendor_ledger_entries' => VendorLedgerEntry::query()->where('document_number', 'PI-2026-00001')->count(),
        'audit_trails' => AuditTrail::query()->count(),
    ];
}

function purchaseInvoiceIncidentExpectTransactionGroupsBalanced(): void
{
    $imbalances = GlEntry::query()
        ->where('document_type', 'PURCHASE_INVOICE')
        ->where('document_number', 'PI-2026-00001')
        ->selectRaw('transaction_number, COALESCE(SUM(debit_amount), 0) as debit, COALESCE(SUM(credit_amount), 0) as credit')
        ->groupBy('transaction_number')
        ->get()
        ->filter(fn ($entry): bool => abs(round((float) $entry->debit - (float) $entry->credit, 2)) > 0.01);

    expect($imbalances->values()->all())->toBe([]);
}

function purchaseInvoiceIncidentExpectTransactionGroupsUnbalanced(): void
{
    $imbalances = GlEntry::query()
        ->where('document_type', 'PURCHASE_INVOICE')
        ->where('document_number', 'PI-2026-00001')
        ->selectRaw('transaction_number, COALESCE(SUM(debit_amount), 0) as debit, COALESCE(SUM(credit_amount), 0) as credit')
        ->groupBy('transaction_number')
        ->havingRaw('ABS(COALESCE(SUM(debit_amount), 0) - COALESCE(SUM(credit_amount), 0)) > 0.01')
        ->count();

    expect($imbalances)->toBe(3);
}

function purchaseInvoiceIncidentExpectVendorExposureReconciles(int $payablesAccountId): void
{
    $vendorExposure = VendorLedgerEntry::query()
        ->where('document_number', 'PI-2026-00001')
        ->sum(DB::raw('credit_amount - debit_amount'));
    $glExposure = GlEntry::query()
        ->where('document_number', 'PI-2026-00001')
        ->where('chart_of_account_id', $payablesAccountId)
        ->sum(DB::raw('credit_amount - debit_amount'));

    expect(round((float) $vendorExposure, 2))->toBe(262866.2)
        ->and(round((float) $glExposure, 2))->toBe(262866.2);
}

function purchaseInvoiceIncidentExpectReceiptCostsSynced(): void
{
    foreach ([50 => 50000.0, 51 => 212866.2] as $entryNumber => $amount) {
        $ile = ItemLedgerEntry::query()->where('entry_number', $entryNumber)->firstOrFail();

        expect(round((float) $ile->cost_amount_actual, 2))->toBe($amount)
            ->and(round((float) $ile->purchase_amount_actual, 2))->toBe($amount)
            ->and(round((float) $ile->cost_amount_expected, 2))->toBe(0.0);
    }
}

function purchaseInvoiceIncidentExpectTransactionGroupBalancedFor(int $transactionNumber): void
{
    $totals = GlEntry::query()
        ->where('transaction_number', $transactionNumber)
        ->selectRaw('COALESCE(SUM(debit_amount), 0) as debit, COALESCE(SUM(credit_amount), 0) as credit')
        ->first();

    expect(round((float) $totals->debit, 2))->toBe(round((float) $totals->credit, 2));
}

function purchaseInvoiceIncidentRawGlEntry(
    int $entryNumber,
    int $transactionNumber,
    ChartOfAccount $account,
    string $debit,
    string $credit,
): GlEntry {
    return GlEntry::query()->create([
        'entry_number' => $entryNumber,
        'transaction_number' => $transactionNumber,
        'posting_transaction_id' => null,
        'business_id' => session('active_business_id'),
        'chart_of_account_id' => $account->id,
        'debit_amount' => $debit,
        'debit_amount_lcy' => $debit,
        'credit_amount' => $credit,
        'credit_amount_lcy' => $credit,
        'amount' => (float) $debit - (float) $credit,
        'amount_lcy' => (float) $debit - (float) $credit,
        'source_type' => SourceType::ITEM,
        'source_module' => 'purchases',
        'source_number' => 'PI-2026-00001',
        'document_type' => 'PURCHASE_INVOICE',
        'document_number' => 'PI-2026-00001',
        'document_date' => '2026-09-10',
        'posting_date' => '2026-09-10',
        'description' => 'Focused legacy neutralizer test row',
    ]);
}

function purchaseInvoiceIncidentExpectedVendorBefore(): array
{
    return app(PurchaseInvoiceIncidentRepairService::class)->analyze()['vendor_ledger']['current'];
}

function purchaseInvoiceIncidentPayablesControlEntry(array $fixture): GlEntry
{
    $transaction = app(GeneralLedgerService::class)->postTransaction([
        [
            'account_id' => $fixture['grniAccount']->id,
            'debit_amount' => '262866.20',
            'credit_amount' => '0',
            'description' => 'Focused test payables control link',
            'source_type' => SourceType::ITEM->value,
            'source_number' => 'PI-2026-00001',
        ],
        [
            'account_id' => $fixture['payablesAccount']->id,
            'debit_amount' => '0',
            'credit_amount' => '262866.20',
            'description' => 'Focused test payables control link',
            'source_type' => SourceType::VENDOR->value,
            'source_number' => 'PI-2026-00001',
        ],
    ], [
        'business_id' => 1,
        'posting_date' => '2026-09-10',
        'document_date' => '2026-09-10',
        'source_module' => 'purchases',
        'source_type' => SourceType::VENDOR->value,
        'source_id' => $fixture['postedInvoice']->id,
        'source_number' => 'PI-2026-00001',
        'document_type' => 'PURCHASE_INVOICE',
        'document_number' => 'PI-2026-00001',
        'transaction_key' => 'focused-test:payables-control',
        'idempotency_key' => 'focused-test:payables-control',
        'description' => 'Focused test payables control link',
    ]);

    return $transaction->glEntries->firstWhere('chart_of_account_id', $fixture['payablesAccount']->id);
}
