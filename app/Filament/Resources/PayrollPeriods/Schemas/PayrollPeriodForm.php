<?php

namespace App\Filament\Resources\PayrollPeriods\Schemas;

use Filament\Schemas\Schema;

class PayrollPeriodForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                \Filament\Forms\Components\DatePicker::make('start_date')
                    ->required(),
                \Filament\Forms\Components\DatePicker::make('end_date')
                    ->required(),
                \Filament\Forms\Components\DatePicker::make('payment_date')
                    ->label('Payment Date')
                    ->required(),
                \Filament\Forms\Components\Select::make('status')
                    ->options(\App\Enums\PayrollPeriodStatus::class)
                    ->required(),
                \Filament\Forms\Components\Toggle::make('is_current')
                    ->label('Is Current Period')
                    ->default(false),
            ]);
    }
}
