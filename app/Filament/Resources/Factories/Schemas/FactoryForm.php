<?php

namespace App\Filament\Resources\Factories\Schemas;

use App\Models\Factory;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class FactoryForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Factory Unit Identification')
                    ->description('Specify the operational unit details and its parent business entity.')
                    ->columns(2)
                    ->schema([
                        TextInput::make('code')
                            ->label('Factory Code')
                            ->required()
                            ->unique(ignoreRecord: true)
                            ->maxLength(20)
                            // Lock the field if the record already exists to maintain audit integrity
                            ->disabled(fn (?Factory $record) => $record !== null)
                            ->dehydrated()
                            ->extraInputAttributes(['style' => 'text-transform: uppercase'])
                            ->placeholder('e.g., FAC-NY-01')
                            ->helperText('The code is used for dimension mapping and cannot be changed after creation.'),

                        TextInput::make('name')
                            ->label('Factory Name')
                            ->required()
                            ->maxLength(255)
                            ->placeholder('e.g., New York Assembly Plant'),

                        Select::make('business_id')
                            ->label('Parent Business')
                            ->relationship('business', 'name')
                            ->searchable()
                            ->preload()
                            ->required()
                            ->helperText('Select the legal business entity this factory belongs to.'),

                        Toggle::make('is_active')
                            ->label('Operational Status')
                            ->default(true)
                            ->onColor('success')
                            ->offColor('danger')
                            ->required()
                            ->inline(false),
                    ]),
            ]);
    }
}
