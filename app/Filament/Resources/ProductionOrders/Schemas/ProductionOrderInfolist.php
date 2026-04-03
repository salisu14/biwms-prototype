<?php

namespace App\Filament\Resources\ProductionOrders\Schemas;

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
                            ->money('USD'),

                        TextEntry::make('inventoryPostingGroup.code')
                            ->label('Inv. Posting Group'),

                        TextEntry::make('generalProductPostingGroup.code')
                            ->label('Gen. Prod. Posting Group'),

                        TextEntry::make('total_actual_cost')
                            ->label('Total Actual Cost')
                            ->money('USD')
                            ->weight(FontWeight::Bold)
                            ->color('success'),
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
            ]);
    }
}
