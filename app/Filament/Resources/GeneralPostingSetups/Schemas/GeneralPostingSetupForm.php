<?php

namespace App\Filament\Resources\GeneralPostingSetups\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class GeneralPostingSetupForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('code')
                    ->required(),
                TextInput::make('description')
                    ->required(),
                TextInput::make('sales_account')
                    ->required(),
                TextInput::make('sales_credit_account'),
                TextInput::make('sales_discount_account'),
                TextInput::make('purchase_account')
                    ->required(),
                TextInput::make('purchase_credit_account'),
                TextInput::make('purchase_discount_account'),
                TextInput::make('cogs_account')
                    ->required(),
                TextInput::make('purchase_variance_account'),
                Toggle::make('is_active')
                    ->required(),
            ]);
    }
}
