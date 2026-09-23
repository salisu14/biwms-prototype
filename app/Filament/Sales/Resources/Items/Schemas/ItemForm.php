<?php

namespace App\Filament\Sales\Resources\Items\Schemas;

use App\Models\Item;
use App\Support\CurrencyPresentation;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class ItemForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('item_code')
                    ->required()
                    ->unique(ignoreRecord: true)
                    ->disabled(),

                TextInput::make('description')
                    ->required()
                    ->disabled(),

                TextInput::make('unit_price')
                    ->numeric()
                    ->prefix(fn (?Item $record): string => CurrencyPresentation::symbol($record?->currency?->code ?: CurrencyPresentation::default()))
                    ->disabled(),

                TextInput::make('unit_of_measure')
                    ->disabled(),

                TextInput::make('inventory_quantity')
                    ->numeric()
                    ->disabled()
                    ->label('Stock on Hand'),
            ]);
    }
}
