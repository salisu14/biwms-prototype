<?php

namespace App\Filament\Resources\SalesQuotes\Schemas;

use App\Enums\QuoteStatus;
use App\Models\SalesQuote;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;

class SalesQuoteForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('General Information')
                    ->columns(2)
                    ->schema([
                        TextInput::make('quote_no')
                            ->label('Quote Number')
                            ->required()
                            ->unique(ignoreRecord: true)
                            ->prefix('#')
                            ->maxLength(10)
                            ->placeholder('e.g. QTE-2023-001')
                            // Lock the field if the record already exists in the database
                            ->disabled(fn (?SalesQuote $record) => $record !== null)
                            // Ensure the value is still sent to the database during creation
                            ->dehydrated()
                            ->extraInputAttributes(['style' => 'text-transform: uppercase'])
                            ->helperText('The number cannot be changed once the Sales quote is created.'),

                        Select::make('customer_id')
                            ->relationship('customer', 'name')
                            ->searchable()
                            ->preload()
                            ->required(),

                        DatePicker::make('quote_date')
                            ->default(now())
                            ->required()
                            ->live(),

                        DatePicker::make('valid_until')
                            ->minDate(fn (Get $get) => $get('quote_date'))
                            ->hint('Must be after quote date'),
                    ]),

                Section::make('Financials & Status')
                    ->columns(3)
                    ->schema([
                        TextInput::make('total_amount')
                            ->numeric()
                            ->required()
                            ->default(0)
                            ->prefix('$') // Change to your currency
                            ->step(0.01),

                        Select::make('status')
                            ->options(QuoteStatus::class)
                            ->default('draft')
                            ->required()
                            ->selectablePlaceholder(false),

                        TextInput::make('approval_status')
                            ->label('Approval Status')
                            ->default('pending')
                            ->readOnly()
                            ->dehydrated(),
                    ]),

                Section::make('Approval Metadata')
                    ->description('Automatically recorded upon approval')
                    ->columns(2)
                    ->collapsed() // Hide by default as these are usually system-filled
                    ->schema([
                        Select::make('approved_by')
                            ->relationship('approver', 'name')
                            ->disabled(),

                        DateTimePicker::make('approved_at')
                            ->disabled(),
                    ]),
            ]);
    }
}
