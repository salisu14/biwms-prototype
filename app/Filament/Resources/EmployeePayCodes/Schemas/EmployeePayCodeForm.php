<?php

namespace App\Filament\Resources\EmployeePayCodes\Schemas;

use Filament\Schemas\Schema;

class EmployeePayCodeForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                \Filament\Forms\Components\Select::make('employee_id')
                    ->relationship('employee', 'employee_number')
                    ->required(),
                \Filament\Forms\Components\Select::make('pay_code_id')
                    ->relationship('payCode', 'name')
                    ->required(),
                \Filament\Forms\Components\TextInput::make('amount')
                    ->numeric()
                    ->label('Override Amount'),
                \Filament\Forms\Components\TextInput::make('percentage')
                    ->numeric()
                    ->label('Override Percentage (%)'),
                \Filament\Forms\Components\DatePicker::make('effective_date')
                    ->required(),
                \Filament\Forms\Components\DatePicker::make('end_date'),
            ]);
    }
}
