<?php

namespace App\Filament\Resources\PhysicalInventoryJournals\Schemas;

use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class PhysicalInventoryJournalForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Grid::make(12)->schema([
                    Section::make('General Information')
                        ->columnSpan(12)
                        ->columns(2)
                        ->schema([
                            TextInput::make('journal_batch_name')
                                ->label('Batch Name')
                                ->required()
                                ->unique(ignoreRecord: true)
                                ->placeholder('e.g., PI-MAY-2024')
                                ->prefixIcon('heroicon-m-tag'),

                            Select::make('status')
                                ->options([
                                    'Open' => 'Open',
                                    'Counting' => 'Counting',
                                    'Calculated' => 'Calculated',
                                    'Posted' => 'Posted',
                                ])
                                ->default('Open')
                                ->required()
                                ->native(false)
                                ->prefixIcon('heroicon-m-arrow-path'),

                            TextInput::make('description')
                                ->columnSpanFull(),
                        ]),

                    Section::make('Inventory Scope')
                        ->description('Define where the count is taking place.')
                        ->columnSpan(8)
                        ->columns(2)
                        ->schema([
                            Select::make('location_code')
                                ->relationship('location', 'name')
                                ->searchable()
                                ->preload()
                                ->required()
                                ->prefixIcon('heroicon-m-map-pin'),

                            Select::make('bin_code')
                                ->relationship('bin', 'bin_code')
                                ->searchable()
                                ->preload()
                                ->prefixIcon('heroicon-m-square-3-stack-3d'),

                            Select::make('reason_code')
                                ->relationship('reasonCode', 'code')
                                ->searchable()
                                ->preload()
                                ->prefixIcon('heroicon-m-question-mark-circle'),

                            Select::make('sorting_method')
                                ->options([
                                    'Item' => 'Item',
                                    'Bin' => 'Bin',
                                    'Shelf' => 'Shelf',
                                ])
                                ->default('Item')
                                ->required()
                                ->native(false)
                                ->prefixIcon('heroicon-m-bars-3-bottom-left'),
                        ]),

                    Section::make('Dates & Assignment')
                        ->columnSpan(4)
                        ->schema([
                            DatePicker::make('posting_date')
                                ->default(now())
                                ->required(),

                            DatePicker::make('document_date')
                                ->default(now()),

                            Select::make('assigned_user_id')
                                ->label('Assigned To')
                                ->relationship('assignedUser', 'name')
                                ->searchable()
                                ->preload(),
                        ]),

                    Section::make('Audit Trail')
                        ->columnSpan(12)
                        ->columns(3)
                        ->visible(fn ($record) => $record !== null)
                        ->schema([
                            Grid::make(2)->schema([
                                Select::make('counted_by')
                                    ->relationship('countedBy', 'name')
                                    ->disabled(),
                                DateTimePicker::make('counted_at')
                                    ->disabled(),
                            ])->columnSpan(1),

                            Grid::make(2)->schema([
                                Select::make('posted_by')
                                    ->relationship('postedBy', 'name')
                                    ->disabled(),
                                DateTimePicker::make('posted_at')
                                    ->disabled(),
                            ])->columnSpan(1),
                        ]),
                ]),
            ]);
    }
}
