<?php

namespace App\Filament\Resources\WarehouseEntries\Schemas;

use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Schema;

class WarehouseEntryForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Tabs::make('Warehouse Ledger Entry')
                    ->tabs([
                        Tabs\Tab::make('General')
                            ->icon('heroicon-m-identification')
                            ->schema([
                                Grid::make(3)->schema([
                                    Select::make('item_id')
                                        ->relationship('item', 'item_code')
                                        ->getOptionLabelFromRecordUsing(fn ($record) => "{$record->item_code} - {$record->description}")
                                        ->columnSpan(2),
                                    TextInput::make('entry_type')
                                        ->label('Movement Type')
                                        ->extraInputAttributes(['class' => 'font-bold']),
                                ]),
                                Grid::make(2)->schema([
                                    Select::make('location_id')
                                        ->relationship('location', 'name'),
                                    DateTimePicker::make('entry_timestamp')
                                        ->label('Posting Timestamp'),
                                ]),
                            ]),

                        Tabs\Tab::make('Inventory & Valuation')
                            ->icon('heroicon-m-banknotes')
                            ->schema([
                                Grid::make(2)->schema([
                                    Section::make('Quantities')
                                        ->schema([
                                            TextInput::make('quantity')
                                                ->numeric()
                                                ->step(0.0001),
                                            TextInput::make('quantity_base')
                                                ->label('Quantity (Base)')
                                                ->numeric(),
                                            TextInput::make('unit_of_measure_code')
                                                ->label('UOM'),
                                        ])->columnSpan(1),
                                    Section::make('Costing (LCY)')
                                        ->schema([
                                            TextInput::make('unit_cost')
                                                ->numeric()
                                                ->prefix('$'),
                                            TextInput::make('total_cost')
                                                ->numeric()
                                                ->prefix('$')
                                                ->extraInputAttributes(['class' => 'font-bold']),
                                        ])->columnSpan(1),
                                ]),
                            ]),

                        Tabs\Tab::make('Logistics & Tracking')
                            ->icon('heroicon-m-map-pin')
                            ->schema([
                                Grid::make(2)->schema([
                                    Select::make('zone_id')->relationship('zone', 'zone_code'),
                                    Select::make('bin_id')->relationship('bin', 'bin_code'),
                                ]),
                                Grid::make(3)->schema([
                                    TextInput::make('lot_no')->label('Lot No.'),
                                    TextInput::make('serial_no')->label('Serial No.'),
                                    DatePicker::make('expiration_date'),
                                ]),
                            ]),

                        Tabs\Tab::make('Source & Audit')
                            ->icon('heroicon-m-link')
                            ->schema([
                                Grid::make(3)->schema([
                                    TextInput::make('document_type'),
                                    TextInput::make('document_no')->label('Document No.'),
                                    TextInput::make('document_line_no')->label('Line No.'),
                                ]),
                                Grid::make(2)->schema([
                                    TextInput::make('warehouse_activity_line_id')->label('Activity Line Ref'),
                                    TextInput::make('item_ledger_entry_id')->label('Item Ledger Ref'),
                                    TextInput::make('created_by')->label('User ID'),
                                ]),
                                Textarea::make('description')->rows(2)->columnSpanFull(),
                            ]),
                    ])->columnSpanFull(),
            ])
            ->disabled(); // Warehouse entries are ledger records and should not be edited
    }
}
