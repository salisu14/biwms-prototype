<?php

namespace App\Filament\Resources\Locations\Schemas;

use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class LocationInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Location Overview')
                    ->icon('heroicon-m-map-pin')
                    ->schema([
                        Grid::make(4)
                            ->schema([
                                TextEntry::make('code')->weight('bold'),
                                TextEntry::make('name'),
                                TextEntry::make('address')->placeholder('No address defined'),
                                TextEntry::make('blocked')
                                    ->label('Status')
                                    ->formatStateUsing(fn ($state) => $state ? 'Blocked' : 'Active')
                                    ->badge()
                                    ->color(fn ($state) => $state ? 'danger' : 'success'),
                            ]),
                    ]),

                Grid::make(2)
                    ->schema([
                        Section::make('Warehouse Capabilities')
                            ->columnSpan(1)
                            ->schema([
                                Grid::make(2)
                                    ->schema([
                                        IconEntry::make('require_receive')->label('Warehouse Receipt')->boolean(),
                                        IconEntry::make('require_shipment')->label('Warehouse Shipment')->boolean(),
                                        IconEntry::make('require_put_away')->label('Put-away Required')->boolean(),
                                        IconEntry::make('require_pick')->label('Pick Required')->boolean(),
                                        IconEntry::make('bin_mandatory')->label('Bin Mandatory')->boolean(),
                                        IconEntry::make('directed_put_away_and_pick')->label('Advanced WMS')->boolean(),
                                    ]),
                            ]),

                        Section::make('Default Bin Strategy')
                            ->columnSpan(1)
                            ->schema([
                                Grid::make(2)
                                    ->schema([
                                        TextEntry::make('receipt_bin_code')->label('Receipt Bin')->placeholder('N/A'),
                                        TextEntry::make('shipment_bin_code')->label('Shipment Bin')->placeholder('N/A'),
                                        TextEntry::make('adjustment_bin_code')->label('Adjustment Bin')->placeholder('N/A'),
                                    ]),
                            ]),
                    ]),
            ]);
    }
}
