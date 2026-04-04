<?php

namespace App\Filament\Pages\Manufacturing;

use App\Enums\ItemLedgerEntryType;
use App\Enums\ProductionOrderStatus;
use App\Models\Manufacturing\ProductionOrder;
use Filament\Pages\Page;
use Filament\Tables\Columns\Summarizers\Sum;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;

class WipValuationReport extends Page implements HasTable
{
    use InteractsWithTable;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-currency-dollar';

    protected string $view = 'filament.pages.manufacturing.wip-valuation-report';

    protected static string|\UnitEnum|null $navigationGroup = 'Manufacturing Reports';

    protected static ?string $title = 'WIP Valuation Report';

    public function table(Table $table): Table
    {
        return $table
            ->query(ProductionOrder::query()->whereIn('status', [ProductionOrderStatus::RELEASED, ProductionOrderStatus::FIRM_PLANNED]))
            ->columns([
                TextColumn::make('document_number')
                    ->label('Order No')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('status')
                    ->badge()
                    ->color(fn ($state) => $state === ProductionOrderStatus::RELEASED ? 'success' : 'warning'),
                TextColumn::make('item.description')
                    ->label('Item Being Produced')
                    ->searchable(),
                TextColumn::make('location_code')
                    ->label('Location')
                    ->badge(),
                TextColumn::make('material_cost')
                    ->label('Material WIP')
                    ->money('USD')
                    ->color('info')
                    ->getStateUsing(fn ($record) => $record->itemLedgerEntries()->where('entry_type', ItemLedgerEntryType::CONSUMPTION)->sum('cost_amount_actual')),
                TextColumn::make('labor_cost')
                    ->label('Labor WIP')
                    ->money('USD')
                    ->color('info')
                    ->getStateUsing(fn ($record) => $record->capacityLedgerEntries()->sum('direct_cost')),
                TextColumn::make('overhead_cost')
                    ->label('Overhead WIP')
                    ->money('USD')
                    ->color('info')
                    ->getStateUsing(fn ($record) => $record->capacityLedgerEntries()->sum('overhead_cost')),
                TextColumn::make('total_actual_cost')
                    ->label('Total WIP Value')
                    ->money('USD')
                    ->weight('bold')
                    ->color('success')
                    ->summarize(Sum::make()->money('USD')),
                TextColumn::make('starting_date_time')
                    ->label('Started At')
                    ->dateTime()
                    ->sortable(),
            ])
            ->defaultSort('total_actual_cost', 'desc');
    }
}
