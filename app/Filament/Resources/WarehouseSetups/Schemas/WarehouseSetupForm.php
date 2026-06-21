<?php

namespace App\Filament\Resources\WarehouseSetups\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;

class WarehouseSetupForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('General')
                    ->columns(2)
                    ->schema([
                        Toggle::make('location_mandatory')
                            ->label('Location Mandatory')
                            ->helperText('Require location code on all inventory transactions')
                            ->default(false),

                        Toggle::make('bin_mandatory')
                            ->label('Bin Mandatory')
                            ->helperText('Require bin code on all inventory transactions')
                            ->default(false),
                    ]),

                Section::make('Warehouse Documents Required')
                    ->description('Require warehouse documents before inventory transactions')
                    ->columns(2)
                    ->schema([
                        Toggle::make('require_receive')
                            ->label('Require Receive')
                            ->helperText('Require Warehouse Receipt before posting purchase receipts'),

                        Toggle::make('require_putaway')
                            ->label('Require Put-away')
                            ->helperText('Require Put-away before items are available'),

                        Toggle::make('require_pick')
                            ->label('Require Pick')
                            ->helperText('Require Pick before posting shipments'),

                        Toggle::make('require_shipment')
                            ->label('Require Shipment')
                            ->helperText('Require Warehouse Shipment before posting sales shipments'),
                    ]),

                Section::make('Advanced WMS')
                    ->columns(2)
                    ->schema([
                        Toggle::make('directed_putaway_and_pick')
                            ->label('Directed Put-away and Pick')
                            ->helperText('Enable advanced WMS with directed movements')
                            ->live(),

                        TextInput::make('warehouse_receipt_nos')
                            ->label('Warehouse Receipt Nos.')
                            ->placeholder('WH-RCPT-####')
                            ->visible(fn (Get $get) => $get('directed_putaway_and_pick')),

                        TextInput::make('warehouse_shipment_nos')
                            ->label('Warehouse Shipment Nos.')
                            ->placeholder('WH-SHIP-####')
                            ->visible(fn (Get $get) => $get('directed_putaway_and_pick')),

                        TextInput::make('internal_putaway_nos')
                            ->label('Internal Put-away Nos.')
                            ->placeholder('WH-PUT-####')
                            ->visible(fn (Get $get) => $get('directed_putaway_and_pick')),

                        TextInput::make('internal_pick_nos')
                            ->label('Internal Pick Nos.')
                            ->placeholder('WH-PICK-####')
                            ->visible(fn (Get $get) => $get('directed_putaway_and_pick')),
                    ]),

                Section::make('Bin Policies')
                    ->columns(2)
                    ->schema([
                        Select::make('bin_capacity_policy')
                            ->options([
                                'Never Check' => 'Never Check',
                                'Check' => 'Check',
                                'Prohibit' => 'Prohibit',
                            ])
                            ->default('Never Check'),

                        Toggle::make('allow_breakbulk')
                            ->label('Allow Breakbulk')
                            ->helperText('Allow breaking units of measure during put-away'),

                        TextInput::make('putaway_template_nos')
                            ->label('Put-away Template Nos.'),

                        Toggle::make('pick_according_to_fefo')
                            ->label('Pick According to FEFO')
                            ->helperText('First Expired, First Out picking method'),

                        Select::make('default_bin_selection')
                            ->options([
                                'Fixed Bin' => 'Fixed Bin',
                                'Last Bin Used' => 'Last Bin Used',
                                'WMS Default' => 'WMS Default',
                            ])
                            ->default('Fixed Bin'),
                    ]),
            ]);
    }
}
