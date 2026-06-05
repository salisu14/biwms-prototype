<?php

namespace App\Filament\Resources\PutawayWorksheets\Schemas;

use App\Filament\Traits\HasSystemGeneratedField;
use Filament\Forms\Components\Select;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class PutawayWorksheetForm
{
    use HasSystemGeneratedField;

    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Worksheet Header')
                    ->description('Primary location and assignment details for this planning document.')
                    ->schema([
                        Grid::make(3)->schema([
                            static::makeSystemGeneratedTextInput(
                                'worksheet_number',
                                'Worksheet No.',
                                'Generated automatically from the put-away worksheet number series and cannot be changed.'
                            ),

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
