<?php

namespace App\Filament\Pages\Manufacturing;

use App\Enums\ItemLedgerEntryType;
use App\Enums\ProductionOrderStatus;
use App\Models\Currency;
use App\Models\Manufacturing\ProductionOrder;
use Filament\Pages\Page;
use Filament\Tables\Columns\Summarizers\Sum;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class ProductionPerformanceReport extends Page implements HasTable
{
    use InteractsWithTable;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-presentation-chart-line';

    protected string $view = 'filament.pages.manufacturing.production-performance-report';

    protected static string|\UnitEnum|null $navigationGroup = 'Manufacturing Reports';

    protected static ?string $title = 'Production Performance Analysis';

    public function table(Table $table): Table
    {
        $currency = config('app.currency', 'USD');

        if (Schema::hasTable('currencies')) {
            $currency = Currency::query()
                ->where('is_lcy', true)
                ->value('code') ?? $currency;
        }

        $capacitySums = DB::table('capacity_ledger_entries')
            ->selectRaw('production_order_id, sum(total_cost) as capacity_total_cost_sum')
            ->groupBy('production_order_id');

        $itemSums = DB::table('item_ledger_entries')
            ->selectRaw('source_id, sum(cost_amount_actual) as material_cost_sum')
            ->where('source_type', ProductionOrder::class)
            ->where('entry_type', ItemLedgerEntryType::CONSUMPTION->value)
            ->groupBy('source_id');

        $query = ProductionOrder::query()
            ->where('status', ProductionOrderStatus::FINISHED)
            ->with('item')
            ->leftJoinSub($capacitySums, 'capacity_sums', function ($join): void {
                $join->on('production_orders.id', '=', 'capacity_sums.production_order_id');
            })
            ->leftJoinSub($itemSums, 'item_sums', function ($join): void {
                $join->on('production_orders.id', '=', 'item_sums.source_id');
            })
            ->select('production_orders.*')
            ->selectRaw('(coalesce(capacity_sums.capacity_total_cost_sum, 0) + coalesce(item_sums.material_cost_sum, 0)) as actual_total_cost_sql')
            ->selectRaw('((coalesce(capacity_sums.capacity_total_cost_sum, 0) + coalesce(item_sums.material_cost_sum, 0)) - (production_orders.cost_rollup * production_orders.quantity)) as variance_amount_sql');

        return $table
            ->query($query)
            ->columns([
                TextColumn::make('document_number')
                    ->label('Order No')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('item.description')
                    ->label('Finished Good')
                    ->searchable(),
                TextColumn::make('quantity')
                    ->label('Produced Qty')
                    ->numeric(2),
                TextColumn::make('unit_cost')
                    ->label('Actual Unit Cost')
                    ->money($currency),
                TextColumn::make('cost_rollup')
                    ->label('Standard Unit Cost')
                    ->money($currency),
                TextColumn::make('actual_total_cost_sql')
                    ->label('Total Actual Cost')
                    ->money($currency)
                    ->summarize(Sum::make()->money($currency)),
                TextColumn::make('variance_amount_sql')
                    ->label('Variance $')
                    ->money($currency)
                    ->color(fn ($state) => $state > 0 ? 'danger' : 'success')
                    ->summarize(Sum::make()->money($currency)),
                TextColumn::make('variance_percent')
                    ->label('Variance %')
                    ->getStateUsing(function ($record) {
                        $standard = $record->cost_rollup * $record->quantity;
                        if ($standard == 0) {
                            return 0;
                        }

                        return ($record->variance_amount_sql / $standard) * 100;
                    })
                    ->numeric(2)
                    ->suffix('%')
                    ->color(fn ($state) => $state > 5 ? 'danger' : ($state < -5 ? 'success' : 'gray')),
                TextColumn::make('finished_at')
                    ->label('Completed At')
                    ->date()
                    ->sortable(),
            ])
            ->defaultSort('finished_at', 'desc');
    }
}
