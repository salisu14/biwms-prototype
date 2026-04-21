<?php

namespace App\Filament\Resources\ItemJournalBatches\Schemas;

use App\Enums\JournalLineType;
use App\Models\ItemJournalBatch;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class ItemJournalBatchForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Batch Identification')
                    ->description('Primary naming and ownership for this journal workspace.')
                    ->columns(2)
                    ->schema([
                        Select::make('template_id')
                            ->label('Journal Template')
                            ->relationship('template', 'name')
                            ->searchable()
                            ->preload()
                            ->required()
                            ->disabled(fn (?ItemJournalBatch $record) => $record !== null)
                            ->dehydrated()
                            ->helperText('The template determines the rules for this batch.'),

                        TextInput::make('name')
                            ->label('Batch Name')
                            ->required()
                            ->maxLength(50)
                            ->extraInputAttributes(['style' => 'text-transform: uppercase'])
                            ->placeholder('e.g., DEFAULT or JAN-AUDIT'),

                        TextInput::make('description')
                            ->label('Description')
                            ->maxLength(100)
                            ->columnSpanFull()
                            ->placeholder('e.g., Primary inventory adjustment batch'),
                    ]),

                Section::make('Defaults & Assignments')
                    ->description('Set default values for lines created within this batch.')
                    ->columns(2)
                    ->schema([
                        Select::make('assigned_user_id')
                            ->label('Assigned User')
                            ->relationship('assignedUser', 'name')
                            ->searchable()
                            ->preload()
                            ->default(auth()->id()),

                        Select::make('location_id')
                            ->label('Default Location')
                            ->relationship('location', 'name')
                            ->searchable()
                            ->preload()
                            ->helperText('Newly created lines will default to this location.'),

                        Select::make('default_entry_type')
                            ->label('Default Entry Type')
                            ->options(JournalLineType::class)
                            ->native(false),

                        TextInput::make('reason_code')
                            ->label('Reason Code')
                            ->maxLength(20)
                            ->placeholder('e.g., CORRECTION'),
                    ]),

                Section::make('Settings')
                    ->schema([
                        Grid::make(3)->schema([
                            Select::make('status')
                                ->options([
                                    'open' => 'Open',
                                    'released' => 'Released',
                                    'posted' => 'Posted',
                                    'cancelled' => 'Cancelled',
                                ])
                                ->required()
                                ->default('open')
                                ->native(false),

                            Toggle::make('copy_item_dimensions')
                                ->label('Copy Item Dimensions')
                                ->helperText('Automatically pull dimension codes from the Item card.')
                                ->default(true)
                                ->inline(false),
                        ]),
                    ]),
            ]);
    }
}
