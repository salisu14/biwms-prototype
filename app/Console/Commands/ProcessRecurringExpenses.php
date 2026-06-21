<?php

namespace App\Console\Commands;

use App\Services\ExpenseService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('expenses:process-recurring')]
#[Description('Process and generate transactions for due recurring expenses')]
class ProcessRecurringExpenses extends Command
{
    public function handle(ExpenseService $expenseService): int
    {
        $this->info('Starting recurring expense processing...');

        try {
            $expenseService->processRecurringExpenses();
            $this->info('Successfully processed recurring expenses.');

            return 0;
        } catch (\Exception $e) {
            $this->error('Failed to process recurring expenses: '.$e->getMessage());

            return 1;
        }
    }
}
