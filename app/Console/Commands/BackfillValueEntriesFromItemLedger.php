<?php

namespace App\Console\Commands;

use App\Models\ItemLedgerEntry;
use App\Models\ValueEntry;
use App\Services\Inventory\ValueEntryService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('app:backfill-value-entries-from-item-ledger {--dry-run : Show what would be backfilled without writing data}')]
#[Description('Backfill missing Value Entries from existing Item Ledger Entries')]
class BackfillValueEntriesFromItemLedger extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $processed = 0;
        $created = 0;

        $this->info('Scanning Item Ledger Entries for missing Value Entries...');

        ItemLedgerEntry::query()
            ->orderBy('id')
            ->chunkById(200, function ($entries) use (&$processed, &$created, $dryRun): void {
                foreach ($entries as $entry) {
                    $processed++;

                    $exists = ValueEntry::query()
                        ->where('item_ledger_entry_no', $entry->entry_number)
                        ->exists();

                    if ($exists) {
                        continue;
                    }

                    $created++;

                    if (! $dryRun) {
                        app(ValueEntryService::class)->ensureForItemLedgerEntry($entry);
                    }
                }
            });

        $this->newLine();
        $this->line("Processed: {$processed}");
        $this->line('Missing Value Entries found: '.$created);
        $this->line($dryRun ? 'Mode: DRY RUN (no records were created).' : 'Mode: APPLY (missing records were created).');

        return self::SUCCESS;
    }
}
