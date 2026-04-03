<?php

namespace App\Filament\Resources\Locations\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class LocationForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('General Information')
                    ->icon('heroicon-m-map-pin')
                    ->schema([
                        Grid::make(3)
                            ->schema([
                                TextInput::make('code')
                                    ->required()
                                    ->unique(ignoreRecord: true)
                                    ->maxLength(20),
                                TextInput::make('name')
                                    ->required()
                                    ->columnSpan(2),
                                TextInput::make('address')
                                    ->columnSpanFull(),
                                Toggle::make('blocked')
                                    ->label('Blocked / Inactive')
//                                    ->color('danger'),
                            ]),
                    ]),

                Section::make('Warehouse Configuration')
                    ->description('Define how inventory moves through this location.')
                    ->icon('heroicon-m-building-office-2')
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                Toggle::make('require_receive')
                                    ->label('Require Warehouse Receipt')
                                    ->helperText('Inbound documents are required.'),
                                Toggle::make('require_shipment')
                                    ->label('Require Warehouse Shipment')
                                    ->helperText('Outbound documents are required.'),
                                Toggle::make('require_put_away')
                                    ->label('Require Put-away'),
                                Toggle::make('require_pick')
                                    ->label('Require Pick'),
                                Toggle::make('bin_mandatory')
                                    ->label('Bin Mandatory')
                                    ->helperText('All transactions must specify a bin.'),
                                Toggle::make('directed_put_away_and_pick')
                                    ->label('Advanced WMS (Directed)')
                                    ->helperText('Enables zone and bin ranking logic.')
//                                    ->color('primary'),
                            ]),
                    ]),

                Section::make('Default Bins')
                    ->description('Default bins for specific warehouse operations.')
                    ->icon('heroicon-m-archive-box')
                    ->collapsible()
                    ->schema([
                        Grid::make(3)
                            ->schema([
                                Select::make('receipt_bin_code')
                                    ->label('Receipt Bin')
                                    ->options(fn ($record) => $record?->bins()->pluck('code', 'code') ?? []),
                                Select::make('shipment_bin_code')
                                    ->label('Shipment Bin')
                                    ->options(fn ($record) => $record?->bins()->pluck('code', 'code') ?? []),
                                Select::make('adjustment_bin_code')
                                    ->label('Adjustment Bin')
                                    ->options(fn ($record) => $record?->bins()->pluck('code', 'code') ?? []),

                                Section::make('Production Bins')
                                    ->compact()
                                    ->schema([
                                        Grid::make(3)
                                            ->schema([
                                                TextInput::make('open_shop_floor_bin_code')->label('Shop Floor'),
                                                TextInput::make('inbound_production_bin_code')->label('Prod. Inbound'),
                                                TextInput::make('outbound_production_bin_code')->label('Prod. Outbound'),
                                            ]),
                                    ]),
                            ]),
                    ]),
            ]);
    }
}
