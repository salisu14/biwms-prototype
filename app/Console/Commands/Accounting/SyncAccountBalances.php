<?php

declare(strict_types=1);

namespace App\Console\Commands\Accounting;

use App\Services\Accounting\GlAccountBalanceService;
use Illuminate\Console\Command;

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
    public function handle(GlAccountBalanceService $balanceService): int
    {
        $this->info('Recalculating Chart of Account balances...');

        $count = $balanceService->syncAll();

        $this->info("{$count} account balances have been synchronized with the ledger.");

        return self::SUCCESS;
    }
}
