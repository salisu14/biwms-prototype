<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Enums\CommissionCalculationStatus;
use App\Enums\CommissionLedgerEntryType;
use App\Models\CommissionCalculation;
use App\Models\CommissionLedgerEntry;
use App\Models\PostedSalesInvoice;
use App\Support\DecimalMath;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\File;

#[Signature('biwms:commission-reconcile {--details : Show detailed diagnostic rows} {--business= : Filter by business ID} {--referrer= : Filter by referrer ID} {--customer= : Filter by customer ID} {--plan= : Filter by commission plan ID} {--source= : Filter by source document number} {--date-from= : Filter posting date from YYYY-MM-DD} {--date-to= : Filter posting date to YYYY-MM-DD} {--status= : Filter calculation status} {--json : Output machine-readable JSON} {--export= : Write JSON report to path}')]
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
