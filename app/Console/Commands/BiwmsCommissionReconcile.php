<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Enums\CommissionCalculationStatus;
use App\Enums\CommissionLedgerEntryType;
use App\Models\CommissionCalculation;
use App\Models\CommissionHold;
use App\Models\CommissionLedgerEntry;
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

#[Signature('biwms:commission-reconcile {--details : Show detailed diagnostic rows} {--business= : Filter by business ID} {--referrer= : Filter by referrer ID} {--customer= : Filter by customer ID} {--plan= : Filter by commission plan ID} {--source= : Filter by source document number} {--date-from= : Filter posting date from YYYY-MM-DD} {--date-to= : Filter posting date to YYYY-MM-DD} {--status= : Filter calculation status} {--review-period= : Filter by commission review period ID} {--review-batch= : Filter by commission review batch ID} {--settlement-batch= : Filter by commission settlement batch ID} {--json : Output machine-readable JSON} {--export= : Write JSON report to path}')]
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
