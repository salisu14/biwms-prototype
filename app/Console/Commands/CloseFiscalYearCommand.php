<?php

namespace App\Console\Commands;

use App\Services\FiscalYearCloseService;
use Illuminate\Console\Command;

class CloseFiscalYearCommand extends Command
{
    protected $signature = 'fiscal:close-income-statement {year : Fiscal year (e.g. 2026)} {--user=1 : User ID posting the close entries}';

    protected $description = 'Close income statement accounts into retained earnings for a fiscal year';

    public function handle(FiscalYearCloseService $service): int
    {
        $year = (int) $this->argument('year');
        $userId = (int) $this->option('user');

        try {
            $result = $service->closeIncomeStatement($year, $userId);
            $this->info("Close complete for {$year}. Entries posted: {$result['entries_posted']}. Net income moved: {$result['net_income']}");

            return self::SUCCESS;
        } catch (\Throwable $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }
    }
}
