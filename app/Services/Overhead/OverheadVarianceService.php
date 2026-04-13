<?php

declare(strict_types=1);

namespace App\Services\Overhead;

use App\Models\ActualOverheadCost;
use App\Models\CapacityLedgerEntry;
use App\Models\ChartOfAccount;
use App\Models\GeneralJournalBatch;
use App\Models\GeneralJournalLine;
use App\Models\GeneralJournalTemplate;
use App\Models\Manufacturing\WorkCenter;
use App\Services\NumberSeriesService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class OverheadVarianceService
{
    public function __construct(
        private readonly NumberSeriesService $numberSeriesService
    ) {}

    /**
     * Calculate monthly variance for a work center
     */
    public function calculateMonthlyVariance(
        WorkCenter $workCenter,
        \DateTime $period
    ): VarianceResult {
        $startOfMonth = Carbon::parse($period)->startOfMonth();
        $endOfMonth = Carbon::parse($period)->endOfMonth();

        // Get absorbed overhead from capacity ledger entries
        $absorbed = CapacityLedgerEntry::where('work_center_id', $workCenter->id)
            ->whereBetween('posting_date', [$startOfMonth, $endOfMonth])
            ->sum('overhead_cost');

        // Get actual overhead costs incurred
        $actual = ActualOverheadCost::getTotalForWorkCenterAndPeriod(
            $workCenter->id,
            $startOfMonth
        );

        return VarianceResult::calculate(
            absorbed: (float) $absorbed,
            actual: (float) $actual
        );
    }

    /**
     * Calculate detailed variance with efficiency and spending breakdown
     */
    public function calculateDetailedVariance(
        WorkCenter $workCenter,
        \DateTime $period,
        float $standardHoursAllowed, // Based on standard cost routing
        float $standardOverheadRate
    ): VarianceResult {
        $startOfMonth = Carbon::parse($period)->startOfMonth();
        $endOfMonth = Carbon::parse($period)->endOfMonth();

        // Actual data from capacity ledger
        $actualHours = CapacityLedgerEntry::where('work_center_id', $workCenter->id)
            ->whereBetween('posting_date', [$startOfMonth, $endOfMonth])
            ->sum('run_time');

        $absorbedOverhead = CapacityLedgerEntry::where('work_center_id', $workCenter->id)
            ->whereBetween('posting_date', [$startOfMonth, $endOfMonth])
            ->sum('overhead_cost');

        // Actual costs
        $actualOverhead = ActualOverheadCost::getTotalForWorkCenterAndPeriod(
            $workCenter->id,
            $startOfMonth
        );

        // Calculate actual rate
        $actualRate = $actualHours > 0 ? $actualOverhead / $actualHours : 0;

        return VarianceResult::calculateDetailed(
            standardHoursAllowed: $standardHoursAllowed,
            actualHours: (float) $actualHours,
            standardRate: $standardOverheadRate,
            actualRate: $actualRate,
            actualOverhead: (float) $actualOverhead
        );
    }

    /**
     * Post variance to General Ledger
     */
    public function postVariance(
        VarianceResult $variance,
        WorkCenter $workCenter,
        \DateTime $period,
        ?string $description = null
    ): GeneralJournalBatch {
        if ($variance->variance == 0) {
            throw new \RuntimeException('No variance to post');
        }

        return DB::transaction(function () use ($variance, $workCenter, $period, $description) {
            // Create journal batch
            $batch = GeneralJournalBatch::create([
                'template_id' => $this->getVarianceTemplateId(),
                'name' => 'OH-VAR-'.$workCenter->code.'-'.$period->format('Ym'),
                'description' => $description ?? "Overhead Variance {$workCenter->name} {$period->format('M Y')}",
                'assigned_user_id' => Auth::id(),
                'status' => 'released', // Auto-release for system posting
            ]);

            // Determine accounts
            $varianceAccount = $workCenter->overhead_variance_account_id
                ?? $this->getDefaultVarianceAccount();

            $absorptionAccount = $workCenter->overhead_absorption_account_id
                ?? $this->getDefaultAbsorptionAccount();

            if ($variance->isUnderAbsorbed) {
                // Under-absorbed: Need more expense
                // Debit Variance Account (Expense), Credit Absorption Account (Clearing)
                $this->createVarianceLine($batch, 10000, $varianceAccount, $absorptionAccount, $variance->variance, $period, 'Under-absorbed overhead');
            } else {
                // Over-absorbed: Reduce expense
                // Debit Absorption Account, Credit Variance Account
                $this->createVarianceLine($batch, 10000, $absorptionAccount, $varianceAccount, abs($variance->variance), $period, 'Over-absorbed overhead');
            }

            // Post detailed variances if available
            if ($variance->spendingVariance !== null && $variance->spendingVariance != 0) {
                $this->createVarianceLine($batch, 20000, $varianceAccount, $absorptionAccount, $variance->spendingVariance, $period, 'Spending variance');
            }

            if ($variance->efficiencyVariance !== null && $variance->efficiencyVariance != 0) {
                $this->createVarianceLine($batch, 30000, $varianceAccount, $absorptionAccount, $variance->efficiencyVariance, $period, 'Efficiency variance');
            }

            // Mark actual costs as variance posted
            ActualOverheadCost::where('work_center_id', $workCenter->id)
                ->where('period', $period->format('Y-m-01'))
                ->whereNull('variance_posted_at')
                ->get()
                ->each(fn ($cost) => $cost->markVariancePosted($batch->id));

            return $batch;
        });
    }

    /**
     * Process variances for all work centers in a period
     */
    public function processPeriodVariances(\DateTime $period): array
    {
        $results = [];

        $workCenters = WorkCenter::where('overhead_rate', '>', 0)
            ->where('is_active', true)
            ->get();

        foreach ($workCenters as $workCenter) {
            $variance = $this->calculateMonthlyVariance($workCenter, $period);

            if ($variance->isSignificant(5.0)) { // 5% threshold
                $batch = $this->postVariance($variance, $workCenter, $period);
                $results[] = [
                    'work_center' => $workCenter->code,
                    'variance' => $variance->toArray(),
                    'batch_id' => $batch->id,
                    'posted' => true,
                ];
            } else {
                $results[] = [
                    'work_center' => $workCenter->code,
                    'variance' => $variance->toArray(),
                    'posted' => false,
                    'reason' => 'Below threshold',
                ];
            }
        }

        return $results;
    }

    /**
     * Get variance summary report
     */
    public function getVarianceReport(\DateTime $from, \DateTime $to): array
    {
        $actualCosts = ActualOverheadCost::whereBetween('period', [
            $from->format('Y-m-01'),
            $to->format('Y-m-01'),
        ])
            ->select('work_center_id')
            ->selectRaw('SUM(amount) as total_actual')
            ->selectRaw('SUM(allocated_amount) as total_allocated')
            ->groupBy('work_center_id')
            ->get()
            ->keyBy('work_center_id');

        $absorbedCosts = CapacityLedgerEntry::whereBetween('posting_date', [$from, $to])
            ->select('work_center_id')
            ->selectRaw('SUM(overhead_cost) as total_absorbed')
            ->groupBy('work_center_id')
            ->get()
            ->keyBy('work_center_id');

        $report = [];
        $workCenters = WorkCenter::whereIn('id', $actualCosts->keys()->merge($absorbedCosts->keys())->unique())->get();

        foreach ($workCenters as $wc) {
            $actual = $actualCosts->get($wc->id)?->total_actual ?? 0;
            $allocated = $actualCosts->get($wc->id)?->total_allocated ?? 0;
            $absorbed = $absorbedCosts->get($wc->id)?->total_absorbed ?? 0;

            $report[] = [
                'work_center_code' => $wc->code,
                'work_center_name' => $wc->name,
                'actual_overhead' => (float) $actual,
                'allocated_to_production' => (float) $allocated,
                'absorbed_from_production' => (float) $absorbed,
                'unallocated_remaining' => (float) $actual - (float) $allocated,
                'variance' => (float) $actual - (float) $absorbed,
                'absorption_rate' => $actual > 0 ? ((float) $absorbed / (float) $actual) * 100 : 0,
            ];
        }

        return $report;
    }

    private function createVarianceLine(
        GeneralJournalBatch $batch,
        int $lineNo,
        int $debitAccountId,
        int $creditAccountId,
        float $amount,
        \DateTime $period,
        string $description
    ): void {
        GeneralJournalLine::create([
            'batch_id' => $batch->id,
            'line_no' => $lineNo,
            'posting_date' => $period,
            'account_id' => $debitAccountId,
            'debit_amount' => $amount,
            'credit_amount' => 0,
            'description' => $description,
            'source_code' => 'OHVAR',
            'created_by' => Auth::id(),
        ]);

        GeneralJournalLine::create([
            'batch_id' => $batch->id,
            'line_no' => $lineNo + 1,
            'posting_date' => $period,
            'account_id' => $creditAccountId,
            'debit_amount' => 0,
            'credit_amount' => $amount,
            'description' => $description.' (Offset)',
            'source_code' => 'OHVAR',
            'created_by' => Auth::id(),
        ]);
    }

    private function getVarianceTemplateId(): int
    {
        // Return ID of General Journal Template for variances
        return GeneralJournalTemplate::where('name', 'GENERAL')->first()?->id ?? 1;
    }

    private function getDefaultVarianceAccount(): int
    {
        return ChartOfAccount::where('account_code', '5990')->first()?->id ?? 1; // Overhead Variance
    }

    private function getDefaultAbsorptionAccount(): int
    {
        return ChartOfAccount::where('account_code', '5995')->first()?->id ?? 1; // Overhead Absorption
    }
}
