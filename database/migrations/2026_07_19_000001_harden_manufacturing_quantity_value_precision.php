<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        foreach ($this->quantityColumns() as $table => $columns) {
            foreach ($columns as $column) {
                $this->alterNumericColumn($table, $column, 24, 8);
            }
        }

        foreach ($this->conversionColumns() as $table => $columns) {
            foreach ($columns as $column) {
                $this->alterNumericColumn($table, $column, 24, 12);
            }
        }

        foreach ($this->unitCostColumns() as $table => $columns) {
            foreach ($columns as $column) {
                $this->alterNumericColumn($table, $column, 24, 8);
            }
        }

        foreach ($this->amountColumns() as $table => $columns) {
            foreach ($columns as $column) {
                $this->alterNumericColumn($table, $column, 24, 4);
            }
        }
    }

    /**
     * This migration is intentionally irreversible because reducing numeric
     * precision can destroy production manufacturing and inventory history.
     */
    public function down(): void
    {
        throw new RuntimeException('Manufacturing precision hardening is intentionally irreversible.');
    }

    /**
     * @return array<string, array<int, string>>
     */
    private function quantityColumns(): array
    {
        return [
            'production_bom_lines' => ['quantity_per'],
            'production_bom_versions' => ['quantity_per'],
            'production_bom_version_lines' => ['quantity_per'],
            'production_orders' => ['quantity', 'quantity_base'],
            'production_order_lines' => ['quantity', 'quantity_base'],
            'production_order_components' => [
                'quantity_per',
                'expected_quantity',
                'expected_quantity_base',
                'actual_quantity_consumed',
                'actual_scrap_quantity',
                'remaining_quantity',
                'reserved_quantity',
            ],
            'production_order_routing_lines' => [
                'expected_output_quantity',
                'actual_output_quantity',
                'scrap_quantity',
            ],
            'production_journal_lines' => [
                'quantity',
                'quantity_base',
                'output_quantity',
                'scrap_quantity',
            ],
            'item_ledger_entries' => ['quantity', 'remaining_quantity'],
            'value_entries' => ['quantity', 'invoiced_quantity'],
        ];
    }

    /**
     * @return array<string, array<int, string>>
     */
    private function conversionColumns(): array
    {
        return [
            'item_uom_assignments' => ['conversion_factor'],
            'unit_of_measures' => ['conversion_factor'],
        ];
    }

    /**
     * @return array<string, array<int, string>>
     */
    private function unitCostColumns(): array
    {
        return [
            'items' => ['unit_cost', 'standard_cost', 'last_direct_cost'],
            'production_orders' => ['unit_cost', 'cost_rollup'],
            'production_order_lines' => ['unit_cost'],
            'production_order_components' => ['unit_cost'],
            'production_journal_lines' => ['unit_cost'],
            'value_entries' => ['unit_cost', 'unit_cost_acy'],
            'routing_lines' => ['direct_unit_cost', 'overhead_rate', 'subcontracting_cost'],
            'routing_version_lines' => ['direct_unit_cost', 'overhead_rate', 'subcontracting_cost'],
            'capacity_ledger_entries' => ['unit_cost'],
        ];
    }

    /**
     * @return array<string, array<int, string>>
     */
    private function amountColumns(): array
    {
        return [
            'production_bom_versions' => ['cost_rollup'],
            'production_order_lines' => ['cost_amount'],
            'production_order_components' => ['total_cost'],
            'production_order_routing_lines' => ['direct_cost', 'overhead_cost', 'total_cost'],
            'production_journal_lines' => ['direct_cost', 'overhead_cost', 'total_cost'],
            'item_ledger_entries' => ['cost_amount_actual', 'cost_amount_expected', 'purchase_amount_actual'],
            'value_entries' => [
                'cost_amount_actual',
                'cost_amount_actual_acy',
                'cost_amount_expected',
                'cost_amount_expected_acy',
                'direct_cost_amount',
                'indirect_cost_amount',
                'overhead_amount',
                'variance_amount',
                'purchase_variance_amount',
                'material_variance_amount',
                'capacity_variance_amount',
                'capacity_overhead_variance_amount',
                'manufacturing_overhead_variance_amount',
                'single_level_material_cost',
                'single_level_capacity_cost',
                'single_level_subcontracted_cost',
                'single_level_overhead_cost',
                'single_level_mfg_ovhd_cost',
                'rollover_amount',
                'work_center_purch_capacity',
                'work_center_purch_oh_capacity',
                'work_center_purch_direct_cost',
                'work_center_purch_ovhd_cost',
            ],
            'capacity_ledger_entries' => [
                'direct_cost',
                'overhead_cost',
                'total_cost',
            ],
        ];
    }

    private function alterNumericColumn(string $table, string $column, int $precision, int $scale): void
    {
        if (! Schema::hasTable($table) || ! Schema::hasColumn($table, $column)) {
            return;
        }

        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement(sprintf(
            'ALTER TABLE %s ALTER COLUMN %s TYPE numeric(%d, %d) USING %s::numeric(%d, %d)',
            $this->quoteIdentifier($table),
            $this->quoteIdentifier($column),
            $precision,
            $scale,
            $this->quoteIdentifier($column),
            $precision,
            $scale,
        ));
    }

    private function quoteIdentifier(string $identifier): string
    {
        return '"'.str_replace('"', '""', $identifier).'"';
    }
};
