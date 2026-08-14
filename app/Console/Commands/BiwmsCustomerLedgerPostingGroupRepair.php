<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\CustomerLedgerEntry;
use App\Models\Payment;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

#[Signature('biwms:customer-ledger-posting-groups-repair {--dry-run : Report deterministic repairs without applying them} {--apply : Apply deterministic posting-group metadata repairs}')]
#[Description('Report or repair payment-created customer ledger entries missing posting-group metadata.')]
class BiwmsCustomerLedgerPostingGroupRepair extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $apply = (bool) $this->option('apply');

        if (! $apply && ! $this->option('dry-run')) {
            $this->line('No mode supplied; defaulting to --dry-run.');
        }

        $candidates = $this->candidates();

        $this->info('BIWMS Customer Ledger Posting Group Repair');
        $this->line('Mode: '.($apply ? 'apply' : 'dry-run'));
        $this->line('Deterministic candidate rows: '.$candidates->count());

        foreach ($candidates as $entry) {
            $this->line(sprintf(
                ' - entry=%s document=%s customer=%s customer_group: %s -> %s business_group: %s -> %s',
                $entry->id,
                $entry->document_number,
                $entry->customer_id,
                $entry->customer_posting_group_id ?? 'null',
                $entry->expected_customer_posting_group_id,
                $entry->general_business_posting_group_id ?? 'null',
                $entry->expected_general_business_posting_group_id,
            ));
        }

        if (! $apply || $candidates->isEmpty()) {
            $this->line('No data was modified.');

            return self::SUCCESS;
        }

        $updated = DB::transaction(function () use ($candidates): int {
            $count = 0;

            foreach ($candidates as $entry) {
                CustomerLedgerEntry::query()
                    ->whereKey($entry->id)
                    ->where('source_type', Payment::class)
                    ->where('reversed', false)
                    ->update([
                        'customer_posting_group_id' => $entry->expected_customer_posting_group_id,
                        'general_business_posting_group_id' => $entry->expected_general_business_posting_group_id,
                    ]);

                $count++;
            }

            return $count;
        });

        $this->info("Updated {$updated} customer ledger entr".($updated === 1 ? 'y' : 'ies').'.');

        return self::SUCCESS;
    }

    /**
     * @return Collection<int, CustomerLedgerEntry>
     */
    private function candidates(): Collection
    {
        return CustomerLedgerEntry::query()
            ->join('customers', 'customer_ledger_entries.customer_id', '=', 'customers.id')
            ->where('customer_ledger_entries.reversed', false)
            ->where('customer_ledger_entries.source_type', Payment::class)
            ->whereNotNull('customers.customer_posting_group_id')
            ->whereNotNull('customers.general_business_posting_group_id')
            ->where(function ($query): void {
                $query
                    ->whereNull('customer_ledger_entries.customer_posting_group_id')
                    ->orWhereNull('customer_ledger_entries.general_business_posting_group_id')
                    ->orWhereColumn('customer_ledger_entries.customer_posting_group_id', '!=', 'customers.customer_posting_group_id')
                    ->orWhereColumn('customer_ledger_entries.general_business_posting_group_id', '!=', 'customers.general_business_posting_group_id');
            })
            ->orderBy('customer_ledger_entries.id')
            ->get([
                'customer_ledger_entries.id',
                'customer_ledger_entries.customer_id',
                'customer_ledger_entries.document_number',
                'customer_ledger_entries.customer_posting_group_id',
                'customer_ledger_entries.general_business_posting_group_id',
                'customers.customer_posting_group_id as expected_customer_posting_group_id',
                'customers.general_business_posting_group_id as expected_general_business_posting_group_id',
            ]);
    }
}
