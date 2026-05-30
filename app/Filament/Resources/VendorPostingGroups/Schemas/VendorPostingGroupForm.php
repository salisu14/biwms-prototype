<?php

namespace App\Filament\Resources\VendorPostingGroups\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class VendorPostingGroupForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('General Information')
                    ->schema([
                        TextInput::make('code')
                            ->required()
                            ->maxLength(20)
                            ->unique(ignoreRecord: true),
                        TextInput::make('description')
                            ->required()
                            ->maxLength(255),
                        Toggle::make('blocked')
                            ->default(false)
                            ->columnSpanFull(),
                    ])->columns(2),

                Section::make('Account Mappings')
                    ->description('Select the Chart of Accounts entries for this posting group.')
                    ->schema([
                        Select::make('payables_account_id')
                            ->relationship('payablesAccount', 'name')
                            ->searchable()
                            ->preload()
                            ->required(),
                        Select::make('payment_disc_debit_account_id')
                            ->relationship('paymentDiscDebitAccount', 'name')
                            ->searchable()
                            ->preload(),
                        Select::make('payment_disc_credit_account_id')
                            ->relationship('paymentDiscCreditAccount', 'name')
                            ->searchable()
                            ->preload(),
                        Select::make('invoice_rounding_account_id')
                            ->relationship('invoiceRoundingAccount', 'name')
                            ->searchable()
                            ->preload(),
                    ])->columns(2),
            ]);
    }
}
