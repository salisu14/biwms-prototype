<?php

namespace App\Filament\Resources\Locations\Schemas;

use App\Enums\LocationType;
use App\Enums\TemperatureZone;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Builder;

class LocationForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('General Information')
                    ->icon('heroicon-o-map-pin')
                    ->schema([
                        Grid::make(2)->schema([
                            TextInput::make('code')
                                ->label('Location Code')
                                ->required()
                                ->unique(ignoreRecord: true)
                                ->maxLength(20)
                                ->placeholder('e.g., WH01')
                                ->prefixIcon('heroicon-o-qr-code'),

                            TextInput::make('name')
                                ->label('Location Name')
                                ->required()
                                ->maxLength(100)
                                ->placeholder('e.g., Main Warehouse'),
                        ]),

                        TextInput::make('address')
                            ->placeholder('Physical address or site coordinates')
                            ->columnSpanFull(),
                    ]),

                Section::make('Hierarchy & Classification')
                    ->description('Define position in organization and physical environment.')
                    ->icon('heroicon-o-squares-2x2')
                    ->schema([
                        Grid::make(2)->schema([
                            Select::make('parent_id')
                                ->label('Parent Location')
                                ->relationship(
                                    name: 'parent',
                                    titleAttribute: 'name',
                                    modifyQueryUsing: fn (Builder $query) => $query->active()
                                )
                                ->getOptionLabelFromRecordUsing(fn ($record): string => "{$record->code} - {$record->name}")
                                ->searchable()
                                ->preload()
                                ->placeholder('Root Location'),

                            TextInput::make('sort_order')
                                ->numeric()
                                ->default(0)
                                ->helperText('Display order among siblings'),
                        ]),

                        Grid::make(2)->schema([
                            Select::make('location_type')
                                ->options(collect(LocationType::cases())->mapWithKeys(fn ($case) => [$case->value => $case->label()]))
                                ->default(LocationType::APPROVED->value)
                                ->required(),

                            Select::make('temperature_zone')
                                ->options(collect(TemperatureZone::cases())->mapWithKeys(fn ($case) => [$case->value => $case->label()]))
                                ->default(TemperatureZone::AMBIENT->value)
                                ->required(),
                        ]),
                    ]),

                Section::make('Warehouse Configuration')
                    ->description('Define how inventory moves through this location.')
                    ->icon('heroicon-o-building-office-2')
                    ->collapsible()
                    ->schema([
                        Grid::make(2)->schema([
                            Toggle::make('require_receive')->label('Require Warehouse Receipt'),
                            Toggle::make('require_shipment')->label('Require Warehouse Shipment'),
                            Toggle::make('require_put_away')->label('Require Put-away'),
                            Toggle::make('require_pick')->label('Require Pick'),
                            Toggle::make('bin_mandatory')->label('Bin Mandatory'),
                            Toggle::make('directed_put_away_and_pick')->label('Advanced WMS (Directed)'),
                        ]),
                    ]),

                Section::make('Default Bins')
                    ->description('Default bins for specific warehouse operations.')
                    ->icon('heroicon-o-archive-box')
                    ->collapsible()
                    ->collapsed()
                    ->schema([
                        Grid::make(3)->schema([
                            Select::make('receipt_bin_code')
                                ->label('Receipt Bin')
                                ->options(fn ($record) => $record?->bins()->pluck('bin_code', 'bin_code') ?? []),
                            Select::make('shipment_bin_code')
                                ->label('Shipment Bin')
                                ->options(fn ($record) => $record?->bins()->pluck('bin_code', 'bin_code') ?? []),
                            Select::make('adjustment_bin_code')
                                ->label('Adjustment Bin')
                                ->options(fn ($record) => $record?->bins()->pluck('bin_code', 'bin_code') ?? []),
                        ]),

                        Grid::make(3)->schema([
                            TextInput::make('open_shop_floor_bin_code')->label('Shop Floor Bin'),
                            TextInput::make('inbound_production_bin_code')->label('Prod. Inbound Bin'),
                            TextInput::make('outbound_production_bin_code')->label('Prod. Outbound Bin'),
                        ]),
                    ]),

                Section::make('Status')
                    ->schema([
                        Grid::make(2)->schema([
                            Toggle::make('is_active')
                                ->label('Active')
                                ->default(true)
                                ->onColor('success')
                                ->offColor('danger'),
                            Toggle::make('blocked')
                                ->label('Blocked / Locked'),
                        ]),
                    ]),
            ]);
    }
}
