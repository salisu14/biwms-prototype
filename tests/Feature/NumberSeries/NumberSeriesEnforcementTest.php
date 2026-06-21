<?php

use App\Data\Sales\SalesInvoiceData;
use App\Models\AuditTrail;
use App\Models\BankAccount;
use App\Models\BankAccountLedgerEntry;
use App\Models\Customer;
use App\Models\Item;
use App\Models\NumberSeries;
use App\Models\NumberSeriesLine;
use App\Models\Payment;
use App\Models\PayrollDocument;
use App\Models\PurchaseInvoice;
use App\Models\User;
use App\Models\Vendor;
use App\Services\BankAccountLedgerService;
use App\Services\NumberSeriesService;
use App\Services\Sales\SalesInvoiceService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('generates sales, purchase, payroll, and payment numbers from configured number series', function () {
    createNumberSeriesForTest('S-INV', 'SINV-');
    createNumberSeriesForTest('P-INV', 'PINV-');
    createNumberSeriesForTest('PAYROLL', 'PRL-');
    createNumberSeriesForTest('PAYMENT', 'PAY-');

    $this->actingAs(User::factory()->create());

    $customer = Customer::factory()->create();
    $item = Item::factory()->create();

    $salesInvoice = app(SalesInvoiceService::class)->create(new SalesInvoiceData(
        customer_id: $customer->id,
        sales_order_id: null,
        invoice_date: now()->toDateString(),
        due_date: now()->addDays(30)->toDateString(),
        currency_code: 'NGN',
        lines: [[
            'item_id' => $item->id,
            'description' => 'Number series test item',
            'quantity' => 1,
            'unit_price' => 100,
            'discount_amount' => 0,
            'vat_percent' => 0,
        ]]
    ));

    expect($salesInvoice->invoice_number)->toBe('SINV-000001')
        ->and(PurchaseInvoice::generateNumber())->toBe('PINV-000001')
        ->and(PayrollDocument::generateDocumentNumber())->toBe('PRL-000001');

    $vendor = Vendor::factory()->create();
    $payment = Payment::query()->create([
        'payment_direction' => 'DISBURSEMENT',
        'party_type' => 'VENDOR',
        'party_id' => $vendor->id,
        'party_name' => $vendor->vendor_name,
        'payment_method' => 'BANK_TRANSFER',
        'payment_amount' => 100,
        'payment_date' => now(),
        'posting_date' => now(),
        'status' => 'PENDING',
        'created_by' => auth()->id(),
    ]);

    expect($payment->payment_number)->toBe('PAY-000001');
});

it('generates bank ledger entry numbers from BANK-LEDGER series', function () {
    $lastBankLedgerEntryNumber = (int) (BankAccountLedgerEntry::query()->max('entry_number') ?? 0);
    createNumberSeriesForTest('BANK-LEDGER', '', lastNoUsed: $lastBankLedgerEntryNumber);
    $user = User::factory()->create();
    $this->actingAs($user);

    $bankAccount = BankAccount::factory()->receiptOnly()->create([
        'current_balance' => 100,
        'available_balance' => 100,
    ]);

    $entry = app(BankAccountLedgerService::class)->postDeposit($bankAccount, [
        'amount' => 50,
        'posting_date' => now(),
        'document_no' => 'DEP-AUD-001',
        'description' => 'Number series deposit',
        'user_id' => $user->id,
    ]);

    expect($entry->entry_number)->toBe($lastBankLedgerEntryNumber + 1)
        ->and((float) $bankAccount->fresh()->current_balance)->toBe(150.0);
});

it('fails clearly when number series configuration is missing, inactive, or exhausted', function () {
    expect(fn () => app(NumberSeriesService::class)->getNextNo('MISSING-SERIES'))
        ->toThrow(RuntimeException::class, 'Number Series MISSING-SERIES does not exist');

    createNumberSeriesForTest('INACTIVE-SERIES', 'INA-', active: false);

    expect(fn () => app(NumberSeriesService::class)->getNextNo('INACTIVE-SERIES'))
        ->toThrow(RuntimeException::class, 'Number Series INACTIVE-SERIES is inactive');

    createNumberSeriesForTest('EXHAUSTED-SERIES', 'END-', endingNo: 1, lastNoUsed: 1);

    expect(fn () => app(NumberSeriesService::class)->getNextNo('EXHAUSTED-SERIES'))
        ->toThrow(RuntimeException::class, 'Number Series EXHAUSTED-SERIES is exhausted');
});

it('rapid generation remains unique and sequential', function () {
    createNumberSeriesForTest('RAPID-SERIES', 'RAP-');

    $numbers = collect(range(1, 25))
        ->map(fn (): string => app(NumberSeriesService::class)->getNextNo('RAPID-SERIES'));

    expect($numbers->unique()->count())->toBe(25)
        ->and($numbers->first())->toBe('RAP-000001')
        ->and($numbers->last())->toBe('RAP-000025');
});

it('audits number series setup changes and blocks duplicate payment external references', function () {
    createNumberSeriesForTest('PAYMENT', 'PAY-');
    $actor = User::factory()->create();
    $this->actingAs($actor);

    $series = NumberSeries::query()->where('code', 'PAYMENT')->firstOrFail();
    $series->update(['description' => 'Updated payment series']);

    expect(AuditTrail::query()
        ->where('event_type', 'setup')
        ->where('action', 'updated')
        ->where('auditable_type', $series->getMorphClass())
        ->where('auditable_id', $series->id)
        ->where('user_id', $actor->id)
        ->exists())->toBeTrue();

    $vendor = Vendor::factory()->create();
    Payment::query()->create(paymentPayloadForNumberSeriesTest($vendor, 'BANK-REF-001'));

    expect(fn () => Payment::query()->create(paymentPayloadForNumberSeriesTest($vendor, 'BANK-REF-001')))
        ->toThrow(RuntimeException::class, 'Duplicate payment external reference');
});

function createNumberSeriesForTest(
    string $code,
    string $prefix,
    bool $active = true,
    ?int $endingNo = null,
    int $lastNoUsed = 0,
): NumberSeries {
    $series = NumberSeries::query()->updateOrCreate([
        'code' => $code,
    ], [
        'description' => "{$code} test series",
        'prefix' => $prefix,
        'starting_number' => 1,
        'ending_number' => $endingNo,
        'current_number' => 0,
        'year' => 2026,
        'is_active' => $active,
        'allow_manual' => false,
        'module' => 'test',
    ]);

    $series->lines()->delete();

    NumberSeriesLine::query()->create([
        'number_series_id' => $series->id,
        'starting_date' => '2026-01-01',
        'starting_no' => 0,
        'ending_no' => $endingNo,
        'increment_by' => 1,
        'last_no_used' => $lastNoUsed,
        'no_of_digits' => 6,
        'prefix' => $prefix,
        'suffix' => '',
        'blocked' => false,
    ]);

    return $series;
}

/**
 * @return array<string, mixed>
 */
function paymentPayloadForNumberSeriesTest(Vendor $vendor, string $externalReference): array
{
    return [
        'payment_direction' => 'DISBURSEMENT',
        'party_type' => 'VENDOR',
        'party_id' => $vendor->id,
        'party_name' => $vendor->vendor_name,
        'payment_method' => 'BANK_TRANSFER',
        'payment_amount' => 100,
        'external_reference' => $externalReference,
        'payment_date' => now(),
        'posting_date' => now(),
        'status' => 'PENDING',
        'created_by' => auth()->id() ?? User::factory()->create()->id,
    ];
}
