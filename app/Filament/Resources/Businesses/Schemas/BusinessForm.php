<?php

namespace App\Filament\Resources\Businesses\Schemas;

use App\Models\Business;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class BusinessForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('code')
                    ->label('Code')
                    ->required()
                    ->unique(ignoreRecord: true)
                    ->maxLength(50)
                    // Lock the field if the record already exists in the database
                    ->disabled(fn (?Business $record) => $record !== null)
                    // Ensure the value is still sent to the database during creation
                    ->dehydrated()
                    ->extraInputAttributes(['style' => 'text-transform: uppercase'])
                    ->helperText('The code cannot be changed once the Business is created.'),
                TextInput::make('name')
                    ->required()
                    ->maxLength(255),
                Toggle::make('is_active')
                    ->default(true)
                    ->required(),
            ]);
    }
}
