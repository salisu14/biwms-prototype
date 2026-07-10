<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\EmployeeLeaveLedgerEntry;
use App\Models\LeaveRequest;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

#[Signature('biwms:leave-reconcile {--details : Show detailed findings} {--export= : Export findings to JSON path}')]
#[Description('Report leave management reconciliation findings without mutating data')]
class BiwmsLeaveReconcile extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $findings = collect([
            ...$this->approvedWithoutLedger(),
            ...$this->duplicatePosting(),
            ...$this->quantityMismatch(),
            ...$this->negativeBalances(),
            ...$this->overlappingApprovedLeave(),
        ]);

        $this->info('BIWMS Leave Reconciliation');
        $this->line('Findings: '.$findings->count());

        $summary = $findings->groupBy('classification')->map->count();
        foreach ($summary as $classification => $count) {
            $this->line(" - {$classification}: {$count}");
        }

        if ($this->option('details')) {
            $findings->each(function (array $finding): void {
                $this->line(sprintf(
                    '[%s] %s: %s',
                    $finding['severity'],
                    $finding['classification'],
                    $finding['message']
                ));
            });
        }

        if ($export = $this->option('export')) {
            Storage::disk('local')->put(
                str_replace('storage/app/', '', (string) $export),
                json_encode(['findings' => $findings->values()], JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR)
            );
            $this->info('Exported leave reconciliation report to '.$export);
        }

        return self::SUCCESS;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function approvedWithoutLedger(): array
    {
        return LeaveRequest::query()
            ->whereIn('status', [LeaveRequest::STATUS_APPROVED, LeaveRequest::STATUS_POSTED, LeaveRequest::STATUS_COMPLETED])
            ->whereDoesntHave('ledgerEntries', fn ($query) => $query->where('entry_type', EmployeeLeaveLedgerEntry::TYPE_APPROVED_LEAVE))
            ->get()
            ->map(fn (LeaveRequest $request): array => $this->finding(
                'approved_without_ledger',
                'critical',
                "Leave request {$request->request_number} is approved/posted without an approved-leave ledger entry.",
                $request
            ))
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function duplicatePosting(): array
    {
        return EmployeeLeaveLedgerEntry::query()
            ->selectRaw('leave_request_id, COUNT(*) as postings')
            ->whereNotNull('leave_request_id')
            ->where('entry_type', EmployeeLeaveLedgerEntry::TYPE_APPROVED_LEAVE)
            ->groupBy('leave_request_id')
            ->havingRaw('COUNT(*) > 1')
            ->get()
            ->map(fn ($row): array => [
                'classification' => 'duplicate_ledger_posting',
                'severity' => 'critical',
                'message' => "Leave request {$row->leave_request_id} has {$row->postings} approved-leave ledger entries.",
                'suggested_remediation' => 'Review the duplicate posting and reverse the duplicate through an audited adjustment.',
            ])
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function quantityMismatch(): array
    {
        return LeaveRequest::query()
            ->whereHas('ledgerEntries', fn ($query) => $query->where('entry_type', EmployeeLeaveLedgerEntry::TYPE_APPROVED_LEAVE))
            ->with('ledgerEntries')
            ->get()
            ->filter(function (LeaveRequest $request): bool {
                $posted = abs((float) $request->ledgerEntries->where('entry_type', EmployeeLeaveLedgerEntry::TYPE_APPROVED_LEAVE)->sum('quantity'));

                return round($posted, 2) !== round((float) ($request->approved_quantity ?? $request->requested_quantity), 2);
            })
            ->map(fn (LeaveRequest $request): array => $this->finding(
                'ledger_quantity_mismatch',
                'critical',
                "Leave request {$request->request_number} ledger quantity differs from approved quantity.",
                $request
            ))
            ->values()
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function negativeBalances(): array
    {
        return EmployeeLeaveLedgerEntry::query()
            ->with('leaveType')
            ->selectRaw('employee_id, leave_type_id, leave_year, SUM(quantity) as balance')
            ->groupBy('employee_id', 'leave_type_id', 'leave_year')
            ->get()
            ->filter(fn ($row): bool => (float) $row->balance < 0 && $row->leaveType?->allow_negative_balance !== true)
            ->map(fn ($row): array => [
                'classification' => 'negative_balance',
                'severity' => 'critical',
                'message' => "Employee {$row->employee_id} has negative leave balance {$row->balance} for leave type {$row->leave_type_id} in {$row->leave_year}.",
                'suggested_remediation' => 'Review entitlements, reversals, and approved leave postings; post an audited adjustment if needed.',
            ])
            ->values()
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function overlappingApprovedLeave(): array
    {
        $findings = [];
        $requests = LeaveRequest::query()
            ->whereIn('status', [LeaveRequest::STATUS_APPROVED, LeaveRequest::STATUS_POSTED, LeaveRequest::STATUS_COMPLETED])
            ->orderBy('employee_id')
            ->orderBy('start_date')
            ->get();

        foreach ($requests as $request) {
            $overlap = $requests
                ->where('employee_id', $request->employee_id)
                ->where('id', '!=', $request->id)
                ->first(fn (LeaveRequest $other): bool => $other->start_date <= $request->end_date && $other->end_date >= $request->start_date);

            if ($overlap) {
                $findings[] = $this->finding(
                    'overlapping_approved_leave',
                    'warning',
                    "Leave request {$request->request_number} overlaps {$overlap->request_number}.",
                    $request
                );
            }
        }

        return $findings;
    }

    /**
     * @return array<string, mixed>
     */
    private function finding(string $classification, string $severity, string $message, LeaveRequest $request): array
    {
        return [
            'classification' => $classification,
            'severity' => $severity,
            'message' => $message,
            'leave_request_id' => $request->id,
            'request_number' => $request->request_number,
            'suggested_remediation' => 'Review the leave request and ledger trail; correct through an audited reversal or adjustment only.',
        ];
    }
}
