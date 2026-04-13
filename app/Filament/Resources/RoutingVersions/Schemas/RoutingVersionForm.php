<?php

namespace App\Filament\Resources\RoutingVersions\Schemas;

use App\Models\Manufacturing\RoutingVersion;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\ToggleButtons;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class RoutingVersionForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Grid::make(3)
                    ->schema([
                        Section::make('General Information')
                            ->columnSpan(2)
                            ->schema([
                                Select::make('routing_id')
                                    ->relationship('routing', 'description')
                                    ->searchable()
                                    ->preload()
                                    ->required(),

                                TextInput::make('version_code')
                                    ->label('Version Code')
                                    ->required()
                                    ->unique(ignoreRecord: true)
                                    ->maxLength(20)
                                    ->placeholder('e.g. V1.0')
                                    // Lock the field if the record already exists in the database
                                    ->disabled(fn (?RoutingVersion $record) => $record !== null)
                                    // Ensure the value is still sent to the database during creation
                                    ->dehydrated()
                                    ->extraInputAttributes(['style' => 'text-transform: uppercase'])
                                    ->helperText('The code cannot be changed once the Sales credit memo is created.'),

                                TextInput::make('description')
                                    ->columnSpanFull(),
                            ])->columns(2),

                        Section::make('Settings & Status')
                            ->columnSpan(1)
                            ->schema([
                                ToggleButtons::make('status')
                                    ->options([
                                        'UNDER_DEVELOPMENT' => 'Development',
                                        'CERTIFIED' => 'Certified',
                                        'CLOSED' => 'Closed',
                                    ])
                                    ->colors([
                                        'UNDER_DEVELOPMENT' => 'warning',
                                        'CERTIFIED' => 'success',
                                        'CLOSED' => 'danger',
                                    ])
                                    ->icons([
                                        'UNDER_DEVELOPMENT' => 'heroicon-o-beaker',
                                        'CERTIFIED' => 'heroicon-o-check-badge',
                                        'CLOSED' => 'heroicon-o-x-circle',
                                    ])
                                    ->default('UNDER_DEVELOPMENT')
                                    ->required(),

                                Select::make('type')
                                    ->options([
                                        'SERIAL' => 'Serial',
                                        'PARALLEL' => 'Parallel',
                                    ])
                                    ->default('SERIAL')
                                    ->selectablePlaceholder(false)
                                    ->required(),

                                TextInput::make('cost_rollup')
                                    ->label('Cost Rollup')
                                    ->numeric()
                                    ->prefix('$')
                                    ->default(0)
                                    ->readOnly(), // Usually calculated via lines, not manual
                            ]),

                        Section::make('Validity Period')
                            ->columnSpanFull()
                            ->schema([
                                DatePicker::make('starting_date')
                                    ->native(false)
                                    ->displayFormat('d/m/Y'),
                                DatePicker::make('ending_date')
                                    ->native(false)
                                    ->displayFormat('d/m/Y')
                                    ->after('starting_date'),
                            ])->columns(2),
                    ]),

                // Audit hidden fields (auto-populated by observer or lifecycle)
                TextInput::make('created_by')
                    ->hidden()
                    ->dehydrateStateUsing(fn ($state) => $state ?? auth()->id()),
                TextInput::make('last_modified_by')
                    ->hidden()
                    ->dehydrated()
                    ->formatStateUsing(fn () => auth()->id()),
            ]);
    }
}
