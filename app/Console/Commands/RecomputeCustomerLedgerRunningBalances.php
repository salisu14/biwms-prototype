<?php

namespace App\Console\Commands;

use App\Models\CustomerLedgerEntry;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

#[Signature('customer-ledger:recompute-running-balances
    {--apply : Persist changes. Without this flag, the command runs as dry-run}
    {--customer=* : Optional customer IDs to process}
    {--all : Recompute all targeted customers, not only those with mismatches}')]
#[Description('Recompute customer ledger running balances in strict posting order')]
class RecomputeCustomerLedgerRunningBalances extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $apply = (bool) $this->option('apply');
        $forceAll = (bool) $this->option('all');
        $customerIds = array_map('intval', array_filter((array) $this->option('customer')));

        $targetCustomerIds = CustomerLedgerEntry::query()
            ->when($customerIds !== [], fn ($query) => $query->whereIn('customer_id', $customerIds))
            ->distinct()
            ->orderBy('customer_id')
            ->pluck('customer_id');

        if ($targetCustomerIds->isEmpty()) {
            $this->warn('No customer ledger entries matched the criteria.');

            return self::SUCCESS;
        }

        $this->info(($apply ? 'Apply' : 'Dry-run')." mode for {$targetCustomerIds->count()} customer(s).");

        $affectedCustomerIds = collect();
        $mismatchCountByCustomer = [];

        foreach ($targetCustomerIds as $customerId) {
            [$hasMismatch, $mismatchCount] = $this->customerHasRunningBalanceMismatch((int) $customerId);
            if ($hasMismatch) {
                $affectedCustomerIds->push((int) $customerId);
                $mismatchCountByCustomer[(int) $customerId] = $mismatchCount;
            }
        }

        $customersToRecompute = $forceAll
            ? $targetCustomerIds->map(fn ($id) => (int) $id)->values()
            : $affectedCustomerIds->values();

        if ($customersToRecompute->isEmpty()) {
            $this->info('No running-balance mismatches found. Nothing to recompute.');

            return self::SUCCESS;
        }

        $this->line('Detected affected customers: '.implode(', ', $customersToRecompute->all()));

        $totalRowsUpdated = 0;
        foreach ($customersToRecompute as $customerId) {
            $changedRows = $this->recomputeCustomerRunningBalances($customerId, $apply);
            $totalRowsUpdated += $changedRows;
            $mismatchCount = $mismatchCountByCustomer[$customerId] ?? 0;
            $this->line(sprintf(
                '%s customer %d: mismatches=%d, rows=%d',
                $apply ? 'UPDATED' : 'PLAN',
                $customerId,
                $mismatchCount,
                $changedRows
            ));
        }

        $this->newLine();
        $this->info(sprintf(
            'Target customers: %d, Affected customers: %d, %s rows: %d',
            $targetCustomerIds->count(),
            $customersToRecompute->count(),
            $apply ? 'Updated' : 'Planned',
            $totalRowsUpdated
        ));

        if (! $apply) {
            $this->comment('Dry-run complete. Re-run with --apply to persist changes.');
        }

        return self::SUCCESS;
    }

    /**
     * @return array{bool,int}
     */
    private function customerHasRunningBalanceMismatch(int $customerId): array
    {
        $entries = $this->orderedCustomerEntries($customerId);

        $expectedBalance = 0.0;
        $mismatchCount = 0;
        foreach ($entries as $entry) {
            $expectedBalance = round($expectedBalance + (float) $entry->amount, 4);
            $storedBalance = round((float) $entry->running_balance, 4);

            if (abs($storedBalance - $expectedBalance) > 0.0001) {
                $mismatchCount++;
            }
        }

        return [$mismatchCount > 0, $mismatchCount];
    }

    private function recomputeCustomerRunningBalances(int $customerId, bool $apply): int
    {
        $entries = $this->orderedCustomerEntries($customerId);

        $expectedBalance = 0.0;
        $updates = [];

        foreach ($entries as $entry) {
            $expectedBalance = round($expectedBalance + (float) $entry->amount, 4);
            $storedBalance = round((float) $entry->running_balance, 4);

            if (abs($storedBalance - $expectedBalance) <= 0.0001) {
                continue;
            }

            $updates[] = [
                'id' => (int) $entry->id,
                'running_balance' => $expectedBalance,
            ];
        }

        if (! $apply || $updates === []) {
            return count($updates);
        }

        DB::transaction(function () use ($updates): void {
            foreach ($updates as $update) {
                CustomerLedgerEntry::query()
                    ->where('id', $update['id'])
                    ->update(['running_balance' => $update['running_balance']]);
            }
        });

        return count($updates);
    }

    private function orderedCustomerEntries(int $customerId): Collection
    {
        return CustomerLedgerEntry::query()
            ->where('customer_id', $customerId)
            ->orderBy('posting_date')
            ->orderBy('entry_number')
            ->orderBy('id')
            ->get(['id', 'amount', 'running_balance']);
    }
}
