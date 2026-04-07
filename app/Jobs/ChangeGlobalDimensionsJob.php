<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ChangeGlobalDimensionsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public ?string $oldDim1;
    public ?string $newDim1;
    public ?string $oldDim2;
    public ?string $newDim2;

    /**
     * Tables to update with global dimension columns
     */
    private array $tables = [
        'general_ledger_entries',
        'customer_ledger_entries',
        'vendor_ledger_entries',
        'item_ledger_entries',
        'bank_account_ledger_entries',
        'fixed_asset_ledger_entries',
        'sales_shipment_headers',
        'sales_shipment_lines',
        'sales_invoice_headers',
        'sales_invoice_lines',
        'purchase_receipt_headers',
        'purchase_receipt_lines',
        'purchase_invoice_headers',
        'purchase_invoice_lines',
        'value_entries',
    ];

    public function __construct(
        ?string $oldDim1,
        ?string $newDim1,
        ?string $oldDim2,
        ?string $newDim2
    ) {
        $this->oldDim1 = $oldDim1;
        $this->newDim1 = $newDim1;
        $this->oldDim2 = $oldDim2;
        $this->newDim2 = $newDim2;
    }

    public function handle(): void
    {
        Log::info("Starting global dimension change job", [
            'old_dim_1' => $this->oldDim1,
            'new_dim_1' => $this->newDim1,
            'old_dim_2' => $this->oldDim2,
            'new_dim_2' => $this->newDim2,
        ]);

        foreach ($this->tables as $table) {
            if (!DB::getSchemaBuilder()->hasTable($table)) {
                Log::warning("Table {$table} does not exist, skipping");
                continue;
            }

            $hasDim1 = DB::getSchemaBuilder()->hasColumn($table, 'global_dimension_1_code');
            $hasDim2 = DB::getSchemaBuilder()->hasColumn($table, 'global_dimension_2_code');

            if (!$hasDim1 && !$hasDim2) {
                continue;
            }

            try {
                DB::transaction(function () use ($table, $hasDim1, $hasDim2) {
                    $updates = [];

                    if ($hasDim1 && $this->oldDim1 !== $this->newDim1) {
                        if ($this->newDim1 === null) {
                            // Clear dimension
                            DB::table($table)->update(['global_dimension_1_code' => null]);
                        } else {
                            // Replace old with new
                            DB::table($table)
                                ->where('global_dimension_1_code', $this->oldDim1)
                                ->update(['global_dimension_1_code' => $this->newDim1]);
                        }
                    }

                    if ($hasDim2 && $this->oldDim2 !== $this->newDim2) {
                        if ($this->newDim2 === null) {
                            DB::table($table)->update(['global_dimension_2_code' => null]);
                        } else {
                            DB::table($table)
                                ->where('global_dimension_2_code', $this->oldDim2)
                                ->update(['global_dimension_2_code' => $this->newDim2]);
                        }
                    }
                });

                Log::info("Updated global dimensions in table: {$table}");

            } catch (\Exception $e) {
                Log::error("Failed to update table {$table}", [
                    'error' => $e->getMessage(),
                ]);
                throw $e;
            }
        }

        // Update General Ledger Setup after all tables processed
        DB::table('general_ledger_setup')->update([
            'global_dimension_1_code' => $this->newDim1,
            'global_dimension_2_code' => $this->newDim2,
        ]);

        // Update dimension types
        if ($this->oldDim1) {
            DB::table('dimensions')
                ->where('code', $this->oldDim1)
                ->update(['dimension_type' => 'regular', 'global_dimension_no' => null]);
        }
        if ($this->newDim1) {
            DB::table('dimensions')
                ->where('code', $this->newDim1)
                ->update(['dimension_type' => 'global', 'global_dimension_no' => 1]);
        }
        if ($this->oldDim2) {
            DB::table('dimensions')
                ->where('code', $this->oldDim2)
                ->update(['dimension_type' => 'regular', 'global_dimension_no' => null]);
        }
        if ($this->newDim2) {
            DB::table('dimensions')
                ->where('code', $this->newDim2)
                ->update(['dimension_type' => 'global', 'global_dimension_no' => 2]);
        }

        Log::info("Global dimension change job completed successfully");
    }

    /**
     * Handle job failure
     */
    public function failed(\Throwable $exception): void
    {
        Log::error("Global dimension change job failed", [
            'error' => $exception->getMessage(),
            'trace' => $exception->getTraceAsString(),
        ]);
    }
}
