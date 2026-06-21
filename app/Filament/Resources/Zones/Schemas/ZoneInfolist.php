<?php

namespace App\Filament\Resources\Zones\Schemas;

use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class ZoneInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('General Identification')
                    ->schema([
                        Grid::make(3)->schema([
                            TextEntry::make('zone_code')
                                ->label('Zone Code')
                                ->weight('bold')
                                ->copyable(),
                            TextEntry::make('zone_name')
                                ->label('Zone Name')
                                ->weight('bold'),
                            TextEntry::make('location.name')
                                ->label('Warehouse Location')
                                ->icon('heroicon-m-map-pin')
                                ->color('primary'),
                        ]),
                        TextEntry::make('description')
                            ->placeholder('No additional description provided.'),
                    ]),

                Section::make('Warehouse Classification')
                    ->schema([
                        Grid::make(2)->schema([
                            TextEntry::make('zone_type')
                                ->badge()
                                ->color('info'),
                            TextEntry::make('warehouse_class')
                                ->badge()
                                ->color('gray'),
                        ]),
                    ]),

                Section::make('Bin Configuration & Capacity')
                    ->description('Operational constraints for items within this zone.')
                    ->schema([
                        Grid::make(3)->schema([
                            IconEntry::make('bin_mandatory')
                                ->label('Bins Mandatory')
                                ->boolean(),
                            TextEntry::make('bin_type_code')
                                ->label('Default Bin Type')
                                ->placeholder('Standard'),
                            TextEntry::make('max_weight')
                                ->label('Zone Weight Limit')
                                ->numeric(4)
                                ->suffix(' kg'),
                        ]),
                    ]),

                Section::make('Controls & Audit')
                    ->collapsible()
                    ->schema([
                        Grid::make(4)->schema([
                            IconEntry::make('is_active')
                                ->label('Status Active')
                                ->boolean(),
                            IconEntry::make('blocked')
                                ->label('Operationally Blocked')
                                ->boolean(),
                            TextEntry::make('sort_order')
                                ->label('Display Rank'),
                            TextEntry::make('updated_at')
                                ->label('Last Configured')
                                ->dateTime(),
                        ]),
                    ]),
            ]);
    }
}
