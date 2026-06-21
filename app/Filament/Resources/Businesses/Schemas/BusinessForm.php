<?php

namespace App\Filament\Resources\Businesses\Schemas;

use App\Models\Business;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class BusinessForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Business Entity Identification')
                    ->description('Define the unique code and naming for this top-level organizational unit.')
                    ->columns(2)
                    ->schema([
                        TextInput::make('code')
                            ->label('Business Code')
                            ->required()
                            ->unique(ignoreRecord: true)
                            ->maxLength(50)
                            // Lock the field if the record already exists in the database
                            ->disabled(fn (?Business $record) => $record !== null)
                            // Ensure the value is still sent to the database during creation
                            ->dehydrated()
                            ->extraInputAttributes(['style' => 'text-transform: uppercase'])
                            ->placeholder('e.g., CORP-NA')
                            ->helperText('The code cannot be changed once the Business is created.'),

                        TextInput::make('name')
                            ->label('Legal Business Name')
                            ->required()
                            ->maxLength(255)
                            ->placeholder('e.g., North American Operations'),

                        Toggle::make('is_active')
                            ->label('Active Status')
                            ->helperText('Inactive businesses will be restricted from new transaction associations.')
                            ->default(true)
                            ->onColor('success')
                            ->offColor('danger')
                            ->required()
                            ->inline(false),
                    ]),
            ]);
    }
}
