<?php

namespace App\Filament\Resources\InventoryPutaways\Schemas;

use App\Models\InventoryPutaway;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class InventoryPutawayForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Grid::make(3)->schema([
                    Section::make('General Information')
                        ->description('Basic identification for this put-away document.')
                        ->schema([
                            TextInput::make('no')
                                ->label('Put-away No.')
                                ->required()
                                ->unique(ignoreRecord: true)
                                ->disabled(fn (?InventoryPutaway $record) => $record !== null)
                                ->dehydrated()
                                ->extraInputAttributes(['style' => 'text-transform: uppercase']),

                            Select::make('location_id')
                                ->relationship('location', 'name')
                                ->searchable()
                                ->preload()
                                ->required(),

                            DateTimePicker::make('posting_date')
                                ->label('Posting Date')
                                ->default(now()),
                        ])->columnSpan(2),

                    Section::make('Status & Assignment')
                        ->schema([
                            TextInput::make('status')
                                ->required()
                                ->default('Open')
                                ->disabled()
                                ->dehydrated(),

                            Select::make('assigned_user_id')
                                ->label('Assigned User')
                                ->relationship('assignedUser', 'name')
                                ->searchable()
                                ->preload(),
                        ])->columnSpan(1),
                ]),

                Section::make('Source Reference')
                    ->description('Links to the document that triggered this put-away.')
                    ->schema([
                        Grid::make(2)->schema([
                            Select::make('source_document')
                                ->options([
                                    'Purchase Order' => 'Purchase Order',
                                    'Sales Return' => 'Sales Return',
                                    'Inbound Transfer' => 'Inbound Transfer',
                                    'Production Output' => 'Production Output',
                                    'Assembly Output' => 'Assembly Output',
                                ])
                                ->required()
                                ->native(false),

                            TextInput::make('source_no')
                                ->label('Source Document No.')
                                ->required(),
                        ]),
                    ]),
            ]);
    }
}
