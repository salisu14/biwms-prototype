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

class WipValuationReport extends Page implements HasTable
{
    use InteractsWithTable;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-currency-dollar';

    protected string $view = 'filament.pages.manufacturing.wip-valuation-report';

    protected static string|\UnitEnum|null $navigationGroup = 'Manufacturing Reports';

    protected static ?string $title = 'WIP Valuation Report';

    public function table(Table $table): Table
    {
        $currency = config('app.currency', 'USD');

        if (Schema::hasTable('currencies')) {
            $currency = Currency::query()
                ->where('is_lcy', true)
                ->value('code') ?? $currency;
        }

        $capacitySums = DB::table('capacity_ledger_entries')
            ->selectRaw('production_order_id, sum(direct_cost) as labor_cost_sum, sum(overhead_cost) as overhead_cost_sum')
            ->groupBy('production_order_id');

        $itemSums = DB::table('item_ledger_entries')
            ->selectRaw('source_id, sum(cost_amount_actual) as material_cost_sum')
            ->where('source_type', ProductionOrder::class)
            ->where('entry_type', ItemLedgerEntryType::CONSUMPTION->value)
            ->groupBy('source_id');

        $query = ProductionOrder::query()
            // ✅ DOING THE RIGHT THING: Only include active manufacturing orders.
            // Exclude FINISHED (value moved to Inventory) and CANCELLED (no active value).
            ->whereIn('status', [
                ProductionOrderStatus::PLANNED,
                ProductionOrderStatus::FIRM_PLANNED,
                ProductionOrderStatus::RELEASED,
            ])
            ->with('item')
            ->leftJoinSub($capacitySums, 'capacity_sums', function ($join): void {
                $join->on('production_orders.id', '=', 'capacity_sums.production_order_id');
            })
            ->leftJoinSub($itemSums, 'item_sums', function ($join): void {
                $join->on('production_orders.id', '=', 'item_sums.source_id');
            })
            ->select('production_orders.*')
            ->selectRaw('coalesce(production_orders.starting_date_time, production_orders.ending_date_time, production_orders.created_at) as production_date_ref')
            ->selectRaw('coalesce(item_sums.material_cost_sum, 0) as material_wip_cost')
            ->selectRaw('coalesce(capacity_sums.labor_cost_sum, 0) as labor_wip_cost')
            ->selectRaw('coalesce(capacity_sums.overhead_cost_sum, 0) as overhead_wip_cost')
            ->selectRaw('(coalesce(item_sums.material_cost_sum, 0) + coalesce(capacity_sums.labor_cost_sum, 0) + coalesce(capacity_sums.overhead_cost_sum, 0)) as wip_total_cost');

        return $table
            ->query($query)
            ->columns([
                TextColumn::make('document_number')
                    ->label('Order No')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('status')
                    ->badge()
                    ->color(fn ($state) => match ($state) {
                        ProductionOrderStatus::RELEASED => 'warning',
                        ProductionOrderStatus::FIRM_PLANNED => 'info',
                        default => 'gray',
                    }),

                TextColumn::make('item.description')
                    ->label('Item Being Produced')
                    ->searchable(),

                TextColumn::make('location_code')
                    ->label('Location')
                    ->badge(),

                TextColumn::make('material_wip_cost')
                    ->label('Material WIP')
                    ->money($currency)
                    ->color('info')
                    ->sortable()
                    ->summarize(Sum::make()->money($currency)),

                TextColumn::make('labor_wip_cost')
                    ->label('Labor WIP')
                    ->money($currency)
                    ->color('info')
                    ->sortable()
                    ->summarize(Sum::make()->money($currency)),

                TextColumn::make('overhead_wip_cost')
                    ->label('Overhead WIP')
                    ->money($currency)
                    ->color('info')
                    ->sortable()
                    ->summarize(Sum::make()->money($currency)),

                TextColumn::make('wip_total_cost')
                    ->label('Total WIP Value')
                    ->money($currency)
                    ->weight('bold')
                    ->color('success')
                    ->sortable()
                    ->summarize(Sum::make()->money($currency)),

                TextColumn::make('production_date_ref')
                    ->label('Production Date')
                    ->dateTime('Y-m-d H:i')
                    ->sortable(),
            ])
            ->defaultSort('production_date_ref', 'desc');
    }
}
