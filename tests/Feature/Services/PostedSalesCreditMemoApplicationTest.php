<?php

declare(strict_types=1);

use App\Exceptions\BusinessException;
use App\Models\CustomerLedgerApplication;
use App\Models\CustomerLedgerEntry;
use App\Models\GlEntry;
use App\Models\ItemLedgerEntry;
use App\Models\PostedSalesCreditMemo;
use App\Models\ValueEntry;
use App\Services\Customer\CustomerSubledgerSummaryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;

uses(RefreshDatabase::class);

it('applies a posted sales credit memo as a balance-neutral settlement with non-monetary trace', function (): void {
    $fixture = postedSalesCreditMemoApplicationFixture($this, 300.00, 1000.00);
    $appliedAmount = 300.00;
    $customerId = $fixture['customer']->id;
    $customerBalanceBefore = CustomerLedgerEntry::getBalance($customerId);
    $customerOpenBalanceBefore = (float) $fixture['customer']->fresh()->open_balance;
    $customerAgingBefore = CustomerLedgerEntry::getAging($customerId);
    $customerSubledgerBefore = app(CustomerSubledgerSummaryService::class)->generate(['customer_id' => $customerId]);
    $ledgerEntryCountBefore = CustomerLedgerEntry::query()->count();
    $glEntryCountBefore = GlEntry::query()->count();
    $itemLedgerEntryCountBefore = ItemLedgerEntry::query()->count();
    $valueEntryCountBefore = ValueEntry::query()->count();

    $fixture['postedCreditMemo']->applyToInvoices([
        [
            'invoice_id' => $fixture['postedInvoice']->id,
            'amount' => $appliedAmount,
        ],
    ]);

    $fixture['postedCreditMemo']->refresh();
    $fixture['postedInvoice']->refresh();
    $fixture['documentEntry']->refresh();

    $invoiceLedgerEntry = CustomerLedgerEntry::query()
        ->where('customer_id', $fixture['customer']->id)
        ->where('document_type', 'SALES_INVOICE')
        ->firstOrFail();

    $applicationTrace = CustomerLedgerApplication::query()
        ->where('customer_id', $customerId)
        ->where('source_posted_sales_credit_memo_id', $fixture['postedCreditMemo']->id)
        ->where('target_posted_sales_invoice_id', $fixture['postedInvoice']->id)
        ->firstOrFail();

    expect($fixture['postedCreditMemo'])->toBeInstanceOf(PostedSalesCreditMemo::class)
        ->and((float) $fixture['postedCreditMemo']->amount_applied)->toBe($appliedAmount)
        ->and((float) $fixture['postedCreditMemo']->remaining_amount)->toBe(0.00)
        ->and($fixture['postedCreditMemo']->fully_applied)->toBeTrue()
        ->and((float) $fixture['documentEntry']->remaining_amount)->toBe(0.00)
        ->and($fixture['documentEntry']->fully_applied)->toBeTrue()
        ->and((float) $fixture['postedInvoice']->amount_paid)->toBe($appliedAmount)
        ->and((float) $fixture['postedInvoice']->remaining_amount)->toBe(700.00)
        ->and((float) $invoiceLedgerEntry->remaining_amount)->toBe(700.00)
        ->and($invoiceLedgerEntry->open)->toBeTrue()
        ->and((float) $applicationTrace->amount)->toBe($appliedAmount)
        ->and((float) $applicationTrace->source_remaining_before)->toBe(300.00)
        ->and((float) $applicationTrace->source_remaining_after)->toBe(0.00)
        ->and((float) $applicationTrace->target_remaining_before)->toBe(1000.00)
        ->and((float) $applicationTrace->target_remaining_after)->toBe(700.00)
        ->and(CustomerLedgerEntry::query()->count())->toBe($ledgerEntryCountBefore)
        ->and(CustomerLedgerEntry::query()->where('document_type', 'CREDIT_MEMO_APPLICATION')->count())->toBe(0)
        ->and(CustomerLedgerEntry::getBalance($customerId))->toBe($customerBalanceBefore)
        ->and((float) $fixture['customer']->fresh()->open_balance)->toBe($customerOpenBalanceBefore)
        ->and(CustomerLedgerEntry::getAging($customerId))->toBe($customerAgingBefore)
        ->and(app(CustomerSubledgerSummaryService::class)->generate(['customer_id' => $customerId])['summary']['net'])->toBe($customerSubledgerBefore['summary']['net'])
        ->and(app(CustomerSubledgerSummaryService::class)->generate(['customer_id' => $customerId])['summary']['open_remaining'])->toBe($customerSubledgerBefore['summary']['open_remaining'])
        ->and(GlEntry::query()->count())->toBe($glEntryCountBefore)
        ->and(ItemLedgerEntry::query()->count())->toBe($itemLedgerEntryCountBefore)
        ->and(ValueEntry::query()->count())->toBe($valueEntryCountBefore);

    expect(Artisan::call('biwms:finance-reconcile', ['--json' => true]))->toBe(0);
    $financeReport = json_decode(Artisan::output(), true);

    expect($financeReport['legacy_monetary_credit_memo_application_entries'])->toBeEmpty();
});

it('supports partial sales credit memo application without changing customer total balance', function (): void {
    $fixture = postedSalesCreditMemoApplicationFixture($this, 300.00, 1000.00);
    $customerBalanceBefore = CustomerLedgerEntry::getBalance($fixture['customer']->id);

    $fixture['postedCreditMemo']->applyToInvoices([
        [
            'invoice_id' => $fixture['postedInvoice']->id,
            'amount' => 100.00,
        ],
    ]);

    $fixture['postedCreditMemo']->refresh();
    $fixture['postedInvoice']->refresh();
    $fixture['documentEntry']->refresh();

    expect((float) $fixture['postedCreditMemo']->remaining_amount)->toBe(200.00)
        ->and((float) $fixture['postedInvoice']->remaining_amount)->toBe(900.00)
        ->and((float) $fixture['documentEntry']->remaining_amount)->toBe(200.00)
        ->and(CustomerLedgerEntry::getBalance($fixture['customer']->id))->toBe($customerBalanceBefore)
        ->and(CustomerLedgerApplication::query()->count())->toBe(1);
});

it('rejects applying a sales credit memo to a fully paid invoice', function (): void {
    $fixture = postedSalesCreditMemoApplicationFixture($this, 300.00, 1000.00);
    $invoiceLedgerEntry = postedInvoiceLedgerEntry($fixture);
    $fixture['postedInvoice']->update([
        'amount_paid' => 1000.00,
        'remaining_amount' => 0,
        'paid_in_full' => true,
    ]);
    $invoiceLedgerEntry->update([
        'remaining_amount' => 0,
        'open' => false,
        'fully_applied' => true,
    ]);

    expect(fn () => $fixture['postedCreditMemo']->applyToInvoices([
        ['invoice_id' => $fixture['postedInvoice']->id, 'amount' => 300.00],
    ]))->toThrow(BusinessException::class, 'fully paid invoice');
});

it('rejects sales credit memo over-application', function (): void {
    $fixture = postedSalesCreditMemoApplicationFixture($this, 300.00, 1000.00);

    expect(fn () => $fixture['postedCreditMemo']->applyToInvoices([
        ['invoice_id' => $fixture['postedInvoice']->id, 'amount' => 300.01],
    ]))->toThrow(BusinessException::class, 'remaining credit memo amount');
});

it('rejects cross-customer sales credit memo application', function (): void {
    $sourceFixture = postedSalesCreditMemoApplicationFixture($this, 300.00, 1000.00);
    $otherFixture = postedSalesCreditMemoApplicationFixture($this, 300.00, 1000.00);

    expect(fn () => $sourceFixture['postedCreditMemo']->applyToInvoices([
        ['invoice_id' => $otherFixture['postedInvoice']->id, 'amount' => 100.00],
    ]))->toThrow(BusinessException::class, 'same customer');
});

it('rejects sales credit memo currency mismatch', function (): void {
    $fixture = postedSalesCreditMemoApplicationFixture($this, 300.00, 1000.00);
    $fixture['postedInvoice']->update(['currency_code' => 'USD']);

    expect(fn () => $fixture['postedCreditMemo']->applyToInvoices([
        ['invoice_id' => $fixture['postedInvoice']->id, 'amount' => 100.00],
    ]))->toThrow(BusinessException::class, 'currencies must match');
});

it('rejects cross-business sales credit memo application where ownership is resolvable', function (): void {
    $fixture = postedSalesCreditMemoApplicationFixture($this, 300.00, 1000.00);
    $fixture['postedCreditMemo']->update(['dimensions' => ['business_id' => 1]]);
    $fixture['postedInvoice']->update(['dimensions' => ['business_id' => 2]]);

    expect(fn () => $fixture['postedCreditMemo']->fresh()->applyToInvoices([
        ['invoice_id' => $fixture['postedInvoice']->id, 'amount' => 100.00],
    ]))->toThrow(BusinessException::class, 'same business');
});

it('prevents repeated sales credit memo application from exceeding remaining balances', function (): void {
    $fixture = postedSalesCreditMemoApplicationFixture($this, 300.00, 1000.00);

    $fixture['postedCreditMemo']->applyToInvoices([
        ['invoice_id' => $fixture['postedInvoice']->id, 'amount' => 200.00],
    ]);

    expect(fn () => $fixture['postedCreditMemo']->fresh()->applyToInvoices([
        ['invoice_id' => $fixture['postedInvoice']->id, 'amount' => 200.00],
    ]))->toThrow(BusinessException::class, 'remaining credit memo amount');

    expect((float) $fixture['postedCreditMemo']->fresh()->remaining_amount)->toBe(100.00)
        ->and((float) $fixture['postedInvoice']->fresh()->remaining_amount)->toBe(800.00);
});

it('keeps sales credit memo application trace immutable', function (): void {
    $fixture = postedSalesCreditMemoApplicationFixture($this, 300.00, 1000.00);

    $fixture['postedCreditMemo']->applyToInvoices([
        ['invoice_id' => $fixture['postedInvoice']->id, 'amount' => 100.00],
    ]);

    $trace = CustomerLedgerApplication::query()->firstOrFail();

    expect(fn () => $trace->update(['amount' => 50.00]))
        ->toThrow(BusinessException::class, 'immutable')
        ->and(fn () => $trace->delete())
        ->toThrow(BusinessException::class, 'immutable');
});

function postedSalesCreditMemoApplicationFixture($testCase, float $creditAmount, float $invoiceAmount): array
{
    $fixtureFactory = Closure::bind(
        fn (float $amount): array => $this->createPostedSalesCreditMemoFixture($amount),
        $testCase,
        $testCase::class,
    );

    $fixture = $fixtureFactory($creditAmount);

    $fixture['postedInvoice']->update([
        'subtotal' => $invoiceAmount,
        'total_amount' => $invoiceAmount,
        'grand_total' => $invoiceAmount,
        'amount_paid' => 0,
        'remaining_amount' => $invoiceAmount,
        'paid_in_full' => false,
        'paid_in_full_date' => null,
    ]);

    postedInvoiceLedgerEntry($fixture)->update([
        'debit_amount' => $invoiceAmount,
        'credit_amount' => 0,
        'amount' => $invoiceAmount,
        'running_balance' => $invoiceAmount,
        'remaining_amount' => $invoiceAmount,
        'open' => true,
        'fully_applied' => false,
    ]);

    $fixture['documentEntry']->update([
        'credit_amount' => $creditAmount,
        'amount' => -$creditAmount,
        'running_balance' => $invoiceAmount - $creditAmount,
        'remaining_amount' => $creditAmount,
        'open' => true,
        'fully_applied' => false,
    ]);

    return $fixture;
}

function postedInvoiceLedgerEntry(array $fixture): CustomerLedgerEntry
{
    return CustomerLedgerEntry::query()
        ->where('customer_id', $fixture['customer']->id)
        ->where('document_type', 'SALES_INVOICE')
        ->where('source_id', $fixture['postedInvoice']->id)
        ->firstOrFail();
}
