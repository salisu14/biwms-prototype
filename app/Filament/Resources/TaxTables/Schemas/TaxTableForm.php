<?php

namespace App\Filament\Resources\TaxTables\Schemas;

use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class TaxTableForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->required()
                    ->maxLength(255),
                TextInput::make('jurisdiction')
                    ->required()
                    ->maxLength(50),
                TextInput::make('country_code')
                    ->maxLength(10),
                TextInput::make('state_code')
                    ->maxLength(10),
                DatePicker::make('effective_date')
                    ->required(),
            ]);
    }
}
