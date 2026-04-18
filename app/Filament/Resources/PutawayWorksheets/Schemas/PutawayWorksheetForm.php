<?php

namespace App\Filament\Resources\PutawayWorksheets\Schemas;

use App\Models\PutawayWorksheet;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class PutawayWorksheetForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Worksheet Header')
                    ->description('Primary location and assignment details for this planning document.')
                    ->schema([
                        Grid::make(3)->schema([
                            TextInput::make('worksheet_number')
                                ->label('Worksheet No.')
                                ->required()
                                ->unique(ignoreRecord: true)
                                // Lock the field if the record already exists in the database
                                ->disabled(fn (?PutawayWorksheet $record) => $record !== null)
                                // Ensure the value is still sent to the database during creation
                                ->dehydrated()
                                ->extraInputAttributes(['style' => 'text-transform: uppercase'])
                                ->helperText('The number cannot be changed once the PutawayWorksheet is created.'),

                            Select::make('location_id')
                                ->relationship('location', 'name')
                                ->searchable()
                                ->preload()
                                ->required()
                                ->helperText('The warehouse location where items are being put away.'),

                            Select::make('user_id')
                                ->label('Assigned User')
                                ->relationship('user', 'name')
                                ->searchable()
                                ->preload()
                                ->required(),

                            Select::make('status')
                                ->options([
                                    'Open' => 'Open',
                                    'Released' => 'Released',
                                    'In Progress' => 'In Progress',
                                    'Completed' => 'Completed',
                                ])
                                ->required()
                                ->default('Open')
                                ->native(false),
                        ]),
                    ]),
            ]);
    }
}
