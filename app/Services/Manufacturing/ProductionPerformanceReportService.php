<?php

namespace App\Services\Manufacturing;

use App\Enums\ItemLedgerEntryType;
use App\Enums\ProductionOrderStatus;
use App\Models\Manufacturing\ProductionOrder;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class ProductionPerformanceReportService
{
    public function query(): Builder
    {
        $capacitySums = DB::table('capacity_ledger_entries')
            ->selectRaw('production_order_id, sum(total_cost) as capacity_total_cost_sum')
            ->groupBy('production_order_id');

        $itemSums = DB::table('item_ledger_entries')
            ->selectRaw('source_id, sum(cost_amount_actual) as material_cost_sum')
            ->where('source_type', ProductionOrder::class)
            ->where('entry_type', ItemLedgerEntryType::CONSUMPTION->value)
            ->groupBy('source_id');

        // This now correctly pulls the BASE quantity (e.g., 288 PCS) from the Item Ledger
        $outputSums = DB::table('item_ledger_entries')
            ->selectRaw('source_id, sum(quantity) as produced_qty_sum')
            ->where('source_type', ProductionOrder::class)
            ->where('entry_type', ItemLedgerEntryType::OUTPUT->value)
            ->groupBy('source_id');

        $actualTotalCostSql = '(coalesce(capacity_sums.capacity_total_cost_sum, 0) + coalesce(item_sums.material_cost_sum, 0))';
        $producedQuantitySql = 'coalesce(nullif(output_sums.produced_qty_sum, 0), production_orders.quantity_base)'; // Fallback to base qty

        $baseStandardUnitCostSql = 'coalesce(nullif(production_orders.cost_rollup, 0), nullif(report_items.standard_cost, 0), nullif(production_orders.unit_cost, 0), nullif(report_items.unit_cost, 0), 0)';

        $standardCostSourceSql = "case
            when coalesce(production_orders.cost_rollup, 0) != 0 then 'cost_rollup'
            when coalesce(report_items.standard_cost, 0) != 0 then 'item_standard_cost'
            when coalesce(production_orders.unit_cost, 0) != 0 then 'order_unit_cost'
            when coalesce(report_items.unit_cost, 0) != 0 then 'item_unit_cost'
            else 'none'
        end";

        $standardUnitCostSql = $baseStandardUnitCostSql;

        $standardTotalCostSql = "({$standardUnitCostSql} * {$producedQuantitySql})";
        $varianceAmountSql = "({$actualTotalCostSql} - {$standardTotalCostSql})";
        $variancePercentSql = "case when {$standardTotalCostSql} = 0 then null else (({$varianceAmountSql}) / {$standardTotalCostSql}) * 100 end";
        $actualUnitCostSql = "case when {$producedQuantitySql} = 0 then 0 else ({$actualTotalCostSql}) / {$producedQuantitySql} end";

        return ProductionOrder::query()
            ->where('status', ProductionOrderStatus::FINISHED)
            ->with('item.baseUom')
            ->leftJoin('items as report_items', 'report_items.id', '=', 'production_orders.item_id')
            ->leftJoin('unit_of_measures as base_uoms', 'base_uoms.id', '=', 'report_items.base_uom_id')
            ->leftJoinSub($capacitySums, 'capacity_sums', function ($join): void {
                $join->on('production_orders.id', '=', 'capacity_sums.production_order_id');
            })
            ->leftJoinSub($itemSums, 'item_sums', function ($join): void {
                $join->on('production_orders.id', '=', 'item_sums.source_id');
            })
            ->leftJoinSub($outputSums, 'output_sums', function ($join): void {
                $join->on('production_orders.id', '=', 'output_sums.source_id');
            })
            ->select('production_orders.*')
            ->selectRaw("coalesce(base_uoms.uom_code, production_orders.unit_of_measure_code, 'PCS') as base_unit_of_measure")
            ->selectRaw("{$producedQuantitySql} as produced_qty_sql")
            ->selectRaw("{$standardCostSourceSql} as standard_cost_source_sql")
            ->selectRaw("{$standardUnitCostSql} as standard_unit_cost_sql")
            ->selectRaw("{$actualUnitCostSql} as actual_unit_cost_sql")
            ->selectRaw("{$actualTotalCostSql} as actual_total_cost_sql")
            ->selectRaw("{$standardTotalCostSql} as standard_total_cost_sql")
            ->selectRaw("{$varianceAmountSql} as variance_amount_sql")
            ->selectRaw("{$variancePercentSql} as variance_percent_sql");
    }
}
