<?php

namespace App\Filament\Resources\ProductionOrders\Schemas;

use App\Enums\ItemLedgerEntryType;
use App\Enums\ProductionOrderStatus;
use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Enums\FontWeight;

class ProductionOrderInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('General Information')
                    ->icon('heroicon-m-document-text')
                    ->columns(3)
                    ->schema([
                        TextEntry::make('document_number')
                            ->label('Order No.')
                            ->weight(FontWeight::Bold)
                            ->copyable(),

                        TextEntry::make('status')
                            ->badge()
                            ->color(fn (ProductionOrderStatus $state): string => match ($state) {
                                ProductionOrderStatus::SIMULATED => 'gray',
                                ProductionOrderStatus::PLANNED => 'info',
                                ProductionOrderStatus::FIRM_PLANNED => 'warning',
                                ProductionOrderStatus::RELEASED => 'success',
                                ProductionOrderStatus::FINISHED => 'primary',
                                default => 'gray',
                            }),

                        TextEntry::make('source_type')
                            ->label('Source Type')
                            ->badge()
                            ->color('gray'),

                        TextEntry::make('source_no')
                            ->label('Source No.')
                            ->placeholder('N/A'),

                        TextEntry::make('capexProject.project_number')
                            ->label('CapEx Project')
                            ->placeholder('None (Operational)')
                            ->color('info')
                            ->icon('heroicon-m-building-office'),

                        TextEntry::make('item.description')
                            ->label('Item')
                            ->columnSpan(2)
                            ->weight(FontWeight::Bold),

                        TextEntry::make('description')
                            ->label('Order Description')
                            ->columnSpanFull(),
                    ]),

                Grid::make(2)->schema([
                    Section::make('Quantities & Progress')
                        ->icon('heroicon-m-beaker')
                        ->columnSpan(1)
                        ->schema([
                            Grid::make(2)->schema([
                                TextEntry::make('quantity')
                                    ->numeric(4)
                                    ->suffix(fn ($record) => " {$record->unit_of_measure_code}"),

                                TextEntry::make('quantity_base')
                                    ->label('Base Quantity')
                                    ->numeric(4),

                                TextEntry::make('remaining_quantity')
                                    ->label('Remaining to Produce')
                                    ->numeric(4)
                                    ->color('warning')
                                    ->weight(FontWeight::Bold),

                                IconEntry::make('posted')
                                    ->label('Posted Status')
                                    ->boolean(),

                                TextEntry::make('produced_quantity')
                                    ->label('Produced Quantity')
                                    ->state(function ($record): float {
                                        $produced = (float) $record->itemLedgerEntries()
                                            ->where('entry_type', ItemLedgerEntryType::OUTPUT)
                                            ->where('item_id', $record->item_id)
                                            ->sum('quantity');

                                        $uomCode = (string) ($record->unit_of_measure_code ?? '');
                                        if (! $record->item_id || $uomCode === '') {
                                            return $produced;
                                        }

                                        $item = $record->item;
                                        if (! $item) {
                                            return $produced;
                                        }

                                        $baseUom = (string) ($item->base_unit_of_measure ?? '');
                                        if ($baseUom !== '' && strtoupper($uomCode) === strtoupper($baseUom)) {
                                            return $produced;
                                        }

                                        $assignment = $item->uoms()->where('uom_code', $uomCode)->first();
                                        $factor = (float) ($assignment?->pivot?->conversion_factor ?? 1);
                                        if ($factor <= 0) {
                                            return $produced;
                                        }

                                        return $produced / $factor;
                                    })
                                    ->numeric(4)
                                    ->suffix(fn ($record): string => ' '.($record->unit_of_measure_code ?? 'PCS'))
                                    ->weight(FontWeight::Bold)
                                    ->color('success'),
                            ]),
                        ]),

                    Section::make('Timeline')
                        ->icon('heroicon-m-calendar-days')
                        ->columnSpan(1)
                        ->schema([
                            TextEntry::make('due_date')
                                ->date()
                                ->color('danger'),

                            Grid::make(2)->schema([
                                TextEntry::make('starting_date_time')
                                    ->label('Scheduled Start')
                                    ->dateTime(),
                                TextEntry::make('ending_date_time')
                                    ->label('Scheduled End')
                                    ->dateTime(),
                            ]),
                        ]),
                ]),

                Section::make('BOM & Routing Structure')
                    ->icon('heroicon-m-wrench-screwdriver')
                    ->columns(2)
                    ->schema([
                        TextEntry::make('productionBom.description')
                            ->label('BOM')
                            ->hint(fn ($record) => $record->production_bom_version_id ? "Version: {$record->productionBomVersion?->version_code}" : 'Latest'),

                        TextEntry::make('routing.description')
                            ->label('Routing')
                            ->hint(fn ($record) => $record->routing_version_id ? "Version: {$record->routingVersion?->version_code}" : 'Latest'),

                        TextEntry::make('flushing_method')
                            ->badge(),

                        TextEntry::make('scrap_percent')
                            ->label('Scrap Factor')
                            ->numeric(2)
                            ->suffix('%'),
                    ]),

                Section::make('Warehouse & Costing')
                    ->icon('heroicon-m-banknotes')
                    ->columns(3)
                    ->schema([
                        TextEntry::make('location_code')
                            ->label('Target Location')
                            ->icon('heroicon-m-map-pin'),

                        TextEntry::make('costing_method')
                            ->label('Method'),

                        TextEntry::make('unit_cost')
                            ->money('NGN'),

                        TextEntry::make('inventoryPostingGroup.code')
                            ->label('Inv. Posting Group'),

                        TextEntry::make('generalProductPostingGroup.code')
                            ->label('Gen. Prod. Posting Group'),

                        TextEntry::make('total_actual_cost')
                            ->label('Total Actual Cost')
                            ->money('NGN')
                            ->weight(FontWeight::Bold)
                            ->color('success'),

                        TextEntry::make('actual_cost_per_unit')
                            ->label('Actual Cost / Unit (FG)')
                            ->suffix(fn ($record): string => ' / '.($record->unit_of_measure_code ?? 'Unit'))
                            ->state(function ($record): ?float {
                                // 1. Get actual produced qty in BASE UoM from ledger
                                $producedBaseQty = (float) $record->itemLedgerEntries()
                                    ->where('entry_type', ItemLedgerEntryType::OUTPUT)
                                    ->where('item_id', $record->item_id)
                                    ->sum('quantity');

                                if ($producedBaseQty <= 0) {
                                    return null;
                                }

                                // 2. Convert to ORDER UoM
                                $uomCode = (string) ($record->unit_of_measure_code ?? '');
                                $item = $record->item;

                                if ($item && $uomCode !== '') {
                                    $baseUom = (string) ($item->base_unit_of_measure ?? '');
                                    if ($baseUom !== '' && strtoupper($uomCode) !== strtoupper($baseUom)) {
                                        $assignment = $item->uoms()->where('uom_code', $uomCode)->first();
                                        $factor = (float) ($assignment?->pivot?->conversion_factor ?? 1);
                                        if ($factor > 0) {
                                            $producedBaseQty = $producedBaseQty / $factor;
                                        }
                                    }
                                }

                                // 3. Now $producedBaseQty is actually the produced qty in ORDER UoM
                                return (float) $record->total_actual_cost / $producedBaseQty;
                            })
                            ->money('NGN')
                            ->weight(FontWeight::Bold)
                            ->color('info')
                            ->placeholder('N/A'),

                        TextEntry::make('actual_cost_per_piece')
                            ->label('Actual Cost / Piece')
                            ->state(function ($record): ?float {
                                // Always divide by actual base-qty from ledger — no planned values
                                $producedBaseQty = (float) $record->itemLedgerEntries()
                                    ->where('entry_type', ItemLedgerEntryType::OUTPUT)
                                    ->where('item_id', $record->item_id)
                                    ->sum('quantity');

                                if ($producedBaseQty <= 0) {
                                    return null;
                                }

                                return (float) $record->total_actual_cost / $producedBaseQty;
                            })
                            ->money('NGN')
                            ->weight(FontWeight::Bold)
                            ->color('success')
                            ->placeholder('N/A'),
                    ]),

                Section::make('Audit & Tracking')
                    ->icon('heroicon-m-user-circle')
                    ->columns(3)
                    ->collapsed()
                    ->schema([
                        TextEntry::make('creator.name')->label('Created By'),
                        TextEntry::make('created_at')->dateTime(),

                        TextEntry::make('poster.name')->label('Posted By')->placeholder('-'),
                        TextEntry::make('posted_at')->dateTime()->placeholder('-'),

                        TextEntry::make('finisher.name')->label('Finished By')->placeholder('-'),
                        TextEntry::make('finished_at')->dateTime()->placeholder('-'),

                        IconEntry::make('reserved_from_stock')
                            ->label('Reserved')
                            ->boolean(),

                        TextEntry::make('priority')
                            ->numeric(),
                    ]),

                Section::make('Related Warehouse Documents')
                    ->icon('heroicon-m-truck')
                    ->columns(2)
                    ->schema([
                        TextEntry::make('warehouseRequests_count')
                            ->label('Warehouse Requests')
                            ->state(fn ($record) => $record->warehouseRequests()->count())
                            ->badge()
                            ->color('info')
                            ->icon('heroicon-m-clipboard-document-list'),

                        TextEntry::make('warehouseActivities_count')
                            ->label('Warehouse Activities (Picks/Put-aways)')
                            ->state(fn ($record) => $record->warehouseActivities()->count())
                            ->badge()
                            ->color('success')
                            ->icon('heroicon-m-arrow-path'),
                    ])
                    ->visible(fn ($record) => $record->status === ProductionOrderStatus::RELEASED),
            ]);
    }
}
