<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('inventory:backfill-opening-balances
    {--apply : Persist changes. Without this flag, the command runs as dry-run}
    {--item=* : Optional item codes to process}
    {--posting-date= : Deprecated. Opening repair uses the current posting date}
    {--force : Deprecated. Ambiguous findings are never auto-repaired}')]
#[Description('Compatibility wrapper for the controlled BIWMS opening inventory repair command')]
class BackfillItemOpeningBalances extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->warn('inventory:backfill-opening-balances is deprecated.');
        $this->line('Delegating to biwms:inventory-opening-repair so opening stock creates documents, item ledger entries, and value entries.');

        if ($this->option('posting-date')) {
            $this->warn('The --posting-date option is ignored by the controlled repair wrapper.');
        }

        if ($this->option('force')) {
            $this->warn('The --force option is ignored. Ambiguous findings are never auto-repaired.');
        }

        $itemCodes = array_values(array_filter((array) $this->option('item')));

        if ($itemCodes === []) {
            return $this->call('biwms:inventory-opening-repair', [
                '--details' => true,
                '--apply' => (bool) $this->option('apply'),
            ]);
        }

        $exitCode = self::SUCCESS;

        foreach ($itemCodes as $itemCode) {
            $result = $this->call('biwms:inventory-opening-repair', [
                '--details' => true,
                '--apply' => (bool) $this->option('apply'),
                '--item' => $itemCode,
            ]);

            if ($result !== self::SUCCESS) {
                $exitCode = $result;
            }
        }

        return $exitCode;
    }
}
