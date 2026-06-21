<?php

namespace App\Filament\Resources\CustomerGroups\Schemas;

use App\Models\CustomerGroup;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class CustomerGroupForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Group Details')
                    ->description('Define the classification code and name for this customer segment.')
                    ->columns(2)
                    ->schema([
                        TextInput::make('code')
                            ->label('Group Code')
                            ->required()
                            ->unique(ignoreRecord: true)
                            ->maxLength(20)
                            ->placeholder('e.g., WHOLESALE')
                            // Lock the field if the record already exists in the database
                            ->disabled(fn (?CustomerGroup $record) => $record !== null)
                            // Ensure the value is still sent to the database during creation
                            ->dehydrated()
                            ->extraInputAttributes(['style' => 'text-transform: uppercase'])
                            ->helperText('The code cannot be changed once the Customer group is created.'),

                        TextInput::make('name')
                            ->label('Group Name')
                            ->required()
                            ->maxLength(100)
                            ->placeholder('e.g., Wholesale Distribution Partners'),
                    ]),
            ]);
    }
}
