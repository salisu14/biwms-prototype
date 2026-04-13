<?php

namespace App\Filament\Resources\UnitOfMeasures\Schemas;

use App\Models\UnitOfMeasure;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class UnitOfMeasureForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make()
                    ->columns(2)
                    ->schema([
                        TextInput::make('uom_code')
                            ->label('UOM Code')
                            ->required()
                            ->unique(ignoreRecord: true)
                            ->maxLength(10)
                            // Lock the field if the record already exists in the database
                            ->disabled(fn (?UnitOfMeasure $record) => $record !== null)
                            // Ensure the value is still sent to the database during creation
                            ->dehydrated()
                            ->placeholder('kg, g, L, mL, etc.')
                            ->extraInputAttributes(['style' => 'text-transform: uppercase'])
                            ->helperText('The code cannot be changed once the Unit of Measure group is created.'),

                        TextInput::make('description')
                            ->label('Description')
                            ->required()
                            ->maxLength(50)
                            ->placeholder('Kilogram, Gram, Liter, etc.'),

                        TextInput::make('conversion_factor')
                            ->label('Conversion Factor')
                            ->required()
                            ->numeric()
                            ->default(1)
                            ->step(0.000001)
                            ->helperText('Factor to convert to base unit'),

                        Toggle::make('is_base_uom')
                            ->label('Base UOM')
                            ->default(false)
                            ->helperText('Check if this is the base unit of measure'),
                    ]),
            ]);
    }
}
