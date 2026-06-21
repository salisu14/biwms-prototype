<?php

namespace App\Filament\Resources\Routings\Schemas;

use App\Models\Manufacturing\Routing;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class RoutingForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('General Information')
                    ->description('Define the routing header details.')
                    ->schema([
                        Grid::make(3)->schema([
                            TextInput::make('code')
                                ->label('Routing Code')
                                ->required()
                                ->unique(ignoreRecord: true)
                                ->maxLength(50)
                                // Lock the field if the record already exists in the database
                                ->disabled(fn (?Routing $record) => $record !== null)
                                // Ensure the value is still sent to the database during creation
                                ->dehydrated()
                                ->extraInputAttributes(['style' => 'text-transform: uppercase'])
                                ->helperText('The code cannot be changed once the Routing is created.'),

                            Select::make('type')
                                ->label('Routing Type')
                                ->options([
                                    'SERIAL' => 'Serial',
                                    'PARALLEL' => 'Parallel',
                                ])
                                ->required()
                                ->default('SERIAL'),

                            TextInput::make('version')
                                ->label('Version')
                                ->default(1)
                                ->numeric(),
                        ]),

                        TextInput::make('description')
                            ->label('Description')
                            ->required()
                            ->columnSpanFull(),

                        Grid::make(2)->schema([
                            DatePicker::make('starting_date')
                                ->label('Valid From')
                                ->native(false),

                            DatePicker::make('ending_date')
                                ->label('Valid Until')
                                ->native(false),
                        ]),
                    ]),

                Section::make('Item & Costing')
                    ->description('Link the routing to an item and set costing parameters.')
                    ->schema([
                        Grid::make(2)->schema([
                            Select::make('item_id')
                                ->label('Item')
                                ->relationship('item', 'description', modifyQueryUsing: fn ($query) => $query->where('is_active', true))
                                ->searchable()
                                ->preload()
                                ->required(),

                            Select::make('status')
                                ->label('Status')
                                ->options([
                                    'DRAFT' => 'Draft',
                                    'CERTIFIED' => 'Certified',
                                    'ARCHIVED' => 'Archived',
                                ])
                                ->default('DRAFT')
                                ->required(),
                        ]),

                        TextInput::make('cost_rollup')
                            ->label('Cost Rollup')
                            ->required()
                            ->numeric()
                            ->prefix('$')
                            ->step(0.01)
                            ->helperText('Total estimated cost to run this routing.'),
                    ])
                    ->collapsible(),

                Section::make('Audit Trail')
                    ->schema([
                        Hidden::make('created_by')
                            ->label('Created By')
                            ->default(auth()->id()),

                        Hidden::make('last_modified_by')
                            ->label('Last Modified By')
                            ->default(auth()->id()),
                    ])
                    ->collapsible()
                    ->visible(fn ($record) => $record !== null), // Only visible on edit
            ]);
    }
}
