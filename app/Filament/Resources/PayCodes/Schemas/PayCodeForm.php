<?php

namespace App\Filament\Resources\PayCodes\Schemas;

use App\Enums\CalculationMethod;
use App\Enums\PayCodeType;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class PayCodeForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('code')
                    ->required()
                    ->maxLength(20)
                    ->unique(ignoreRecord: true),
                TextInput::make('name')
                    ->required()
                    ->maxLength(255),
                Select::make('type')
                    ->options(PayCodeType::class)
                    ->required(),
                Select::make('calculation_method')
                    ->options(CalculationMethod::class)
                    ->required(),
                TextInput::make('default_amount')
                    ->numeric()
                    ->label('Default Amount / %'),
                Select::make('gl_account_id')
                    ->relationship('glAccount', 'name')
                    ->label('GL Account Mapping')
                    ->required(),
            ]);
    }
}
