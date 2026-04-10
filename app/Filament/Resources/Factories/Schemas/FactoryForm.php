<?php

namespace App\Filament\Resources\Factories\Schemas;

use Filament\Schemas\Schema;

class FactoryForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                \Filament\Forms\Components\TextInput::make('code')
                    ->required()
                    ->maxLength(20)
                    ->unique(ignoreRecord: true),
                \Filament\Forms\Components\TextInput::make('name')
                    ->required()
                    ->maxLength(255),
                \Filament\Forms\Components\Select::make('business_id')
                    ->relationship('business', 'name')
                    ->required(),
                \Filament\Forms\Components\Toggle::make('is_active')
                    ->default(true)
                    ->required(),
            ]);
    }
}
