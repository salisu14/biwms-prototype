<?php

namespace App\Filament\Resources\SocialSecurityTiers\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class SocialSecurityTierForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('tier_code')
                    ->required()
                    ->maxLength(20),
                TextInput::make('code')
                    ->maxLength(50),
                TextInput::make('from_salary')
                    ->required()
                    ->numeric(),
                TextInput::make('to_salary')
                    ->numeric(),
                TextInput::make('employee_rate')
                    ->required()
                    ->numeric()
                    ->suffix('%'),
                TextInput::make('employer_rate')
                    ->required()
                    ->numeric()
                    ->suffix('%'),
                TextInput::make('max_base')
                    ->numeric()
                    ->helperText('Maximum salary base for calculation'),
                TextInput::make('employee_max_amount')
                    ->numeric()
                    ->helperText('Maximum contribution amount for employee'),
                TextInput::make('employer_max_amount')
                    ->numeric()
                    ->helperText('Maximum contribution amount for employer'),
            ]);
    }
}
