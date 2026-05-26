<?php

namespace App\Console\Commands;

use App\Services\FiscalWindowService;
use Illuminate\Console\Command;

class ReopenFiscalPostingWindowCommand extends Command
{
    protected $signature = 'fiscal:reopen-window
        {from : Allow posting from (YYYY-MM-DD)}
        {to : Allow posting to (YYYY-MM-DD)}
        {reason : Reason for reopening}
        {--user=1 : User ID requesting reopen}';

    protected $description = 'Reopen/adjust fiscal posting window with audit log';

    public function handle(FiscalWindowService $service): int
    {
        try {
            $service->reopenPostingWindow(
                fromDate: (string) $this->argument('from'),
                toDate: (string) $this->argument('to'),
                reason: (string) $this->argument('reason'),
                userId: (int) $this->option('user')
            );

            $this->info('Posting window updated and audited successfully.');

            return self::SUCCESS;
        } catch (\Throwable $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }
    }
}
