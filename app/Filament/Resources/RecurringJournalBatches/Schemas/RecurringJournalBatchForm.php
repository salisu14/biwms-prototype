<?php

namespace App\Filament\Resources\RecurringJournalBatches\Schemas;

use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class RecurringJournalBatchForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Batch Details')
                    ->columns(2)
                    ->schema([
                        Select::make('template_id')
                            ->label('Recurring Template')
                            ->relationship('template', 'name')
                            ->required()
                            ->searchable()
                            ->preload()
                            ->native(false),

                        TextInput::make('name')
                            ->label('Batch Name')
                            ->required()
                            ->maxLength(50),

                        Textarea::make('description')
                            ->rows(2)
                            ->columnSpanFull(),

                        Select::make('assigned_user_id')
                            ->label('Assigned User')
                            ->relationship('assignedUser', 'name')
                            ->searchable()
                            ->preload(),
                    ]),

                Section::make('Processing')
                    ->columns(2)
                    ->schema([
                        DatePicker::make('current_processing_date')
                            ->label('Processing Date')
                            ->native(false)
                            ->helperText('The date used as "posting date" when lines are processed.'),
                    ]),
            ]);
    }
}
