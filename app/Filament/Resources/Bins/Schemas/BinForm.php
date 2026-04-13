<?php

namespace App\Filament\Resources\Bins\Schemas;

use App\Enums\BinType;
use App\Enums\WarehouseClass;
use App\Models\Bin;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;

class BinForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Tabs::make('Warehouse Bin Configuration')
                    ->tabs([
                        Tabs\Tab::make('General Information')
                            ->icon('heroicon-m-map-pin')
                            ->schema([
                                Grid::make(3)->schema([
                                    Select::make('location_id')
                                        ->relationship('location', 'name')
                                        ->searchable()
                                        ->preload()
                                        ->required()
                                        ->live(),
                                    Select::make('zone_id')
                                        ->label('Zone')
                                        ->relationship(
                                            name: 'zone',
                                            titleAttribute: 'zone_code',
                                            modifyQueryUsing: fn ($query, Get $get) => $query->where('location_id', $get('location_id'))
                                        )
                                        ->searchable()
                                        ->preload()
                                        ->required(),
                                    TextInput::make('barcode')
                                        ->label('Bin Barcode')
                                        ->placeholder('Scan or enter barcode'),
                                ]),
                                Grid::make(2)->schema([
                                    TextInput::make('bin_code')
                                        ->label('Bin Code')
                                        ->required()
                                        ->unique(ignoreRecord: true)
                                        ->maxLength(50)
                                        // Lock the field if the record already exists in the database
                                        ->disabled(fn (?Bin $record) => $record !== null)
                                        // Ensure the value is still sent to the database during creation
                                        ->dehydrated()
                                        ->extraInputAttributes(['style' => 'text-transform: uppercase'])
                                        ->helperText('The code cannot be changed once the Bin is created.'),

                                    TextInput::make('bin_name')
                                        ->label('Display Name'),
                                ]),
                            ]),

                        Tabs\Tab::make('Logistics & Type')
                            ->icon('heroicon-m-rectangle-stack')
                            ->schema([
                                Grid::make(2)->schema([
                                    Select::make('bin_type')
                                        ->options(BinType::class)
                                        ->default('STORAGE')
                                        ->required()
                                        ->native(false),
                                    Select::make('warehouse_class')
                                        ->options(WarehouseClass::class)
                                        ->default('standard')
                                        ->required()
                                        ->native(false),
                                ]),
                                Section::make('Inventory Dedication')
                                    ->schema([
                                        Grid::make(2)->schema([
                                            Toggle::make('dedicated')
                                                ->label('Dedicated to specific item')
                                                ->live()
                                                ->inline(false),
                                            Select::make('dedicated_item_id')
                                                ->label('Dedicated Item')
                                                ->relationship('dedicatedItem', 'item_code')
                                                ->getOptionLabelFromRecordUsing(fn ($record) => "{$record->item_code} - {$record->description}")
                                                ->searchable()
                                                ->preload()
                                                ->required(fn (Get $get) => $get('dedicated'))
                                                ->visible(fn (Get $get) => $get('dedicated')),
                                        ]),
                                    ])->compact(),
                            ]),

                        Tabs\Tab::make('Capacity Limits')
                            ->icon('heroicon-m-scale')
                            ->schema([
                                Grid::make(3)->schema([
                                    TextInput::make('maximum_weight')
                                        ->numeric()
                                        ->suffix('kg')
                                        ->step(0.0001),
                                    TextInput::make('maximum_volume')
                                        ->numeric()
                                        ->suffix('m³')
                                        ->step(0.0001),
                                    TextInput::make('maximum_items')
                                        ->label('Max Item Count')
                                        ->numeric(),
                                ]),
                            ]),

                        Tabs\Tab::make('Status & Movement')
                            ->icon('heroicon-m-shield-check')
                            ->schema([
                                Grid::make(2)->schema([
                                    Section::make('Availability')
                                        ->schema([
                                            Toggle::make('is_active')->label('Bin Active')->default(true),
                                            Toggle::make('blocked')->label('Operationally Blocked'),
                                        ])->columnSpan(1)->compact(),
                                    Section::make('Movement Restrictions')
                                        ->schema([
                                            Toggle::make('block_movement_in')->label('Block Inbound (Put-away)'),
                                            Toggle::make('block_movement_out')->label('Block Outbound (Picking)'),
                                        ])->columnSpan(1)->compact(),
                                ]),
                            ]),
                    ])->columnSpanFull(),
            ]);
    }
}
