<?php

declare(strict_types=1);

namespace App\Services\Sales\ReferralCommissions;

use App\Enums\CommissionCalculationBasis;
use App\Enums\CommissionCalculationStatus;
use App\Enums\CommissionLedgerEntryStatus;
use App\Enums\CommissionLedgerEntryType;
use App\Enums\ReferralCommissionMethod;
use App\Models\CommissionCalculation;
use App\Models\CommissionCalculationLine;
use App\Models\CommissionLedgerEntry;
use App\Models\PostedSalesInvoice;
use App\Models\PostedSalesInvoiceLine;
use App\Models\ReferralCommissionPlan;
use App\Models\ReferralCommissionPlanTier;
use App\Services\AuditTrailService;
use App\Support\DecimalMath;
use App\Support\DecimalPrecision;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class CommissionCalculationService
{
    public const CALCULATION_VERSION = 1;

    public function __construct(
        private readonly CommissionEligibilityService $eligibilityService,
        private readonly AuditTrailService $auditTrailService,
    ) {}

    public function calculateForPostedSalesInvoice(PostedSalesInvoice $invoice, ?int $userId = null): CommissionCalculation
    {
        return DB::transaction(function () use ($invoice, $userId): CommissionCalculation {
            $lockedInvoice = PostedSalesInvoice::query()
                ->with(['lines.item', 'customer'])
                ->lockForUpdate()
                ->findOrFail($invoice->id);

            $idempotencyKey = $this->calculationIdempotencyKey($lockedInvoice);
            $existing = CommissionCalculation::query()
                ->where('idempotency_key', $idempotencyKey)
                ->first();

            if ($existing && $existing->calculation_status !== CommissionCalculationStatus::Failed) {
                return $existing->fresh(['lines', 'ledgerEntries']);
            }

            $headerEligibility = $this->eligibilityService->resolveHeader($lockedInvoice);
            $calculation = $existing ?? CommissionCalculation::query()->create([
                'business_id' => $headerEligibility['customer_referral']?->business_id ?? $headerEligibility['plan']?->business_id,
                'source_type' => PostedSalesInvoice::class,
                'source_id' => $lockedInvoice->id,
                'source_number' => $lockedInvoice->document_number,
                'source_posting_date' => $lockedInvoice->posting_date,
                'customer_id' => $lockedInvoice->customer_id,
                'customer_referral_id' => $headerEligibility['customer_referral']?->id,
                'referrer_id' => $headerEligibility['customer_referral']?->referrer_id,
                'commission_plan_id' => $headerEligibility['plan']?->id,
                'currency_code' => $lockedInvoice->currency_code ?? 'NGN',
                'calculation_status' => CommissionCalculationStatus::Pending,
                'calculated_by' => $userId,
                'calculation_version' => self::CALCULATION_VERSION,
                'idempotency_key' => $idempotencyKey,
                'metadata' => [
                    'phase' => 'referrer_commission_phase_4',
                    'source' => 'posted_sales_invoice',
                ],
            ]);

            if (! $headerEligibility['eligible']) {
                $calculation->update([
                    'calculation_status' => CommissionCalculationStatus::Ineligible,
                    'calculated_at' => now(),
                    'metadata' => array_merge($calculation->metadata ?? [], [
                        'ineligibility_reason' => $headerEligibility['reason_code'],
                        'ineligibility_message' => $headerEligibility['reason_message'],
                    ]),
                ]);

                return $calculation->fresh(['lines', 'ledgerEntries']);
            }

            $plan = $headerEligibility['plan'];
            if (! $plan instanceof ReferralCommissionPlan) {
                throw new RuntimeException('Commission plan resolution failed for an eligible invoice.');
            }

            $totals = [
                'base' => '0',
                'commission' => '0',
                'eligible' => 0,
                'ineligible' => 0,
            ];

            foreach ($lockedInvoice->lines as $line) {
                $lineSnapshot = $this->calculateLine($calculation, $line, $plan);
                $lineModel = CommissionCalculationLine::query()->updateOrCreate(
                    ['idempotency_key' => $lineSnapshot['idempotency_key']],
                    $lineSnapshot
                );

                if ($lineModel->eligibility_status === 'eligible') {
                    $totals['eligible']++;
                    $totals['base'] = DecimalMath::add($totals['base'], $lineModel->eligible_base_amount, DecimalPrecision::AMOUNT_SCALE);
                    $totals['commission'] = DecimalMath::add($totals['commission'], $lineModel->calculated_commission_amount, DecimalPrecision::AMOUNT_SCALE);
                    $this->createAccrualLedgerEntry($calculation, $lineModel, $userId);
                } else {
                    $totals['ineligible']++;
                }
            }

            $calculation->update([
                'calculation_status' => $totals['eligible'] > 0
                    ? CommissionCalculationStatus::Accrued
                    : CommissionCalculationStatus::Ineligible,
                'calculated_base_amount' => $totals['base'],
                'calculated_commission_amount' => $totals['commission'],
                'eligible_line_count' => $totals['eligible'],
                'ineligible_line_count' => $totals['ineligible'],
                'calculated_at' => now(),
            ]);

            $this->auditTrailService->recordGeneric(
                eventType: 'commission',
                action: 'commission_calculated',
                auditable: $calculation,
                documentType: 'COMMISSION_CALCULATION',
                documentNo: (string) $calculation->id,
                userId: $userId,
                description: "Commission calculated for posted sales invoice {$lockedInvoice->document_number}",
                metadata: [
                    'source_type' => PostedSalesInvoice::class,
                    'source_id' => $lockedInvoice->id,
                    'commission_amount' => $totals['commission'],
                ],
            );

            return $calculation->fresh(['lines', 'ledgerEntries']);
        });
    }

    /**
     * @return array<string, mixed>
     */
    private function calculateLine(CommissionCalculation $calculation, PostedSalesInvoiceLine $line, ReferralCommissionPlan $plan): array
    {
        $basis = $plan->calculation_basis ?? CommissionCalculationBasis::LineNetAmount;
        $basisAmount = $this->basisAmount($basis, $line);
        $eligibility = $this->eligibilityService->evaluateLine($line, $plan, $basisAmount);
        $tier = $eligibility['matched_tier'];
        $rate = $this->rateFor($plan, $tier);
        $fixedAmount = $this->fixedAmountFor($plan, $tier);
        $commissionAmount = $eligibility['eligible']
            ? $this->commissionAmount($basis, $basisAmount, $line, $plan->commission_method, $rate, $fixedAmount)
            : '0.0000';

        return [
            'commission_calculation_id' => $calculation->id,
            'source_line_type' => PostedSalesInvoiceLine::class,
            'source_line_id' => $line->id,
            'source_line_number' => $line->line_number,
            'item_id' => $line->item_id,
            'description' => $line->item_description,
            'quantity' => $line->quantity,
            'unit_of_measure_id' => null,
            'gross_amount' => DecimalMath::amount($line->line_total),
            'discount_amount' => DecimalMath::amount($line->line_discount_amount),
            'net_amount' => DecimalMath::amount($line->line_amount),
            'recognized_cost_amount' => DecimalMath::amount($line->cost_amount),
            'gross_profit_amount' => DecimalMath::amount($line->profit_amount),
            'eligible_base_amount' => $eligibility['eligible'] ? $basisAmount : '0.0000',
            'commission_basis' => $basis,
            'commission_rate' => $rate,
            'fixed_commission_amount' => $fixedAmount,
            'calculated_commission_amount' => $commissionAmount,
            'commission_plan_rule_id' => null,
            'commission_tier_id' => $tier?->id,
            'eligibility_status' => $eligibility['eligible'] ? 'eligible' : 'ineligible',
            'ineligibility_reason' => $eligibility['reason_code'],
            'calculation_snapshot' => [
                'plan_id' => $plan->id,
                'plan_code' => $plan->code,
                'plan_priority' => $plan->priority,
                'calculation_basis' => $basis->value,
                'commission_method' => $plan->commission_method->value,
                'rate' => $rate,
                'fixed_amount' => $fixedAmount,
                'tier_id' => $tier?->id,
                'source_line_amounts' => [
                    'line_total' => (string) $line->line_total,
                    'line_discount_amount' => (string) $line->line_discount_amount,
                    'line_amount' => (string) $line->line_amount,
                    'vat_amount' => (string) $line->vat_amount,
                    'cost_amount' => (string) $line->cost_amount,
                    'profit_amount' => (string) $line->profit_amount,
                ],
            ],
            'idempotency_key' => $this->lineIdempotencyKey($calculation, $line),
        ];
    }

    private function createAccrualLedgerEntry(CommissionCalculation $calculation, CommissionCalculationLine $line, ?int $userId): CommissionLedgerEntry
    {
        return CommissionLedgerEntry::query()->firstOrCreate(
            ['idempotency_key' => $this->ledgerIdempotencyKey('accrual', $line)],
            [
                'business_id' => $calculation->business_id,
                'entry_type' => CommissionLedgerEntryType::Accrual,
                'referrer_id' => $calculation->referrer_id,
                'customer_id' => $calculation->customer_id,
                'customer_referral_id' => $calculation->customer_referral_id,
                'commission_calculation_id' => $calculation->id,
                'commission_calculation_line_id' => $line->id,
                'source_type' => $calculation->source_type,
                'source_id' => $calculation->source_id,
                'source_line_id' => $line->source_line_id,
                'source_number' => $calculation->source_number,
                'posting_date' => $calculation->source_posting_date,
                'currency_code' => $calculation->currency_code,
                'amount' => $line->calculated_commission_amount,
                'base_amount' => $line->eligible_base_amount,
                'status' => CommissionLedgerEntryStatus::Open,
                'description' => 'Referral commission accrual',
                'metadata' => [
                    'calculation_version' => $calculation->calculation_version,
                    'calculation_basis' => $line->commission_basis?->value ?? $line->commission_basis,
                ],
                'created_by' => $userId,
            ]
        );
    }

    private function basisAmount(CommissionCalculationBasis $basis, PostedSalesInvoiceLine $line): string
    {
        return match ($basis) {
            CommissionCalculationBasis::GrossSales => DecimalMath::amount($line->line_total),
            CommissionCalculationBasis::NetSales, CommissionCalculationBasis::LineNetAmount => DecimalMath::amount($line->line_amount),
            CommissionCalculationBasis::GrossProfit => DecimalMath::amount($line->profit_amount),
            CommissionCalculationBasis::Quantity => DecimalMath::quantity($line->quantity),
            CommissionCalculationBasis::FixedAmount => '0.0000',
        };
    }

    private function commissionAmount(CommissionCalculationBasis $basis, string $basisAmount, PostedSalesInvoiceLine $line, ReferralCommissionMethod $method, ?string $rate, ?string $fixedAmount): string
    {
        if ($basis === CommissionCalculationBasis::FixedAmount || $method->isFixedAmount()) {
            return DecimalMath::amount($fixedAmount ?? '0');
        }

        $rateDecimal = DecimalMath::div($rate ?? '0', '100', 8);

        return DecimalMath::mul($basisAmount, $rateDecimal, DecimalPrecision::AMOUNT_SCALE);
    }

    private function rateFor(ReferralCommissionPlan $plan, ?ReferralCommissionPlanTier $tier): ?string
    {
        return $tier?->percentage_rate !== null
            ? DecimalMath::toScale($tier->percentage_rate, 4)
            : ($plan->percentage_rate !== null ? DecimalMath::toScale($plan->percentage_rate, 4) : null);
    }

    private function fixedAmountFor(ReferralCommissionPlan $plan, ?ReferralCommissionPlanTier $tier): ?string
    {
        return $tier?->fixed_amount !== null
            ? DecimalMath::amount($tier->fixed_amount)
            : ($plan->fixed_amount !== null ? DecimalMath::amount($plan->fixed_amount) : null);
    }

    private function calculationIdempotencyKey(PostedSalesInvoice $invoice): string
    {
        return hash('sha256', implode('|', [
            'commission-calculation',
            PostedSalesInvoice::class,
            $invoice->id,
            self::CALCULATION_VERSION,
        ]));
    }

    private function lineIdempotencyKey(CommissionCalculation $calculation, PostedSalesInvoiceLine $line): string
    {
        return hash('sha256', implode('|', [
            'commission-calculation-line',
            $calculation->id,
            PostedSalesInvoiceLine::class,
            $line->id,
            self::CALCULATION_VERSION,
        ]));
    }

    public function ledgerIdempotencyKey(string $scope, CommissionCalculationLine $line): string
    {
        return hash('sha256', implode('|', [
            'commission-ledger',
            $scope,
            $line->id,
            self::CALCULATION_VERSION,
        ]));
    }
}
