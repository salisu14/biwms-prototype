<?php

namespace App\Filament\Resources\VatMasters\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class VatMasterForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('code')
                    ->required(),
                TextInput::make('description')
                    ->required(),
                TextInput::make('purchase_account_number')
                    ->required(),
                TextInput::make('sales_account_number')
                    ->required(),
                TextInput::make('percentage')
                    ->required()
                    ->numeric()
                    ->default(0),
            ]);
    }
}
