<?php

namespace App\Filament\Resources\Zones\Schemas;

use App\Enums\WarehouseClass;
use App\Enums\ZoneType;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Schema;

class ZoneForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Tabs::make('Warehouse Zone Configuration')
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
                                        ->columnSpan(2),
                                    TextInput::make('sort_order')
                                        ->label('Display Order')
                                        ->numeric()
                                        ->default(0),
                                ]),
                                Grid::make(2)->schema([
                                    TextInput::make('zone_code')
                                        ->label('Zone Code')
                                        ->required()
                                        ->unique(ignoreRecord: true)
                                        ->extraInputAttributes(['style' => 'text-transform: uppercase']),
                                    TextInput::make('zone_name')
                                        ->label('Zone Name')
                                        ->required(),
                                ]),
                                Textarea::make('description')
                                    ->rows(2)
                                    ->columnSpanFull(),
                            ]),

                        Tabs\Tab::make('Logistics & Classification')
                            ->icon('heroicon-m-rectangle-group')
                            ->schema([
                                Grid::make(2)->schema([
                                    Select::make('zone_type')
                                        ->label('Zone Type/Function')
                                        ->options(ZoneType::class)
                                        ->default(ZoneType::STORAGE) // Assuming STORAGE exists in enum
                                        ->required()
                                        ->native(false),
                                    Select::make('warehouse_class')
                                        ->label('Warehouse Class')
                                        ->options(WarehouseClass::class)
                                        ->default('standard')
                                        ->required()
                                        ->native(false),
                                ]),
                                Section::make('Bin Strategy')
                                    ->schema([
                                        Grid::make(3)->schema([
                                            TextInput::make('bin_type_code')
                                                ->label('Bin Type Code')
                                                ->placeholder('e.g., PICK, PUTAWAY'),
                                            Toggle::make('bin_mandatory')
                                                ->label('Bin Mandatory')
                                                ->helperText('Items must be placed in a bin within this zone.')
                                                ->inline(false),
                                            TextInput::make('max_weight')
                                                ->label('Max Weight Capacity')
                                                ->numeric()
                                                ->suffix('kg')
                                                ->placeholder('0.0000'),
                                        ]),
                                    ])->compact(),
                            ]),

                        Tabs\Tab::make('Status & Administration')
                            ->icon('heroicon-m-shield-check')
                            ->schema([
                                Grid::make(2)->schema([
                                    Toggle::make('is_active')
                                        ->label('Zone Active')
                                        ->default(true)
                                        ->onColor('success'),
                                    Toggle::make('blocked')
                                        ->label('Blocked/Under Maintenance')
                                        ->onColor('danger'),
                                ])->inlineLabel(),
                            ]),
                    ])->columnSpanFull(),
            ]);
    }
}
