<?php

namespace App\Filament\Resources\PayrollPeriods\Schemas;

use App\Enums\PayrollPeriodStatus;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class PayrollPeriodForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Period Duration')
                    ->description('Define the start and end dates for this payroll cycle.')
                    ->columns(2)
                    ->schema([
                        DatePicker::make('start_date')
                            ->label('Start Date')
                            ->required()
                            ->native(false)
                            ->reactive()
                            ->afterStateUpdated(fn ($state, callable $set) => $set('end_date', \Carbon\Carbon::parse($state)->endOfMonth()->toDateString())),

                        DatePicker::make('end_date')
                            ->label('End Date')
                            ->required()
                            ->native(false)
                            ->afterOrEqual('start_date'),

                        DatePicker::make('payment_date')
                            ->label('Scheduled Payment Date')
                            ->required()
                            ->native(false)
                            ->placeholder('Date when employees receive pay')
                            ->columnSpanFull(),
                    ]),

                Section::make('Status & Settings')
                    ->description('Manage the lifecycle and visibility of this period.')
                    ->columns(2)
                    ->schema([
                        Select::make('status')
                            ->label('Period Status')
                            ->options(PayrollPeriodStatus::class)
                            ->enum(PayrollPeriodStatus::class)
                            ->default(PayrollPeriodStatus::OPEN)
                            ->required()
                            ->native(false),

                        Toggle::make('is_current')
                            ->label('Mark as Current Period')
                            ->helperText('Only one period should be marked as current at a time.')
                            ->default(false)
                            ->inline(false),
                    ]),
            ]);
    }
}
