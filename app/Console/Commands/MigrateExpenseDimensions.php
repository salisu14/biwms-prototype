<?php

namespace App\Console\Commands;

use App\Models\ExpenseAllocation;
use App\Models\ExpenseBudget;
use App\Models\ExpenseTransaction;
use App\Services\DimensionManagementService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class MigrateExpenseDimensions extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:migrate-expense-dimensions {--dry-run : Perform a dry run without saving changes}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Migrate existing shortcut dimensions to Dimension Sets for Expense Transactions and Allocations';

    /**
     * Execute the console command.
     */
    public function handle(DimensionManagementService $dimensionService)
    {
        $this->info('Starting dimension migration for Expenses...');

        $transactions = ExpenseTransaction::where(function ($query) {
            $query->whereNull('dimension_set_id')->orWhere('dimension_set_id', 0);
        })->get();

        $allocations = ExpenseAllocation::where(function ($query) {
            $query->whereNull('dimension_set_id')->orWhere('dimension_set_id', 0);
        })->get();

        $budgets = ExpenseBudget::where(function ($query) {
            $query->whereNull('dimension_set_id')->orWhere('dimension_set_id', 0);
        })->get();

        $this->info("Found {$transactions->count()} transactions, {$allocations->count()} allocations, and {$budgets->count()} budgets to migrate.");

        DB::transaction(function () use ($transactions, $allocations, $budgets, $dimensionService) {
            $this->migrateRecords($transactions, $dimensionService, 'ExpenseTransaction');
            $this->migrateRecords($allocations, $dimensionService, 'ExpenseAllocation', true);
            $this->migrateRecords($budgets, $dimensionService, 'ExpenseBudget');
        });

        $this->info('Migration completed successfully!');
    }

    private function migrateRecords($records, $dimensionService, $label, $isAllocation = false)
    {
        foreach ($records as $record) {
            $dimensions = [];

            if ($isAllocation) {
                if ($record->target_dimension_1) {
                    $dimensions['DEPARTMENT'] = $record->target_dimension_1;
                }
                if ($record->target_dimension_2) {
                    $dimensions['PROJECT'] = $record->target_dimension_2;
                }
            } else {
                if ($record->shortcut_dimension_1_code) {
                    $dimensions['DEPARTMENT'] = $record->shortcut_dimension_1_code;
                }
                if ($record->shortcut_dimension_2_code) {
                    $dimensions['PROJECT'] = $record->shortcut_dimension_2_code;
                }
            }

            if (empty($dimensions)) {
                continue;
            }

            try {
                $setId = $dimensionService->getDimensionSetID($dimensions);

                if ($this->option('dry-run')) {
                    $this->line("DRY RUN: Would set Dimension Set ID {$setId} for {$label} #{$record->id}");
                } else {
                    $record->update(['dimension_set_id' => $setId]);
                }
            } catch (\Exception $e) {
                $this->error("Failed to migrate {$label} #{$record->id}: ".$e->getMessage());
            }
        }
    }
}
