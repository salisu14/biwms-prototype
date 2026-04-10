<?php

namespace App\Console\Commands\Accounting;

use App\Models\ChartOfAccount;
use App\Models\GlEntry;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class SyncAccountBalances extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'accounting:sync-balances';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Recalculate all Chart of Account balances from G/L entries';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info("Recalculating Chart of Account balances...");

        DB::transaction(function () {
            // Reset all balances to 0
            ChartOfAccount::query()->update(['balance' => 0]);

            // Sum up all GL entries per account
            $balances = GlEntry::select('chart_of_account_id', DB::raw('SUM(amount) as total_balance'))
                ->groupBy('chart_of_account_id')
                ->get();

            $bar = $this->output->createProgressBar($balances->count());
            $bar->start();

            foreach ($balances as $ba) {
                if ($ba->chart_of_account_id) {
                    ChartOfAccount::where('id', $ba->chart_of_account_id)
                        ->update(['balance' => $ba->total_balance]);
                }
                $bar->advance();
            }

            $bar->finish();
            $this->newLine();
        });

        $this->info("All account balances have been synchronized with the ledger.");
    }
}
