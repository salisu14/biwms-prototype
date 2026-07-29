<?php

declare(strict_types=1);

use App\Enums\CommissionCalculationBasis;
use App\Enums\CommissionCalculationStatus;
use App\Enums\CommissionLedgerEntryType;
use App\Enums\CustomerReferralStatus;
use App\Enums\ReferralCommissionMethod;
use App\Enums\ReferralCommissionScope;
use App\Models\Business;
use App\Models\CommissionCalculation;
use App\Models\CommissionLedgerEntry;
use App\Models\Customer;
use App\Models\CustomerReferral;
use App\Models\Item;
use App\Models\Permission;
use App\Models\PostedSalesCreditMemo;
use App\Models\PostedSalesInvoice;
use App\Models\ReferralCommissionPlan;
use App\Models\ReferralCommissionPlanTier;
use App\Models\Referrer;
use App\Models\ReferrerCommissionPlanAssignment;
use App\Models\User;
use App\Services\Sales\ReferralCommissions\CommissionAdjustmentService;
use App\Services\Sales\ReferralCommissions\CommissionCalculationService;
use App\Services\Sales\ReferralCommissions\CommissionReversalService;
use App\Services\Sales\ReferralCommissions\ReferrerCommissionBalanceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;

uses(RefreshDatabase::class);

test('posted sales invoice creates snapshotted commission calculation and ledger accrual idempotently', function (): void {
    [$invoice, $referrer] = commissionAccrualFixture(rate: 10);

    $calculation = app(CommissionCalculationService::class)->calculateForPostedSalesInvoice($invoice);
    $again = app(CommissionCalculationService::class)->calculateForPostedSalesInvoice($invoice);

    expect($again->id)->toBe($calculation->id)
        ->and($calculation->calculation_status)->toBe(CommissionCalculationStatus::Accrued)
        ->and((float) $calculation->calculated_base_amount)->toBe(180.0)
        ->and((float) $calculation->calculated_commission_amount)->toBe(18.0)
        ->and($calculation->customer_referral_id)->not->toBeNull()
        ->and($calculation->referrer_id)->toBe($referrer->id)
        ->and($calculation->lines)->toHaveCount(2)
        ->and($calculation->ledgerEntries)->toHaveCount(2)
        ->and(CommissionCalculation::query()->count())->toBe(1)
        ->and(CommissionLedgerEntry::query()->count())->toBe(2);

    $firstLine = $calculation->lines->first();

    expect($firstLine->calculation_snapshot['plan_id'])->toBe($calculation->commission_plan_id)
        ->and($firstLine->calculation_snapshot['calculation_basis'])->toBe('line_net_amount')
        ->and((float) $firstLine->calculated_commission_amount)->toBe(8.0);
});

test('referral and plan effective dates are enforced without creating payable accrual', function (): void {
    [$invoice] = commissionAccrualFixture(referralEffectiveFrom: '2026-08-01');

    $calculation = app(CommissionCalculationService::class)->calculateForPostedSalesInvoice($invoice);

    expect($calculation->calculation_status)->toBe(CommissionCalculationStatus::Ineligible)
        ->and($calculation->metadata['ineligibility_reason'])->toBe('referral_missing')
        ->and($calculation->ledgerEntries()->exists())->toBeFalse();
});

test('gross profit fixed and tiered commission bases are deterministic', function (): void {
    [$grossProfitInvoice] = commissionAccrualFixture(rate: 25, basis: CommissionCalculationBasis::GrossProfit);
    $grossProfitCalculation = app(CommissionCalculationService::class)->calculateForPostedSalesInvoice($grossProfitInvoice);

    expect((float) $grossProfitCalculation->calculated_base_amount)->toBe(120.0)
        ->and((float) $grossProfitCalculation->calculated_commission_amount)->toBe(30.0);

    [$fixedInvoice] = commissionAccrualFixture(method: ReferralCommissionMethod::FIXED_AMOUNT, fixedAmount: 7.5, documentNumber: 'PSI-COMM-FIX');
    $fixedCalculation = app(CommissionCalculationService::class)->calculateForPostedSalesInvoice($fixedInvoice);

    expect((float) $fixedCalculation->calculated_commission_amount)->toBe(15.0);

    [$tieredInvoice, , $plan] = commissionAccrualFixture(method: ReferralCommissionMethod::TIERED_PERCENTAGE, rate: null, documentNumber: 'PSI-COMM-TIER');
    ReferralCommissionPlanTier::factory()->create([
        'referral_commission_plan_id' => $plan->id,
        'sequence' => 1,
        'minimum_threshold' => 0,
        'maximum_threshold' => 90,
        'percentage_rate' => 5,
    ]);
    ReferralCommissionPlanTier::factory()->create([
        'referral_commission_plan_id' => $plan->id,
        'sequence' => 2,
        'minimum_threshold' => 90.0001,
        'maximum_threshold' => null,
        'percentage_rate' => 12,
    ]);

    $tieredCalculation = app(CommissionCalculationService::class)->calculateForPostedSalesInvoice($tieredInvoice);

    expect((float) $tieredCalculation->calculated_commission_amount)->toBe(16.0);
});

test('posted sales credit memo creates append-only partial reversal using original snapshot', function (): void {
    [$invoice] = commissionAccrualFixture(rate: 10);
    $calculation = app(CommissionCalculationService::class)->calculateForPostedSalesInvoice($invoice);
    $invoiceLine = $invoice->lines()->orderBy('line_number')->firstOrFail();

    $creditMemo = PostedSalesCreditMemo::query()->create([
        'document_number' => 'SCM-COMM-001',
        'corrected_invoice_id' => $invoice->id,
        'corrected_invoice_number' => $invoice->document_number,
        'customer_id' => $invoice->customer_id,
        'customer_name' => $invoice->customer_name,
        'posting_date' => '2026-07-29',
        'document_date' => '2026-07-29',
        'subtotal' => 40,
        'line_discount_total' => 0,
        'total_amount' => 40,
        'total_vat' => 0,
        'grand_total' => 40,
        'currency_code' => 'NGN',
        'currency_factor' => 1,
        'amount_applied' => 0,
        'remaining_amount' => 40,
        'fully_applied' => false,
        'refunded' => false,
        'posted_by' => $invoice->posted_by,
        'posted_at' => now(),
    ]);
    $creditMemo->lines()->create([
        'corrected_invoice_line_id' => $invoiceLine->id,
        'item_id' => $invoiceLine->item_id,
        'item_code' => $invoiceLine->item_code,
        'item_description' => $invoiceLine->item_description,
        'posting_date' => '2026-07-29',
        'quantity' => 1,
        'quantity_base' => 1,
        'unit_of_measure_code' => 'PCS',
        'qty_per_unit_of_measure' => 1,
        'unit_price' => 40,
        'unit_cost' => 10,
        'line_discount_amount' => 0,
        'line_total' => 40,
        'line_amount' => 40,
        'vat_amount' => 0,
        'amount_including_vat' => 40,
        'cost_amount_reversed' => 10,
        'inventory_amount_reversed' => 10,
        'line_number' => 10000,
    ]);

    $created = app(CommissionReversalService::class)->reverseForPostedSalesCreditMemo($creditMemo);
    $createdAgain = app(CommissionReversalService::class)->reverseForPostedSalesCreditMemo($creditMemo);

    $reversal = CommissionLedgerEntry::query()->where('entry_type', CommissionLedgerEntryType::Reversal)->firstOrFail();

    expect($created)->toBe(1)
        ->and($createdAgain)->toBe(0)
        ->and((float) $reversal->amount)->toBe(-4.0)
        ->and($reversal->reverses_entry_id)->not->toBeNull()
        ->and($reversal->metadata['uses_original_snapshot'])->toBeTrue()
        ->and($calculation->fresh()->ledgerEntries()->where('entry_type', CommissionLedgerEntryType::Accrual)->count())->toBe(2);
});

test('commission balances are derived from ledger entries and adjustments are authorized append-only entries', function (): void {
    [$invoice, $referrer] = commissionAccrualFixture(rate: 10);
    app(CommissionCalculationService::class)->calculateForPostedSalesInvoice($invoice);

    $actor = User::factory()->create();
    Permission::query()->firstOrCreate(['name' => 'sales.commission_adjustment.create', 'guard_name' => 'web']);
    $actor->givePermissionTo('sales.commission_adjustment.create');

    app(CommissionAdjustmentService::class)->create($referrer, '2026-07-29', 'NGN', '2.5000', 'ROUNDING', 'Rounding adjustment', $actor);

    $balances = app(ReferrerCommissionBalanceService::class)->balances(['referrer_id' => $referrer->id]);

    expect($balances['NGN']['accrued_open'])->toBe('20.5000')
        ->and($balances['NGN']['net_outstanding'])->toBe('20.5000');

    expect(fn () => CommissionLedgerEntry::query()->firstOrFail()->update(['amount' => 999]))
        ->toThrow(RuntimeException::class, 'append-only');
});

test('commission reconcile is report only and exports json', function (): void {
    [$invoice] = commissionAccrualFixture(rate: 10);
    app(CommissionCalculationService::class)->calculateForPostedSalesInvoice($invoice);

    $before = [
        'calculations' => CommissionCalculation::query()->count(),
        'ledger_entries' => CommissionLedgerEntry::query()->count(),
        'invoice_updated_at' => $invoice->fresh()->updated_at?->toDateTimeString(),
    ];
    $exportPath = storage_path('app/reports/commission-reconcile-test.json');
    File::delete($exportPath);

    Artisan::call('biwms:commission-reconcile', [
        '--details' => true,
        '--export' => $exportPath,
    ]);

    $after = [
        'calculations' => CommissionCalculation::query()->count(),
        'ledger_entries' => CommissionLedgerEntry::query()->count(),
        'invoice_updated_at' => $invoice->fresh()->updated_at?->toDateTimeString(),
    ];

    expect($after)->toBe($before)
        ->and(File::exists($exportPath))->toBeTrue()
        ->and(json_decode(File::get($exportPath), true)['mode'])->toBe('report-only');
});

test('phase four architecture does not add customer referrer shortcut or payment and gl posting logic', function (): void {
    expect(Schema::hasColumn('customers', 'referrer_id'))->toBeFalse()
        ->and(File::get(app_path('Services/Sales/ReferralCommissions/CommissionCalculationService.php')))
        ->not->toContain('GlEntry::create')
        ->not->toContain('Payment::create')
        ->not->toContain('BankAccountLedgerEntry');
});

function commissionAccrualFixture(
    ?float $rate = 10,
    ReferralCommissionMethod $method = ReferralCommissionMethod::PERCENTAGE,
    CommissionCalculationBasis $basis = CommissionCalculationBasis::LineNetAmount,
    ?float $fixedAmount = null,
    string $documentNumber = 'PSI-COMM-001',
    string $referralEffectiveFrom = '2026-07-01',
): array {
    $business = Business::query()->create([
        'code' => 'BIZ-COMM-'.str()->random(5),
        'name' => 'Commission Business',
        'is_active' => true,
    ]);
    $poster = User::factory()->create();
    $customer = Customer::factory()->create();
    $referrer = Referrer::factory()->create([
        'business_id' => $business->id,
        'commission_eligible' => true,
        'is_active' => true,
    ]);
    CustomerReferral::factory()->create([
        'business_id' => $business->id,
        'customer_id' => $customer->id,
        'referrer_id' => $referrer->id,
        'status' => CustomerReferralStatus::ACTIVE,
        'is_primary' => true,
        'effective_from' => $referralEffectiveFrom,
        'effective_to' => null,
    ]);
    $plan = ReferralCommissionPlan::factory()->active()->create([
        'business_id' => $business->id,
        'commission_method' => $method,
        'commission_scope' => ReferralCommissionScope::ALL_ELIGIBLE_SALES,
        'calculation_basis' => $basis,
        'percentage_rate' => $rate,
        'fixed_amount' => $fixedAmount,
        'effective_from' => '2026-07-01',
        'effective_to' => null,
    ]);
    ReferrerCommissionPlanAssignment::factory()->create([
        'business_id' => $business->id,
        'referrer_id' => $referrer->id,
        'referral_commission_plan_id' => $plan->id,
        'effective_from' => '2026-07-01',
        'effective_to' => null,
    ]);
    $itemA = Item::factory()->create(['unit_cost' => 10]);
    $itemB = Item::factory()->create(['unit_cost' => 20]);

    $invoice = PostedSalesInvoice::query()->create([
        'document_number' => $documentNumber,
        'customer_id' => $customer->id,
        'customer_name' => $customer->name,
        'posting_date' => '2026-07-29',
        'document_date' => '2026-07-29',
        'due_date' => '2026-08-28',
        'subtotal' => 200,
        'line_discount_total' => 20,
        'invoice_discount_amount' => 0,
        'total_amount' => 180,
        'total_vat' => 0,
        'grand_total' => 180,
        'currency_code' => 'NGN',
        'currency_factor' => 1,
        'amount_paid' => 0,
        'remaining_amount' => 180,
        'paid_in_full' => false,
        'posted_by' => $poster->id,
        'posted_at' => now(),
        'cancelled' => false,
    ]);
    $invoice->lines()->create([
        'item_id' => $itemA->id,
        'item_code' => $itemA->item_code,
        'item_description' => $itemA->description,
        'posting_date' => '2026-07-29',
        'quantity' => 2,
        'unit_of_measure_code' => 'PCS',
        'qty_per_unit_of_measure' => 1,
        'quantity_base' => 2,
        'unit_price' => 50,
        'unit_cost' => 10,
        'line_discount_amount' => 20,
        'line_total' => 100,
        'line_amount' => 80,
        'vat_amount' => 0,
        'amount_including_vat' => 80,
        'cost_amount' => 20,
        'profit_amount' => 60,
        'line_number' => 10000,
    ]);
    $invoice->lines()->create([
        'item_id' => $itemB->id,
        'item_code' => $itemB->item_code,
        'item_description' => $itemB->description,
        'posting_date' => '2026-07-29',
        'quantity' => 1,
        'unit_of_measure_code' => 'PCS',
        'qty_per_unit_of_measure' => 1,
        'quantity_base' => 1,
        'unit_price' => 100,
        'unit_cost' => 40,
        'line_discount_amount' => 0,
        'line_total' => 100,
        'line_amount' => 100,
        'vat_amount' => 0,
        'amount_including_vat' => 100,
        'cost_amount' => 40,
        'profit_amount' => 60,
        'line_number' => 20000,
    ]);

    return [$invoice->fresh('lines.item'), $referrer, $plan, $business];
}
