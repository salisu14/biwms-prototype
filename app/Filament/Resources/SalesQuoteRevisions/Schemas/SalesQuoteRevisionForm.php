<?php

namespace App\Filament\Resources\SalesQuoteRevisions\Schemas;

use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\KeyValue;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class SalesQuoteRevisionForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Revision Details')
                    ->columns(2)
                    ->schema([
                        TextInput::make('revision_number')
                            ->label('Revision Identifier')
                            ->placeholder('e.g., REV-2024-001')
                            ->required()
                            ->unique(ignoreRecord: true),

                        Select::make('sales_quote_id')
                            ->label('Sales Quote')
                            ->relationship('salesQuote', 'quote_no')
                            ->searchable()
                            ->preload()
                            ->required(),

                        TextInput::make('description')
                            ->label('Reason for Revision')
                            ->placeholder('e.g., Updated quantities for item #12')
                            ->columnSpanFull()
                            ->required(),

                        DateTimePicker::make('revision_date')
                            ->label('Timestamp')
                            ->default(now())
                            ->readOnly() // Automated by model, but good for visibility
                            ->displayFormat('M d, Y H:i'),

                        TextInput::make('version')
                            ->label('Version Number')
                            ->numeric()
                            ->placeholder('Auto-generated')
                            ->disabled() // Model handles this on creation
                            ->dehydrated(false),
                    ]),

                Section::make('Data Changes')
                    ->description('Record the specific field changes for this revision')
                    ->schema([
                        KeyValue::make('changes')
                            ->label('Changed Attributes')
                            ->keyLabel('Field Name')
                            ->valueLabel('New Value')
                            ->reorderable()
                            ->columnSpanFull(),
                    ]),
            ]);
    }
}
