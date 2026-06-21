<?php

namespace App\Filament\Resources\Bins\Schemas;

use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class BinInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('General Identification')
                    ->schema([
                        Grid::make(3)->schema([
                            TextEntry::make('bin_code')
                                ->label('Bin Code')
                                ->weight('bold')
                                ->copyable(),
                            TextEntry::make('bin_name')
                                ->label('Bin Name')
                                ->placeholder('N/A'),
                            TextEntry::make('barcode')
                                ->label('Barcode')
                                ->icon('heroicon-m-qr-code')
                                ->copyable()
                                ->placeholder('No barcode assigned'),
                        ]),
                        Grid::make(2)->schema([
                            TextEntry::make('location.name')
                                ->label('Warehouse Location')
                                ->icon('heroicon-m-map-pin')
                                ->color('primary'),
                            TextEntry::make('zone.zone_code')
                                ->label('Zone')
                                ->icon('heroicon-m-rectangle-group')
                                ->color('info'),
                        ]),
                    ]),

                Section::make('Classification & Strategy')
                    ->schema([
                        Grid::make(3)->schema([
                            TextEntry::make('bin_type')
                                ->badge()
                                ->color('info'),
                            TextEntry::make('warehouse_class')
                                ->badge()
                                ->color('gray'),
                            IconEntry::make('dedicated')
                                ->label('Dedicated Bin')
                                ->boolean(),
                        ]),
                        TextEntry::make('dedicatedItem.item_number')
                            ->label('Dedicated For Item')
                            ->visible(fn ($record) => $record->dedicated)
                            ->weight('bold'),
//                            ->description(fn ($record) => $record->dedicatedItem?->description),
                    ]),

                Section::make('Physical Capacity')
                    ->description('Operational constraints for volume and weight.')
                    ->schema([
                        Grid::make(3)->schema([
                            TextEntry::make('maximum_weight')
                                ->label('Max Weight')
                                ->numeric(4)
                                ->suffix(' kg'),
                            TextEntry::make('maximum_volume')
                                ->label('Max Volume')
                                ->numeric(4)
                                ->suffix(' m³'),
                            TextEntry::make('maximum_items')
                                ->label('Max Item Count')
                                ->numeric(),
                        ]),
                    ]),

                Section::make('Movement Controls & Status')
                    ->schema([
                        Grid::make(4)->schema([
                            IconEntry::make('is_active')
                                ->label('Active Status')
                                ->boolean(),
                            IconEntry::make('blocked')
                                ->label('Operationally Blocked')
                                ->boolean(),
                            IconEntry::make('block_movement_in')
                                ->label('Inbound Blocked')
                                ->boolean(),
                            IconEntry::make('block_movement_out')
                                ->label('Outbound Blocked')
                                ->boolean(),
                        ]),
                    ]),

                Section::make('Audit Trail')
                    ->collapsible()
                    ->collapsed()
                    ->schema([
                        Grid::make(2)->schema([
                            TextEntry::make('created_at')
                                ->dateTime(),
                            TextEntry::make('updated_at')
                                ->label('Last Configuration Update')
                                ->dateTime(),
                        ]),
                    ]),
            ]);
    }
}
