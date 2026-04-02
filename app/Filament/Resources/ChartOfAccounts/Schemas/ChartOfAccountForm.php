<?php

namespace App\Filament\Resources\ChartOfAccounts\Schemas;

use App\Enums\AccountCategory;
use App\Enums\AccountType;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class ChartOfAccountForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('account_number')
                    ->required(),
                TextInput::make('name')
                    ->required(),
                Select::make('account_type')
                    ->options(AccountType::class)
                    ->default('REVENUE')
                    ->required(),
                Select::make('account_category')
                    ->options(AccountCategory::class)
                    ->default('RECEIVABLE')
                    ->required(),
                TextInput::make('balance')
                    ->required()
                    ->numeric()
                    ->default(0),
                Toggle::make('direct_posting')
                    ->required(),
                Toggle::make('blocked')
                    ->required(),
                TextInput::make('parent_account_id')
                    ->numeric(),
            ]);
    }
}
