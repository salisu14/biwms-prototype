<?php

namespace App\Filament\Resources\PurchasePrices\Schemas;

use App\Models\Item;
use App\Models\Vendor;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class PurchasePriceForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Purchase Price Details')
                    ->description('Define a vendor-specific cost for an item, date range, and quantity break.')
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                Select::make('vendor_id')
                                    ->label('Vendor')
                                    ->relationship('vendor', 'vendor_name')
                                    ->searchable()
                                    ->preload()
                                    ->required()
                                    ->getOptionLabelFromRecordUsing(fn (Vendor $record): string => "{$record->vendor_code} - {$record->vendor_name}"),

                                Select::make('item_id')
                                    ->label('Item')
                                    ->relationship('item', 'item_code', fn ($query) => $query->rawMaterials()->where('blocked', false))
                                    ->searchable()
                                    ->preload()
                                    ->required()
                                    ->getOptionLabelFromRecordUsing(fn (Item $record): string => "{$record->item_code} - {$record->description}"),

                                DatePicker::make('starting_date')
                                    ->label('Starting Date')
                                    ->native(false),

                                DatePicker::make('ending_date')
                                    ->label('Ending Date')
                                    ->native(false),

                                TextInput::make('minimum_quantity')
                                    ->label('Minimum Quantity')
                                    ->numeric()
                                    ->default(0)
                                    ->required(),

                                TextInput::make('direct_unit_cost')
                                    ->label('Direct Unit Cost')
                                    ->numeric()
                                    ->required()
                                    ->prefix(fn (): string => config('app.default_currency', 'USD'))
                                    ->step(0.0001),

                                TextInput::make('line_discount_percent')
                                    ->label('Line Discount %')
                                    ->numeric()
                                    ->default(0)
                                    ->suffix('%')
                                    ->step(0.01),

                                TextInput::make('unit_of_measure_code')
                                    ->label('Unit of Measure')
                                    ->maxLength(10),

                                TextInput::make('vendor_item_no')
                                    ->label('Vendor Item No.')
                                    ->maxLength(20),
                            ]),
                    ]),
            ]);
    }
}
