<?php

namespace App\Filament\Resources\NumberSeries\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class NumberSeriesForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('code')
                    ->required(),
                TextInput::make('description')
                    ->required(),
                TextInput::make('prefix')
                    ->required()
                    ->default('P'),
                TextInput::make('starting_number')
                    ->required()
                    ->numeric()
                    ->default(1),
                TextInput::make('ending_number')
                    ->numeric(),
                TextInput::make('current_number')
                    ->required()
                    ->numeric()
                    ->default(0),
                TextInput::make('year')
                    ->required()
                    ->numeric()
                    ->default(2026),
                Toggle::make('is_active')
                    ->required(),
                Toggle::make('allow_manual')
                    ->required(),
                TextInput::make('module')
                    ->required()
                    ->default('purchase'),
            ]);
    }
}
