<?php

namespace App\Filament\Resources\CustomerPostingGroups\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class CustomerPostingGroupForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('code')
                    ->required(),
                TextInput::make('description')
                    ->required(),
                Select::make('receivables_account_id')
                    ->relationship('receivablesAccount', 'name')
                    ->required(),
                TextInput::make('payment_disc_debit_account_id')
                    ->numeric(),
                TextInput::make('payment_disc_credit_account_id')
                    ->numeric(),
                TextInput::make('invoice_rounding_account_id')
                    ->numeric(),
                TextInput::make('debit_rounding_account_id')
                    ->numeric(),
                TextInput::make('credit_rounding_account_id')
                    ->numeric(),
                Toggle::make('blocked')
                    ->required(),
            ]);
    }
}
