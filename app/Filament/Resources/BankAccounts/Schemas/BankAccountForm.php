<?php

namespace App\Filament\Resources\BankAccounts\Schemas;

use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class BankAccountForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('account_code')
                    ->required(),
                TextInput::make('account_name')
                    ->required(),
                TextInput::make('bank_name')
                    ->required(),
                TextInput::make('bank_branch'),
                TextInput::make('account_number')
                    ->required(),
                TextInput::make('routing_number'),
                TextInput::make('swift_code'),
                TextInput::make('iban'),
                Select::make('gl_account_id')
                    ->relationship('glAccount', 'name')
                    ->required(),
                TextInput::make('currency_code')
                    ->required()
                    ->default('USD'),
                TextInput::make('account_type')
                    ->required()
                    ->default('CHECKING'),
                TextInput::make('current_balance')
                    ->required()
                    ->numeric()
                    ->default(0),
                TextInput::make('available_balance')
                    ->required()
                    ->numeric()
                    ->default(0),
                DatePicker::make('last_reconciliation_date'),
                TextInput::make('last_reconciliation_balance')
                    ->numeric(),
                TextInput::make('next_check_number'),
                TextInput::make('check_form_id'),
                Toggle::make('active')
                    ->required(),
                Toggle::make('allow_payments')
                    ->required(),
                Toggle::make('allow_receipts')
                    ->required(),
            ]);
    }
}
