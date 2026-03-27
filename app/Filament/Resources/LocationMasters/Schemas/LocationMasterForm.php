<?php

namespace App\Filament\Resources\LocationMasters\Schemas;

use App\Enums\LocationType;
use App\Enums\TemperatureZone;
use App\Models\LocationMaster;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\Placeholder;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Builder;

class LocationMasterForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Location Information')
                    ->description('Basic identification and classification')
                    ->icon('heroicon-o-map-pin')
                    ->schema([
                        Grid::make(2)->schema([
                            TextInput::make('location_code')
                                ->label('Location Code')
                                ->required()
                                ->unique(ignoreRecord: true)
                                ->maxLength(20)
                                ->placeholder('WH-01-A-01-05')
                                ->helperText('Format: WAREHOUSE-ZONE-AISLE-RACK-BIN')
                                ->prefixIcon('heroicon-o-qr-code')
                                ->columnSpan(1),

                            TextInput::make('location_name')
                                ->label('Location Name')
                                ->required()
                                ->maxLength(100)
                                ->placeholder('e.g., Raw Materials Zone A, Aisle 1')
                                ->columnSpan(1),
                        ]),

                        Grid::make(2)->schema([
                            // Location Type Enum Select
                            Select::make('location_type')
                                ->label('Location Type')
                                ->options(
                                    collect(LocationType::cases())
                                        ->mapWithKeys(fn ($case) => [$case->value => $case->label()])
                                )
                                ->required()
                                ->default(LocationType::APPROVED->value)
                                ->live()
//                                ->prefixIcon(fn ($state) => LocationType::tryFrom($state)?->icon())
                                ->helperText(fn ($state): string =>
                                    LocationType::tryFrom($state)?->label() ?? 'Select location type'
                                )
                                ->columnSpan(1),

                            // Temperature Zone Enum Select
                            Select::make('temperature_zone')
                                ->label('Temperature Zone')
                                ->options(
                                    collect(TemperatureZone::cases())
                                        ->mapWithKeys(fn ($case) => [$case->value => $case->label()])
                                )
                                ->required()
                                ->default(TemperatureZone::AMBIENT->value)
                                ->live()
//                                ->prefixIcon(fn ($state) => TemperatureZone::tryFrom($state)?->icon())
                                ->helperText(fn ($state): string =>
                                    TemperatureZone::tryFrom($state)?->label() ?? 'Select temperature requirement'
                                )
                                ->columnSpan(1),
                        ]),
                    ]),

                Section::make('Hierarchy & Relationships')
                    ->description('Define parent-child relationships')
                    ->icon('heroicon-o-squares-2x2')
                    ->collapsible()
                    ->schema([
                        Grid::make(2)->schema([
                            // Parent Location Select
                            Select::make('parent_id')
                                ->label('Parent Location')
                                ->relationship(
                                    name: 'parent',
                                    titleAttribute: 'location_name',
                                    modifyQueryUsing: fn (Builder $query) => $query
                                        ->whereNotNull('location_name')
                                        ->where('is_active', true)
                                )
                                ->getOptionLabelFromRecordUsing(fn ($record): string =>
                                "{$record->location_code} - {$record->location_name}"
                                )
                                ->searchable()
                                ->preload()
                                ->placeholder('Select parent location (optional)')
                                ->helperText('Leave empty for top-level locations')
                                ->columnSpan(1),

                            // Sibling Order
                            TextInput::make('sort_order')
                                ->label('Sort Order')
                                ->numeric()
                                ->default(0)
                                ->minValue(0)
                                ->helperText('Display order among siblings')
                                ->columnSpan(1),
                        ]),

                        // Dynamic info about selected parent
                        Placeholder::make('parent_info')
                            ->label('Parent Details')
                            ->content(function ($get) {
                                $parentId = $get('parent_id');
                                if (!$parentId) {
                                    return 'No parent selected - this will be a root location';
                                }

                                $parent = LocationMaster::find($parentId);
                                if (!$parent) {
                                    return 'Parent not found';
                                }

                                return "Type: {$parent->location_type} | Zone: {$parent->temperature_zone}";
                            })
                            ->columnSpanFull()
                            ->visible(fn ($get) => $get('parent_id')),
                    ]),

                Section::make('Status & Configuration')
                    ->description('Availability and monitoring settings')
                    ->icon('heroicon-o-cog-6-tooth')
                    ->collapsible()
                    ->schema([
                        Grid::make(3)->schema([
                            Toggle::make('is_active')
                                ->label('Active')
                                ->required()
                                ->default(true)
                                ->inline(false)
                                ->onColor('success')
                                ->offColor('danger')
                                ->helperText('Inactive locations cannot be used for inventory')
                                ->columnSpan(1),

                            Toggle::make('is_pickable')
                                ->label('Pickable')
                                ->default(true)
                                ->inline(false)
                                ->helperText('Allow picking from this location')
                                ->columnSpan(1),

                            Toggle::make('is_receivable')
                                ->label('Receivable')
                                ->default(true)
                                ->inline(false)
                                ->helperText('Allow receiving to this location')
                                ->columnSpan(1),
                        ]),

                        // Dynamic warning based on location type
                        Placeholder::make('type_warning')
                            ->label('⚠️ Special Requirements')
                            ->content(function ($get) {
                                $type = LocationType::tryFrom($get('location_type'));

                                if (!$type) return null;

                                return match($type) {
                                    LocationType::QUARANTINE => 'Quarantine locations require QA approval for all movements',
                                    LocationType::RECEIVING, LocationType::RETURNS => 'Temperature monitoring is mandatory for this zone',
                                    LocationType::PRODUCTION => 'GMP compliance required - Grade A/B cleanroom',
                                    default => null,
                                };
                            })
                            ->columnSpanFull()
                            ->visible(fn ($get) => in_array($get('location_type'), [
                                LocationType::QUARANTINE->value,
                                LocationType::APPROVED->value,
                                LocationType::SHIPPING->value,
                                LocationType::SHIPPING->value,
                                LocationType::PRODUCTION->value,
                            ])),
                    ]),

                Section::make('Physical Attributes')
                    ->description('Capacity and dimensional constraints')
                    ->icon('heroicon-o-cube')
                    ->collapsible()
                    ->collapsed()
                    ->schema([
                        Grid::make(3)->schema([
                            TextInput::make('max_weight_kg')
                                ->label('Max Weight (kg)')
                                ->numeric()
                                ->minValue(0)
                                ->placeholder('5000')
                                ->suffix('kg')
                                ->columnSpan(1),

                            TextInput::make('max_volume_m3')
                                ->label('Max Volume (m³)')
                                ->numeric()
                                ->minValue(0)
                                ->step(0.01)
                                ->placeholder('50.00')
                                ->suffix('m³')
                                ->columnSpan(1),

                            TextInput::make('max_pallets')
                                ->label('Max Pallets')
                                ->numeric()
                                ->minValue(0)
                                ->placeholder('20')
                                ->suffix('pallets')
                                ->columnSpan(1),
                        ]),

                        TextInput::make('barcode')
                            ->label('Barcode/RFID')
                            ->maxLength(50)
                            ->placeholder('Scan or enter barcode')
//                            ->prefixIcon('heroicon-o-barcode')
                            ->columnSpanFull(),
                    ]),
            ]);
    }
}
