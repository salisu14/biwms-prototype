<?php

namespace App\Filament\Resources\PayrollPostingGroups\Schemas;

use Filament\Schemas\Schema;

class PayrollPostingGroupForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                \Filament\Forms\Components\TextInput::make('code')
                    ->required()
                    ->maxLength(20)
                    ->unique(ignoreRecord: true),
                \Filament\Forms\Components\TextInput::make('description')
                    ->required()
                    ->maxLength(255),
                \Filament\Forms\Components\Select::make('salaries_account_id')
                    ->relationship('salariesAccount', 'name')
                    ->label('Salaries Account')
                    ->required(),
                \Filament\Forms\Components\Select::make('wages_account_id')
                    ->relationship('wagesAccount', 'name')
                    ->label('Wages Account'),
                \Filament\Forms\Components\Select::make('social_security_account_id')
                    ->relationship('socialSecurityAccount', 'name')
                    ->label('Social Security Account')
                    ->required(),
                \Filament\Forms\Components\Select::make('tax_payable_account_id')
                    ->relationship('taxPayableAccount', 'name')
                    ->label('Tax Payable Account')
                    ->required(),
                \Filament\Forms\Components\Select::make('net_pay_account_id')
                    ->relationship('netPayAccount', 'name')
                    ->label('Net Pay (Liability) Account')
                    ->required(),
            ]);
    }
}
