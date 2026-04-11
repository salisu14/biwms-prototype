<?php

namespace App\Filament\Resources\CustomerLedgerEntries\Schemas;

use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class CustomerLedgerEntryForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('entry_number')
                    ->required()
                    ->numeric(),
                Select::make('customer_id')
                    ->relationship('customer', 'name')
                    ->required(),
                TextInput::make('document_type')
                    ->required(),
                TextInput::make('document_number')
                    ->required(),
                TextInput::make('external_document_number'),
                TextInput::make('description')
                    ->required(),
                Textarea::make('comment')
                    ->columnSpanFull(),
                DatePicker::make('posting_date')
                    ->required(),
                DatePicker::make('document_date')
                    ->required(),
                DatePicker::make('due_date'),
                TextInput::make('debit_amount')
                    ->required()
                    ->numeric()
                    ->default(0),
                TextInput::make('credit_amount')
                    ->required()
                    ->numeric()
                    ->default(0),
                TextInput::make('amount')
                    ->required()
                    ->numeric(),
                TextInput::make('running_balance')
                    ->required()
                    ->numeric()
                    ->default(0),
                TextInput::make('remaining_amount')
                    ->required()
                    ->numeric()
                    ->default(0),
                Toggle::make('open')
                    ->required(),
                TextInput::make('applied_to_entries'),
                Toggle::make('fully_applied')
                    ->required(),
                TextInput::make('currency_code')
                    ->required()
                    ->default('USD'),
                TextInput::make('original_debit_amount')
                    ->required()
                    ->numeric()
                    ->default(0),
                TextInput::make('original_credit_amount')
                    ->required()
                    ->numeric()
                    ->default(0),
                TextInput::make('currency_factor')
                    ->required()
                    ->numeric()
                    ->default(1),
                Select::make('general_business_posting_group_id')
                    ->relationship('generalBusinessPostingGroup', 'id'),
                Select::make('customer_posting_group_id')
                    ->relationship('customerPostingGroup', 'id'),
                Select::make('gl_entry_id')
                    ->relationship('glEntry', 'id'),
                TextInput::make('source_id')
                    ->numeric(),
                TextInput::make('source_type'),
                TextInput::make('created_by')
                    ->required()
                    ->numeric(),
                Toggle::make('reversed')
                    ->required(),
                DateTimePicker::make('reversed_at'),
                TextInput::make('reversed_by')
                    ->numeric(),
                TextInput::make('reversal_entry_number'),
                TextInput::make('dimensions'),
            ]);
    }
}
