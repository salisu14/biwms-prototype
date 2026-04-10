<?php

namespace App\Filament\Resources\PayCodes\Schemas;

use Filament\Schemas\Schema;

class PayCodeForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                \Filament\Forms\Components\TextInput::make('code')
                    ->required()
                    ->maxLength(20)
                    ->unique(ignoreRecord: true),
                \Filament\Forms\Components\TextInput::make('name')
                    ->required()
                    ->maxLength(255),
                \Filament\Forms\Components\Select::make('type')
                    ->options(\App\Enums\PayCodeType::class)
                    ->required(),
                \Filament\Forms\Components\Select::make('calculation_method')
                    ->options(\App\Enums\CalculationMethod::class)
                    ->required(),
                \Filament\Forms\Components\TextInput::make('default_amount')
                    ->numeric()
                    ->label('Default Amount / %'),
                \Filament\Forms\Components\Select::make('gl_account_id')
                    ->relationship('glAccount', 'name')
                    ->label('GL Account Mapping')
                    ->required(),
            ]);
    }
}
