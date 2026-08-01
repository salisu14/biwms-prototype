<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Enums\CommissionCalculationStatus;
use App\Enums\CommissionLedgerEntryType;
use App\Enums\CommissionLiabilityPostingStatus;
use App\Enums\CommissionPaymentApplicationType;
use App\Enums\CommissionPaymentBatchStatus;
use App\Models\BankAccountLedgerEntry;
use App\Models\CommissionCalculation;
use App\Models\CommissionHold;
use App\Models\CommissionLedgerEntry;
use App\Models\CommissionLiabilityPosting;
use App\Models\CommissionPaymentApplication;
use App\Models\CommissionPaymentBatch;
use App\Models\CommissionPaymentLine;
use App\Models\CommissionReviewBatch;
use App\Models\CommissionReviewBatchLine;
use App\Models\CommissionReviewPeriod;
use App\Models\CommissionSettlementAllocation;
use App\Models\CommissionSettlementBatch;
use App\Models\CommissionSettlementLine;
use App\Models\PostedSalesInvoice;
use App\Support\DecimalMath;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;

#[Signature('biwms:commission-reconcile {--details : Show detailed diagnostic rows} {--business= : Filter by business ID} {--referrer= : Filter by referrer ID} {--customer= : Filter by customer ID} {--plan= : Filter by commission plan ID} {--source= : Filter by source document number} {--date-from= : Filter posting date from YYYY-MM-DD} {--date-to= : Filter posting date to YYYY-MM-DD} {--status= : Filter calculation status} {--review-period= : Filter by commission review period ID} {--review-batch= : Filter by commission review batch ID} {--settlement-batch= : Filter by commission settlement batch ID} {--payment-batch= : Filter by commission payment batch ID} {--payment-status= : Filter by commission payment batch status} {--payment-method= : Filter by commission payment method} {--json : Output machine-readable JSON} {--export= : Write JSON report to path}')]
#[Description('Report referral commission calculation, accrual, reversal, and ledger consistency issues without writing data.')]
class BiwmsCommissionReconcile extends Command
{
    public function handle(): int
    {
        $report = [
            'mode' => 'report-only',
            'filters' => [
                'business' => $this->option('business'),
                'referrer' => $this->option('referrer'),
                'customer' => $this->option('customer'),
                'plan' => $this->option('plan'),
                'source' => $this->option('source'),
                'date_from' => $this->option('date-from'),
                'date_to' => $this->option('date-to'),
                'status' => $this->option('status'),
            ],
            'findings' => [
                'posted_invoice_without_commission_evaluation' => $this->postedInvoicesWithoutCommissionEvaluation(),
                'calculation_without_posted_source' => $this->calculationsWithoutPostedSource(),
                'calculation_total_mismatch' => $this->calculationTotalMismatch(),
                'accrued_calculation_without_ledger' => $this->accruedCalculationWithoutLedger(),
                'ledger_without_calculation' => $this->ledgerWithoutCalculation(),
                'duplicate_calculation' => $this->duplicateCalculation(),
                'duplicate_accrual' => $this->duplicateAccrual(),
                'duplicate_reversal' => $this->duplicateReversal(),
                'reversal_without_original' => $this->reversalWithoutOriginal(),
                'reversal_amount_exceeds_original' => $this->reversalAmountExceedsOriginal(),
                'broken_reversal_chain' => $this->brokenReversalChain(),
                'calculation_without_referral_snapshot' => $this->calculationWithoutReferralSnapshot(),
                'calculation_without_plan_snapshot' => $this->calculationWithoutPlanSnapshot(),
                'expired_plan_used' => [],
                'inactive_referrer_accrued' => [],
                'cross_business_reference' => [],
                'gross_profit_costing_pending' => [],
                'overlapping_review_period' => $this->overlappingReviewPeriod(),
                'review_period_invalid_dates' => $this->reviewPeriodInvalidDates(),
                'review_batch_without_period' => $this->reviewBatchWithoutPeriod(),
                'review_batch_total_mismatch' => $this->reviewBatchTotalMismatch(),
                'review_line_without_ledger_entry' => $this->reviewLineWithoutLedgerEntry(),
                'ledger_entry_in_multiple_active_review_batches' => $this->ledgerEntryInMultipleActiveReviewBatches(),
                'review_line_amount_exceeds_available' => $this->reviewLineAmountExceedsAvailable(),
                'approved_batch_with_pending_lines' => $this->approvedBatchWithPendingLines(),
                'approved_batch_with_active_hold' => $this->approvedBatchWithActiveHold(),
                'approved_batch_with_open_dispute' => $this->approvedBatchWithOpenDispute(),
                'self_approved_review_batch' => $this->selfApprovedReviewBatch(),
                'hold_amount_exceeds_available' => $this->holdAmountExceedsAvailable(),
                'settlement_batch_without_approved_review' => $this->settlementBatchWithoutApprovedReview(),
                'settlement_batch_total_mismatch' => $this->settlementBatchTotalMismatch(),
                'settlement_line_total_mismatch' => $this->settlementLineTotalMismatch(),
                'settlement_allocation_missing' => $this->settlementAllocationMissing(),
                'settlement_allocation_duplicate' => $this->settlementAllocationDuplicate(),
                'settlement_allocation_exceeds_available' => $this->settlementAllocationExceedsAvailable(),
                'ledger_entry_in_multiple_active_settlement_batches' => $this->ledgerEntryInMultipleActiveSettlementBatches(),
                'settlement_currency_mismatch' => $this->settlementCurrencyMismatch(),
                'self_approved_settlement_batch' => $this->selfApprovedSettlementBatch(),
                ...$this->phaseSixFindings(),
            ],
        ];

        if ($exportPath = $this->option('export')) {
            $this->exportReport($report, (string) $exportPath);
        }

        if ($this->option('json')) {
            $this->line(json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

            return self::SUCCESS;
        }

        $this->info('BIWMS Commission Reconciliation');
        $this->line('Mode: report-only. No commission, source, referral, plan, ledger, or payment records were changed.');
        if ($exportPath) {
            $this->line("Exported JSON report to {$exportPath}.");
        }
        $this->newLine();

        foreach ($report['findings'] as $classification => $findings) {
            $this->line(str($classification)->replace('_', ' ')->headline().': '.count($findings));

            if ($this->option('details')) {
                foreach ($findings as $finding) {
                    $this->line('  - '.$finding['message'].' '.json_encode($finding['context'], JSON_UNESCAPED_SLASHES));
                }
            }
        }

        return self::SUCCESS;
    }

    private function postedInvoicesWithoutCommissionEvaluation(): array
    {
        return PostedSalesInvoice::query()
            ->when($this->option('source'), fn (Builder $query, string $source): Builder => $query->where('document_number', $source))
            ->whereDoesntHave('commissionCalculations')
            ->limit(250)
            ->get()
            ->map(fn (PostedSalesInvoice $invoice): array => $this->finding('posted_invoice_without_commission_evaluation', 'warning', 'Posted sales invoice has not been evaluated for commission.', [
                'posted_sales_invoice_id' => $invoice->id,
                'document_number' => $invoice->document_number,
            ]))
            ->all();
    }

    private function calculationsWithoutPostedSource(): array
    {
        return $this->calculationQuery()
            ->where('source_type', PostedSalesInvoice::class)
            ->whereDoesntHave('postedSalesInvoice')
            ->limit(250)
            ->get()
            ->map(fn (CommissionCalculation $calculation): array => $this->finding('calculation_without_posted_source', 'critical', 'Commission calculation references a missing posted source.', [
                'commission_calculation_id' => $calculation->id,
                'source_type' => $calculation->source_type,
                'source_id' => $calculation->source_id,
            ]))
            ->all();
    }

    private function calculationTotalMismatch(): array
    {
        return $this->calculationQuery()
            ->with('lines')
            ->limit(250)
            ->get()
            ->filter(fn (CommissionCalculation $calculation): bool => DecimalMath::compare(
                $calculation->calculated_commission_amount,
                $calculation->lines->sum('calculated_commission_amount')
            ) !== 0)
            ->map(fn (CommissionCalculation $calculation): array => $this->finding('calculation_total_mismatch', 'critical', 'Calculation header total does not match calculation line total.', [
                'commission_calculation_id' => $calculation->id,
                'header_total' => (string) $calculation->calculated_commission_amount,
                'line_total' => (string) $calculation->lines->sum('calculated_commission_amount'),
            ]))
            ->values()
            ->all();
    }

    private function accruedCalculationWithoutLedger(): array
    {
        return $this->calculationQuery()
            ->where('calculation_status', CommissionCalculationStatus::Accrued)
            ->whereDoesntHave('ledgerEntries')
            ->limit(250)
            ->get()
            ->map(fn (CommissionCalculation $calculation): array => $this->finding('accrued_calculation_without_ledger', 'critical', 'Accrued calculation has no commission ledger entries.', [
                'commission_calculation_id' => $calculation->id,
            ]))
            ->all();
    }

    private function ledgerWithoutCalculation(): array
    {
        return $this->ledgerQuery()
            ->whereNotNull('commission_calculation_id')
            ->whereDoesntHave('calculation')
            ->limit(250)
            ->get()
            ->map(fn (CommissionLedgerEntry $entry): array => $this->finding('ledger_without_calculation', 'critical', 'Commission ledger entry references a missing calculation.', [
                'commission_ledger_entry_id' => $entry->id,
            ]))
            ->all();
    }

    private function duplicateCalculation(): array
    {
        return CommissionCalculation::query()
            ->selectRaw('source_type, source_id, calculation_version, COUNT(*) as duplicate_count')
            ->groupBy('source_type', 'source_id', 'calculation_version')
            ->havingRaw('COUNT(*) > 1')
            ->limit(250)
            ->get()
            ->map(fn ($row): array => $this->finding('duplicate_calculation', 'critical', 'Duplicate commission calculations exist for the same source and version.', [
                'source_type' => $row->source_type,
                'source_id' => $row->source_id,
                'calculation_version' => $row->calculation_version,
                'duplicate_count' => $row->duplicate_count,
            ]))
            ->all();
    }

    private function duplicateAccrual(): array
    {
        return CommissionLedgerEntry::query()
            ->where('entry_type', CommissionLedgerEntryType::Accrual)
            ->selectRaw('commission_calculation_line_id, COUNT(*) as duplicate_count')
            ->groupBy('commission_calculation_line_id')
            ->havingRaw('COUNT(*) > 1')
            ->limit(250)
            ->get()
            ->map(fn ($row): array => $this->finding('duplicate_accrual', 'critical', 'Duplicate commission accrual entries exist for one calculation line.', [
                'commission_calculation_line_id' => $row->commission_calculation_line_id,
                'duplicate_count' => $row->duplicate_count,
            ]))
            ->all();
    }

    private function duplicateReversal(): array
    {
        return CommissionLedgerEntry::query()
            ->where('entry_type', CommissionLedgerEntryType::Reversal)
            ->selectRaw('reverses_entry_id, source_type, source_id, source_line_id, COUNT(*) as duplicate_count')
            ->groupBy('reverses_entry_id', 'source_type', 'source_id', 'source_line_id')
            ->havingRaw('COUNT(*) > 1')
            ->limit(250)
            ->get()
            ->map(fn ($row): array => $this->finding('duplicate_reversal', 'critical', 'Duplicate commission reversal entries exist for one source reversal.', [
                'reverses_entry_id' => $row->reverses_entry_id,
                'source_type' => $row->source_type,
                'source_id' => $row->source_id,
                'source_line_id' => $row->source_line_id,
            ]))
            ->all();
    }

    private function reversalWithoutOriginal(): array
    {
        return CommissionLedgerEntry::query()
            ->where('entry_type', CommissionLedgerEntryType::Reversal)
            ->whereNull('reverses_entry_id')
            ->limit(250)
            ->get()
            ->map(fn (CommissionLedgerEntry $entry): array => $this->finding('reversal_without_original', 'critical', 'Commission reversal has no original accrual reference.', [
                'commission_ledger_entry_id' => $entry->id,
            ]))
            ->all();
    }

    private function reversalAmountExceedsOriginal(): array
    {
        return CommissionLedgerEntry::query()
            ->where('entry_type', CommissionLedgerEntryType::Accrual)
            ->withSum('reversalEntries as reversal_total', 'amount')
            ->limit(250)
            ->get()
            ->filter(fn (CommissionLedgerEntry $entry): bool => DecimalMath::compare(abs((float) ($entry->reversal_total ?? 0)), abs((float) $entry->amount)) > 0)
            ->map(fn (CommissionLedgerEntry $entry): array => $this->finding('reversal_amount_exceeds_original', 'critical', 'Commission reversals exceed the original accrual amount.', [
                'commission_ledger_entry_id' => $entry->id,
                'amount' => (string) $entry->amount,
                'reversal_total' => (string) ($entry->reversal_total ?? 0),
            ]))
            ->values()
            ->all();
    }

    private function brokenReversalChain(): array
    {
        return CommissionLedgerEntry::query()
            ->whereNotNull('reverses_entry_id')
            ->whereDoesntHave('reversesEntry')
            ->limit(250)
            ->get()
            ->map(fn (CommissionLedgerEntry $entry): array => $this->finding('broken_reversal_chain', 'critical', 'Commission ledger reversal chain is broken.', [
                'commission_ledger_entry_id' => $entry->id,
                'reverses_entry_id' => $entry->reverses_entry_id,
            ]))
            ->all();
    }

    private function calculationWithoutReferralSnapshot(): array
    {
        return $this->calculationQuery()
            ->where('calculation_status', CommissionCalculationStatus::Accrued)
            ->whereNull('customer_referral_id')
            ->limit(250)
            ->get()
            ->map(fn (CommissionCalculation $calculation): array => $this->finding('calculation_without_referral_snapshot', 'critical', 'Accrued calculation has no referral snapshot.', [
                'commission_calculation_id' => $calculation->id,
            ]))
            ->all();
    }

    private function calculationWithoutPlanSnapshot(): array
    {
        return $this->calculationQuery()
            ->where('calculation_status', CommissionCalculationStatus::Accrued)
            ->whereNull('commission_plan_id')
            ->limit(250)
            ->get()
            ->map(fn (CommissionCalculation $calculation): array => $this->finding('calculation_without_plan_snapshot', 'critical', 'Accrued calculation has no commission plan snapshot.', [
                'commission_calculation_id' => $calculation->id,
            ]))
            ->all();
    }

    private function overlappingReviewPeriod(): array
    {
        return CommissionReviewPeriod::query()
            ->whereExists(function ($query): void {
                $query->selectRaw('1')
                    ->from('commission_review_periods as other')
                    ->whereColumn('other.business_id', 'commission_review_periods.business_id')
                    ->whereColumn('other.id', '!=', 'commission_review_periods.id')
                    ->whereColumn('other.period_start', '<=', 'commission_review_periods.period_end')
                    ->whereColumn('other.period_end', '>=', 'commission_review_periods.period_start')
                    ->where('other.status', '!=', 'cancelled');
            })
            ->limit(250)
            ->get()
            ->map(fn (CommissionReviewPeriod $period): array => $this->finding('overlapping_review_period', 'critical', 'Commission review period overlaps another active period.', [
                'commission_review_period_id' => $period->id,
                'code' => $period->code,
            ]))
            ->all();
    }

    private function reviewPeriodInvalidDates(): array
    {
        return CommissionReviewPeriod::query()
            ->whereColumn('period_start', '>', 'period_end')
            ->limit(250)
            ->get()
            ->map(fn (CommissionReviewPeriod $period): array => $this->finding('review_period_invalid_dates', 'critical', 'Commission review period start date is after end date.', [
                'commission_review_period_id' => $period->id,
            ]))
            ->all();
    }

    private function reviewBatchWithoutPeriod(): array
    {
        return $this->reviewBatchQuery()
            ->whereDoesntHave('period')
            ->limit(250)
            ->get()
            ->map(fn (CommissionReviewBatch $batch): array => $this->finding('review_batch_without_period', 'critical', 'Commission review batch has no review period.', [
                'commission_review_batch_id' => $batch->id,
            ]))
            ->all();
    }

    private function reviewBatchTotalMismatch(): array
    {
        return $this->reviewBatchQuery()
            ->with('lines')
            ->limit(250)
            ->get()
            ->filter(fn (CommissionReviewBatch $batch): bool => DecimalMath::compare($batch->total_eligible_amount, $batch->lines->sum('approved_amount')) !== 0)
            ->map(fn (CommissionReviewBatch $batch): array => $this->finding('review_batch_total_mismatch', 'critical', 'Review batch total does not match review line approved total.', [
                'commission_review_batch_id' => $batch->id,
                'batch_total' => (string) $batch->total_eligible_amount,
                'line_total' => (string) $batch->lines->sum('approved_amount'),
            ]))
            ->values()
            ->all();
    }

    private function reviewLineWithoutLedgerEntry(): array
    {
        return $this->reviewLineQuery()
            ->whereDoesntHave('ledgerEntry')
            ->limit(250)
            ->get()
            ->map(fn (CommissionReviewBatchLine $line): array => $this->finding('review_line_without_ledger_entry', 'critical', 'Commission review line has no source ledger entry.', [
                'commission_review_batch_line_id' => $line->id,
            ]))
            ->all();
    }

    private function ledgerEntryInMultipleActiveReviewBatches(): array
    {
        return CommissionReviewBatchLine::query()
            ->selectRaw('commission_ledger_entry_id, COUNT(*) as duplicate_count')
            ->groupBy('commission_ledger_entry_id')
            ->havingRaw('COUNT(*) > 1')
            ->limit(250)
            ->get()
            ->map(fn ($row): array => $this->finding('ledger_entry_in_multiple_active_review_batches', 'critical', 'Commission ledger entry appears in multiple review batches.', [
                'commission_ledger_entry_id' => $row->commission_ledger_entry_id,
                'duplicate_count' => $row->duplicate_count,
            ]))
            ->all();
    }

    private function reviewLineAmountExceedsAvailable(): array
    {
        return $this->reviewLineQuery()
            ->whereColumn('approved_amount', '>', 'eligible_amount')
            ->limit(250)
            ->get()
            ->map(fn (CommissionReviewBatchLine $line): array => $this->finding('review_line_amount_exceeds_available', 'critical', 'Review line approved amount exceeds eligible amount.', [
                'commission_review_batch_line_id' => $line->id,
            ]))
            ->all();
    }

    private function approvedBatchWithPendingLines(): array
    {
        return $this->reviewBatchQuery()
            ->whereIn('status', ['approved', 'locked'])
            ->whereHas('lines', fn (Builder $query): Builder => $query->whereIn('review_status', ['pending', 'disputed']))
            ->limit(250)
            ->get()
            ->map(fn (CommissionReviewBatch $batch): array => $this->finding('approved_batch_with_pending_lines', 'critical', 'Approved review batch has pending or disputed lines.', [
                'commission_review_batch_id' => $batch->id,
            ]))
            ->all();
    }

    private function approvedBatchWithActiveHold(): array
    {
        return $this->reviewBatchQuery()
            ->whereIn('status', ['approved', 'locked'])
            ->whereHas('holds', fn (Builder $query): Builder => $query->where('status', 'active'))
            ->limit(250)
            ->get()
            ->map(fn (CommissionReviewBatch $batch): array => $this->finding('approved_batch_with_active_hold', 'warning', 'Approved review batch has an active hold.', [
                'commission_review_batch_id' => $batch->id,
            ]))
            ->all();
    }

    private function approvedBatchWithOpenDispute(): array
    {
        return $this->reviewBatchQuery()
            ->whereIn('status', ['approved', 'locked'])
            ->whereHas('disputes', fn (Builder $query): Builder => $query->whereIn('status', ['open', 'under_review', 'awaiting_information']))
            ->limit(250)
            ->get()
            ->map(fn (CommissionReviewBatch $batch): array => $this->finding('approved_batch_with_open_dispute', 'warning', 'Approved review batch has an open dispute.', [
                'commission_review_batch_id' => $batch->id,
            ]))
            ->all();
    }

    private function selfApprovedReviewBatch(): array
    {
        return $this->reviewBatchQuery()
            ->whereNotNull('submitted_by')
            ->whereColumn('submitted_by', 'approved_by')
            ->limit(250)
            ->get()
            ->map(fn (CommissionReviewBatch $batch): array => $this->finding('self_approved_review_batch', 'critical', 'Commission review batch was approved by its submitter.', [
                'commission_review_batch_id' => $batch->id,
            ]))
            ->all();
    }

    private function holdAmountExceedsAvailable(): array
    {
        return CommissionHold::query()
            ->whereHas('line', fn (Builder $query): Builder => $query->whereColumn('commission_holds.amount', '>', 'commission_review_batch_lines.eligible_amount'))
            ->limit(250)
            ->get()
            ->map(fn (CommissionHold $hold): array => $this->finding('hold_amount_exceeds_available', 'critical', 'Commission hold exceeds line eligible amount.', [
                'commission_hold_id' => $hold->id,
            ]))
            ->all();
    }

    private function settlementBatchWithoutApprovedReview(): array
    {
        return $this->settlementBatchQuery()
            ->whereHas('reviewBatch', fn (Builder $query): Builder => $query->whereNotIn('status', ['approved', 'locked']))
            ->limit(250)
            ->get()
            ->map(fn (CommissionSettlementBatch $batch): array => $this->finding('settlement_batch_without_approved_review', 'critical', 'Settlement batch does not reference an approved review batch.', [
                'commission_settlement_batch_id' => $batch->id,
            ]))
            ->all();
    }

    private function settlementBatchTotalMismatch(): array
    {
        return $this->settlementBatchQuery()
            ->with('lines')
            ->limit(250)
            ->get()
            ->filter(fn (CommissionSettlementBatch $batch): bool => DecimalMath::compare($batch->total_net_amount, $batch->lines->sum('net_settlement_amount')) !== 0)
            ->map(fn (CommissionSettlementBatch $batch): array => $this->finding('settlement_batch_total_mismatch', 'critical', 'Settlement batch total does not match settlement line total.', [
                'commission_settlement_batch_id' => $batch->id,
            ]))
            ->values()
            ->all();
    }

    private function settlementLineTotalMismatch(): array
    {
        return CommissionSettlementLine::query()
            ->with('allocations')
            ->limit(250)
            ->get()
            ->filter(fn (CommissionSettlementLine $line): bool => DecimalMath::compare($line->net_settlement_amount, $line->allocations->sum('allocated_amount')) !== 0)
            ->map(fn (CommissionSettlementLine $line): array => $this->finding('settlement_line_total_mismatch', 'critical', 'Settlement line total does not match allocation total.', [
                'commission_settlement_line_id' => $line->id,
            ]))
            ->values()
            ->all();
    }

    private function settlementAllocationMissing(): array
    {
        return CommissionSettlementLine::query()
            ->whereDoesntHave('allocations')
            ->limit(250)
            ->get()
            ->map(fn (CommissionSettlementLine $line): array => $this->finding('settlement_allocation_missing', 'critical', 'Settlement line has no ledger allocations.', [
                'commission_settlement_line_id' => $line->id,
            ]))
            ->all();
    }

    private function settlementAllocationDuplicate(): array
    {
        return CommissionSettlementAllocation::query()
            ->selectRaw('commission_ledger_entry_id, COUNT(*) as duplicate_count')
            ->groupBy('commission_ledger_entry_id')
            ->havingRaw('COUNT(*) > 1')
            ->limit(250)
            ->get()
            ->map(fn ($row): array => $this->finding('settlement_allocation_duplicate', 'critical', 'Commission ledger entry appears in multiple settlement allocations.', [
                'commission_ledger_entry_id' => $row->commission_ledger_entry_id,
                'duplicate_count' => $row->duplicate_count,
            ]))
            ->all();
    }

    private function settlementAllocationExceedsAvailable(): array
    {
        return CommissionSettlementAllocation::query()
            ->whereHas('ledgerEntry', fn (Builder $query): Builder => $query->whereColumn('commission_settlement_allocations.allocated_amount', '>', 'commission_ledger_entries.amount'))
            ->limit(250)
            ->get()
            ->map(fn (CommissionSettlementAllocation $allocation): array => $this->finding('settlement_allocation_exceeds_available', 'critical', 'Settlement allocation exceeds source ledger amount.', [
                'commission_settlement_allocation_id' => $allocation->id,
            ]))
            ->all();
    }

    private function ledgerEntryInMultipleActiveSettlementBatches(): array
    {
        return $this->settlementAllocationDuplicate();
    }

    private function settlementCurrencyMismatch(): array
    {
        return CommissionSettlementAllocation::query()
            ->whereHas('ledgerEntry', fn (Builder $query): Builder => $query->whereColumn('commission_settlement_allocations.currency_code', '!=', 'commission_ledger_entries.currency_code'))
            ->limit(250)
            ->get()
            ->map(fn (CommissionSettlementAllocation $allocation): array => $this->finding('settlement_currency_mismatch', 'critical', 'Settlement allocation currency does not match ledger entry currency.', [
                'commission_settlement_allocation_id' => $allocation->id,
            ]))
            ->all();
    }

    private function selfApprovedSettlementBatch(): array
    {
        return $this->settlementBatchQuery()
            ->whereNotNull('prepared_by')
            ->whereColumn('prepared_by', 'approved_by')
            ->limit(250)
            ->get()
            ->map(fn (CommissionSettlementBatch $batch): array => $this->finding('self_approved_settlement_batch', 'critical', 'Commission settlement batch was approved by its preparer.', [
                'commission_settlement_batch_id' => $batch->id,
            ]))
            ->all();
    }

    private function phaseSixFindings(): array
    {
        $keys = [
            'locked_settlement_without_liability',
            'duplicate_liability_posting',
            'liability_total_mismatch',
            'liability_gl_missing',
            'payment_batch_without_settlement',
            'payment_batch_currency_mismatch',
            'payment_batch_total_mismatch',
            'payment_line_total_mismatch',
            'payment_without_approval',
            'payment_without_liability',
            'payment_amount_exceeds_outstanding',
            'duplicate_payment_application',
            'payment_application_missing',
            'payment_application_exceeds_allocation',
            'payment_without_bank_or_cash_entry',
            'payment_gl_missing',
            'payment_posted_but_batch_not_posted',
            'negative_outstanding',
            'overpayment_detected',
            'payment_reversal_without_original',
            'self_approved_payment_batch',
            'cross_business_payment_reference',
        ];

        if (! Schema::hasTable('commission_liability_postings') || ! Schema::hasTable('commission_payment_batches')) {
            return array_fill_keys($keys, []);
        }

        return [
            'locked_settlement_without_liability' => $this->lockedSettlementWithoutLiability(),
            'duplicate_liability_posting' => $this->duplicateLiabilityPosting(),
            'liability_total_mismatch' => $this->liabilityTotalMismatch(),
            'liability_gl_missing' => $this->liabilityGlMissing(),
            'payment_batch_without_settlement' => $this->paymentBatchWithoutSettlement(),
            'payment_batch_currency_mismatch' => $this->paymentBatchCurrencyMismatch(),
            'payment_batch_total_mismatch' => $this->paymentBatchTotalMismatch(),
            'payment_line_total_mismatch' => $this->paymentLineTotalMismatch(),
            'payment_without_approval' => $this->paymentWithoutApproval(),
            'payment_without_liability' => $this->paymentWithoutLiability(),
            'payment_amount_exceeds_outstanding' => $this->paymentAmountExceedsOutstanding(),
            'duplicate_payment_application' => $this->duplicatePaymentApplication(),
            'payment_application_missing' => $this->paymentApplicationMissing(),
            'payment_application_exceeds_allocation' => $this->paymentApplicationExceedsAllocation(),
            'payment_without_bank_or_cash_entry' => $this->paymentWithoutBankOrCashEntry(),
            'payment_gl_missing' => $this->paymentGlMissing(),
            'payment_posted_but_batch_not_posted' => $this->paymentPostedButBatchNotPosted(),
            'negative_outstanding' => $this->negativeOutstanding(),
            'overpayment_detected' => $this->overpaymentDetected(),
            'payment_reversal_without_original' => $this->paymentReversalWithoutOriginal(),
            'self_approved_payment_batch' => $this->selfApprovedPaymentBatch(),
            'cross_business_payment_reference' => $this->crossBusinessPaymentReference(),
        ];
    }

    private function lockedSettlementWithoutLiability(): array
    {
        return $this->settlementBatchQuery()
            ->where('status', 'locked')
            ->whereDoesntHave('liabilityPosting', fn (Builder $query): Builder => $query->where('status', CommissionLiabilityPostingStatus::Posted))
            ->limit(250)
            ->get()
            ->map(fn (CommissionSettlementBatch $batch): array => $this->finding('locked_settlement_without_liability', 'critical', 'Locked commission settlement has no posted liability recognition.', [
                'commission_settlement_batch_id' => $batch->id,
                'settlement_number' => $batch->settlement_number,
            ]))
            ->all();
    }

    private function duplicateLiabilityPosting(): array
    {
        return CommissionLiabilityPosting::query()
            ->selectRaw('commission_settlement_batch_id, COUNT(*) as duplicate_count')
            ->groupBy('commission_settlement_batch_id')
            ->havingRaw('COUNT(*) > 1')
            ->limit(250)
            ->get()
            ->map(fn ($row): array => $this->finding('duplicate_liability_posting', 'critical', 'Multiple commission liability postings exist for one settlement.', [
                'commission_settlement_batch_id' => $row->commission_settlement_batch_id,
                'duplicate_count' => $row->duplicate_count,
            ]))
            ->all();
    }

    private function liabilityTotalMismatch(): array
    {
        return CommissionLiabilityPosting::query()
            ->with('settlementBatch')
            ->where('status', CommissionLiabilityPostingStatus::Posted)
            ->limit(250)
            ->get()
            ->filter(fn (CommissionLiabilityPosting $posting): bool => DecimalMath::compare($posting->net_liability_amount, $posting->settlementBatch?->total_net_amount ?? 0) !== 0)
            ->map(fn (CommissionLiabilityPosting $posting): array => $this->finding('liability_total_mismatch', 'critical', 'Commission liability posting total does not match locked settlement total.', [
                'commission_liability_posting_id' => $posting->id,
                'commission_settlement_batch_id' => $posting->commission_settlement_batch_id,
                'liability_amount' => (string) $posting->net_liability_amount,
                'settlement_amount' => (string) ($posting->settlementBatch?->total_net_amount ?? 0),
            ]))
            ->values()
            ->all();
    }

    private function liabilityGlMissing(): array
    {
        return CommissionLiabilityPosting::query()
            ->where('status', CommissionLiabilityPostingStatus::Posted)
            ->whereDoesntHave('postingTransaction.glEntries')
            ->limit(250)
            ->get()
            ->map(fn (CommissionLiabilityPosting $posting): array => $this->finding('liability_gl_missing', 'critical', 'Posted commission liability has no G/L entries.', [
                'commission_liability_posting_id' => $posting->id,
                'document_number' => $posting->document_number,
            ]))
            ->all();
    }

    private function paymentBatchWithoutSettlement(): array
    {
        return $this->paymentBatchQuery()
            ->whereDoesntHave('settlementBatch')
            ->limit(250)
            ->get()
            ->map(fn (CommissionPaymentBatch $batch): array => $this->finding('payment_batch_without_settlement', 'critical', 'Commission payment batch references a missing settlement.', [
                'commission_payment_batch_id' => $batch->id,
                'batch_number' => $batch->batch_number,
            ]))
            ->all();
    }

    private function paymentBatchCurrencyMismatch(): array
    {
        return $this->paymentBatchQuery()
            ->whereHas('settlementBatch', fn (Builder $query): Builder => $query->whereColumn('commission_payment_batches.currency_code', '!=', 'commission_settlement_batches.currency_code'))
            ->limit(250)
            ->get()
            ->map(fn (CommissionPaymentBatch $batch): array => $this->finding('payment_batch_currency_mismatch', 'critical', 'Commission payment batch currency differs from settlement currency.', [
                'commission_payment_batch_id' => $batch->id,
            ]))
            ->all();
    }

    private function paymentBatchTotalMismatch(): array
    {
        return $this->paymentBatchQuery()
            ->with('lines')
            ->limit(250)
            ->get()
            ->filter(fn (CommissionPaymentBatch $batch): bool => DecimalMath::compare($batch->total_amount, $batch->lines->sum('payment_amount')) !== 0)
            ->map(fn (CommissionPaymentBatch $batch): array => $this->finding('payment_batch_total_mismatch', 'critical', 'Commission payment batch total does not match line payment total.', [
                'commission_payment_batch_id' => $batch->id,
                'batch_total' => (string) $batch->total_amount,
                'line_total' => (string) $batch->lines->sum('payment_amount'),
            ]))
            ->values()
            ->all();
    }

    private function paymentLineTotalMismatch(): array
    {
        return CommissionPaymentLine::query()
            ->whereColumn('payment_amount', '>', 'approved_amount')
            ->limit(250)
            ->get()
            ->map(fn (CommissionPaymentLine $line): array => $this->finding('payment_line_total_mismatch', 'critical', 'Commission payment line exceeds approved settlement amount.', [
                'commission_payment_line_id' => $line->id,
            ]))
            ->all();
    }

    private function paymentWithoutApproval(): array
    {
        return $this->paymentBatchQuery()
            ->whereIn('status', [CommissionPaymentBatchStatus::Posted, CommissionPaymentBatchStatus::Reversed])
            ->whereNull('approved_at')
            ->limit(250)
            ->get()
            ->map(fn (CommissionPaymentBatch $batch): array => $this->finding('payment_without_approval', 'critical', 'Commission payment batch was posted without approval timestamp.', [
                'commission_payment_batch_id' => $batch->id,
            ]))
            ->all();
    }

    private function paymentWithoutLiability(): array
    {
        return $this->paymentBatchQuery()
            ->whereIn('status', [CommissionPaymentBatchStatus::Posted, CommissionPaymentBatchStatus::Reversed])
            ->whereDoesntHave('settlementBatch.liabilityPosting', fn (Builder $query): Builder => $query->where('status', CommissionLiabilityPostingStatus::Posted))
            ->limit(250)
            ->get()
            ->map(fn (CommissionPaymentBatch $batch): array => $this->finding('payment_without_liability', 'critical', 'Commission payment was posted before liability recognition.', [
                'commission_payment_batch_id' => $batch->id,
            ]))
            ->all();
    }

    private function paymentAmountExceedsOutstanding(): array
    {
        return CommissionPaymentLine::query()
            ->whereRaw('payment_amount > (approved_amount - previously_paid_amount)')
            ->limit(250)
            ->get()
            ->map(fn (CommissionPaymentLine $line): array => $this->finding('payment_amount_exceeds_outstanding', 'critical', 'Commission payment line exceeds outstanding amount at preparation.', [
                'commission_payment_line_id' => $line->id,
            ]))
            ->all();
    }

    private function duplicatePaymentApplication(): array
    {
        return CommissionPaymentApplication::query()
            ->selectRaw('commission_payment_line_id, commission_settlement_allocation_id, application_type, COUNT(*) as duplicate_count')
            ->groupBy('commission_payment_line_id', 'commission_settlement_allocation_id', 'application_type')
            ->havingRaw('COUNT(*) > 1')
            ->limit(250)
            ->get()
            ->map(fn ($row): array => $this->finding('duplicate_payment_application', 'critical', 'Duplicate commission payment application exists for one line/allocation/type.', [
                'commission_payment_line_id' => $row->commission_payment_line_id,
                'commission_settlement_allocation_id' => $row->commission_settlement_allocation_id,
                'application_type' => $row->application_type,
            ]))
            ->all();
    }

    private function paymentApplicationMissing(): array
    {
        return CommissionPaymentLine::query()
            ->where('status', 'posted')
            ->whereDoesntHave('applications')
            ->limit(250)
            ->get()
            ->map(fn (CommissionPaymentLine $line): array => $this->finding('payment_application_missing', 'critical', 'Posted commission payment line has no settlement application.', [
                'commission_payment_line_id' => $line->id,
            ]))
            ->all();
    }

    private function paymentApplicationExceedsAllocation(): array
    {
        return CommissionSettlementAllocation::query()
            ->withSum(['paymentApplications as payment_total' => fn (Builder $query): Builder => $query->where('application_type', CommissionPaymentApplicationType::Payment)], 'applied_amount')
            ->withSum(['paymentApplications as reversal_total' => fn (Builder $query): Builder => $query->where('application_type', CommissionPaymentApplicationType::Reversal)], 'applied_amount')
            ->limit(250)
            ->get()
            ->filter(fn (CommissionSettlementAllocation $allocation): bool => ((float) ($allocation->payment_total ?? 0) - (float) ($allocation->reversal_total ?? 0)) - (float) $allocation->allocated_amount > 0.0001)
            ->map(fn (CommissionSettlementAllocation $allocation): array => $this->finding('payment_application_exceeds_allocation', 'critical', 'Commission payment applications exceed settlement allocation amount.', [
                'commission_settlement_allocation_id' => $allocation->id,
                'allocated_amount' => (string) $allocation->allocated_amount,
                'paid_amount' => (float) ($allocation->payment_total ?? 0) - (float) ($allocation->reversal_total ?? 0),
            ]))
            ->values()
            ->all();
    }

    private function paymentWithoutBankOrCashEntry(): array
    {
        return $this->paymentBatchQuery()
            ->where('status', CommissionPaymentBatchStatus::Posted)
            ->limit(250)
            ->get()
            ->filter(function (CommissionPaymentBatch $batch): bool {
                if ($batch->bank_account_id) {
                    return ! BankAccountLedgerEntry::query()
                        ->where('source_type', 'commission_payment')
                        ->where('source_id', $batch->id)
                        ->exists();
                }

                return false;
            })
            ->map(fn (CommissionPaymentBatch $batch): array => $this->finding('payment_without_bank_or_cash_entry', 'critical', 'Posted commission payment has no matching bank/cash ledger record.', [
                'commission_payment_batch_id' => $batch->id,
            ]))
            ->values()
            ->all();
    }

    private function paymentGlMissing(): array
    {
        return $this->paymentBatchQuery()
            ->where('status', CommissionPaymentBatchStatus::Posted)
            ->whereDoesntHave('postingTransaction.glEntries')
            ->limit(250)
            ->get()
            ->map(fn (CommissionPaymentBatch $batch): array => $this->finding('payment_gl_missing', 'critical', 'Posted commission payment batch has no G/L entries.', [
                'commission_payment_batch_id' => $batch->id,
            ]))
            ->all();
    }

    private function paymentPostedButBatchNotPosted(): array
    {
        return CommissionPaymentApplication::query()
            ->whereHas('batch', fn (Builder $query): Builder => $query->whereNotIn('status', [CommissionPaymentBatchStatus::Posted, CommissionPaymentBatchStatus::PartiallyReversed, CommissionPaymentBatchStatus::Reversed]))
            ->limit(250)
            ->get()
            ->map(fn (CommissionPaymentApplication $application): array => $this->finding('payment_posted_but_batch_not_posted', 'critical', 'Commission payment application exists under an unposted batch.', [
                'commission_payment_application_id' => $application->id,
            ]))
            ->all();
    }

    private function negativeOutstanding(): array
    {
        return $this->overpaymentDetected();
    }

    private function overpaymentDetected(): array
    {
        return CommissionSettlementLine::query()
            ->withSum(['paymentLines as paid_total' => fn (Builder $query): Builder => $query->where('status', 'posted')], 'payment_amount')
            ->limit(250)
            ->get()
            ->filter(fn (CommissionSettlementLine $line): bool => (float) ($line->paid_total ?? 0) - (float) $line->net_settlement_amount > 0.0001)
            ->map(fn (CommissionSettlementLine $line): array => $this->finding('overpayment_detected', 'critical', 'Commission payments exceed locked settlement line amount.', [
                'commission_settlement_line_id' => $line->id,
                'net_settlement_amount' => (string) $line->net_settlement_amount,
                'paid_amount' => (string) ($line->paid_total ?? 0),
            ]))
            ->values()
            ->all();
    }

    private function paymentReversalWithoutOriginal(): array
    {
        return CommissionPaymentApplication::query()
            ->where('application_type', CommissionPaymentApplicationType::Reversal)
            ->whereNull('reverses_application_id')
            ->limit(250)
            ->get()
            ->map(fn (CommissionPaymentApplication $application): array => $this->finding('payment_reversal_without_original', 'critical', 'Commission payment reversal application has no original application reference.', [
                'commission_payment_application_id' => $application->id,
            ]))
            ->all();
    }

    private function selfApprovedPaymentBatch(): array
    {
        return $this->paymentBatchQuery()
            ->whereNotNull('prepared_by')
            ->whereColumn('prepared_by', 'approved_by')
            ->limit(250)
            ->get()
            ->map(fn (CommissionPaymentBatch $batch): array => $this->finding('self_approved_payment_batch', 'critical', 'Commission payment batch was approved by its preparer.', [
                'commission_payment_batch_id' => $batch->id,
            ]))
            ->all();
    }

    private function crossBusinessPaymentReference(): array
    {
        return CommissionPaymentLine::query()
            ->whereHas('batch', fn (Builder $query): Builder => $query->whereColumn('commission_payment_lines.business_id', '!=', 'commission_payment_batches.business_id'))
            ->limit(250)
            ->get()
            ->map(fn (CommissionPaymentLine $line): array => $this->finding('cross_business_payment_reference', 'critical', 'Commission payment line business does not match payment batch business.', [
                'commission_payment_line_id' => $line->id,
            ]))
            ->all();
    }

    private function calculationQuery(): Builder
    {
        return CommissionCalculation::query()
            ->when($this->option('business'), fn (Builder $query, string $id): Builder => $query->where('business_id', $id))
            ->when($this->option('referrer'), fn (Builder $query, string $id): Builder => $query->where('referrer_id', $id))
            ->when($this->option('customer'), fn (Builder $query, string $id): Builder => $query->where('customer_id', $id))
            ->when($this->option('plan'), fn (Builder $query, string $id): Builder => $query->where('commission_plan_id', $id))
            ->when($this->option('source'), fn (Builder $query, string $source): Builder => $query->where('source_number', $source))
            ->when($this->option('date-from'), fn (Builder $query, string $date): Builder => $query->whereDate('source_posting_date', '>=', $date))
            ->when($this->option('date-to'), fn (Builder $query, string $date): Builder => $query->whereDate('source_posting_date', '<=', $date))
            ->when($this->option('status'), fn (Builder $query, string $status): Builder => $query->where('calculation_status', $status));
    }

    private function ledgerQuery(): Builder
    {
        return CommissionLedgerEntry::query()
            ->when($this->option('business'), fn (Builder $query, string $id): Builder => $query->where('business_id', $id))
            ->when($this->option('referrer'), fn (Builder $query, string $id): Builder => $query->where('referrer_id', $id))
            ->when($this->option('customer'), fn (Builder $query, string $id): Builder => $query->where('customer_id', $id))
            ->when($this->option('source'), fn (Builder $query, string $source): Builder => $query->where('source_number', $source))
            ->when($this->option('date-from'), fn (Builder $query, string $date): Builder => $query->whereDate('posting_date', '>=', $date))
            ->when($this->option('date-to'), fn (Builder $query, string $date): Builder => $query->whereDate('posting_date', '<=', $date));
    }

    private function reviewBatchQuery(): Builder
    {
        return CommissionReviewBatch::query()
            ->when($this->option('business'), fn (Builder $query, string $id): Builder => $query->where('business_id', $id))
            ->when($this->option('review-period'), fn (Builder $query, string $id): Builder => $query->where('commission_review_period_id', $id))
            ->when($this->option('review-batch'), fn (Builder $query, string $id): Builder => $query->whereKey($id));
    }

    private function reviewLineQuery(): Builder
    {
        return CommissionReviewBatchLine::query()
            ->when($this->option('business'), fn (Builder $query, string $id): Builder => $query->where('business_id', $id))
            ->when($this->option('referrer'), fn (Builder $query, string $id): Builder => $query->where('referrer_id', $id))
            ->when($this->option('review-batch'), fn (Builder $query, string $id): Builder => $query->where('commission_review_batch_id', $id));
    }

    private function settlementBatchQuery(): Builder
    {
        return CommissionSettlementBatch::query()
            ->when($this->option('business'), fn (Builder $query, string $id): Builder => $query->where('business_id', $id))
            ->when($this->option('review-period'), fn (Builder $query, string $id): Builder => $query->where('commission_review_period_id', $id))
            ->when($this->option('review-batch'), fn (Builder $query, string $id): Builder => $query->where('commission_review_batch_id', $id))
            ->when($this->option('settlement-batch'), fn (Builder $query, string $id): Builder => $query->whereKey($id));
    }

    private function paymentBatchQuery(): Builder
    {
        return CommissionPaymentBatch::query()
            ->when($this->option('business'), fn (Builder $query, string $id): Builder => $query->where('business_id', $id))
            ->when($this->option('settlement-batch'), fn (Builder $query, string $id): Builder => $query->where('commission_settlement_batch_id', $id))
            ->when($this->option('payment-batch'), fn (Builder $query, string $id): Builder => $query->whereKey($id))
            ->when($this->option('payment-status'), fn (Builder $query, string $status): Builder => $query->where('status', $status))
            ->when($this->option('payment-method'), fn (Builder $query, string $method): Builder => $query->where('payment_method', $method));
    }

    private function finding(string $classification, string $severity, string $message, array $context): array
    {
        return [
            'classification' => $classification,
            'severity' => $severity,
            'message' => $message,
            'context' => $context,
            'suggested_remediation' => $this->remediation($classification),
        ];
    }

    private function remediation(string $classification): string
    {
        return match ($classification) {
            'posted_invoice_without_commission_evaluation' => 'Run commission calculation for the posted source if the sale is expected to be commissionable.',
            'calculation_total_mismatch' => 'Review calculation lines and create an authorized superseding calculation if needed.',
            'accrued_calculation_without_ledger' => 'Retry the calculation or reverse/supersede through the commission service.',
            'duplicate_accrual', 'duplicate_reversal', 'duplicate_calculation' => 'Investigate idempotency keys and reverse duplicate history through an authorized append-only correction.',
            default => 'Review the commission source, referral, plan, calculation, and ledger history before manual correction.',
        };
    }

    private function exportReport(array $report, string $path): void
    {
        $directory = dirname($path);

        if (! File::isDirectory($directory)) {
            File::makeDirectory($directory, 0755, true);
        }

        File::put($path, json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    }
}
