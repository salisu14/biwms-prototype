<?php

namespace App\Filament\Resources\SalespersonPurchasers\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class SalespersonPurchaserForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('General Information')
                    ->schema([
                        TextInput::make('code')
                            ->label('Code')
                            ->required()
                            ->unique(ignoreRecord: true)
                            ->maxLength(20),
                        TextInput::make('name')
                            ->required()
                            ->maxLength(100),
                        TextInput::make('commission_pct')
                            ->label('Commission %')
                            ->numeric()
                            ->default(0)
                            ->prefix('%'),
                        Toggle::make('is_active')
                            ->default(true)
                            ->onColor('success'),
                    ])->columns(2),

                Section::make('Contact & HR')
                    ->schema([
                        TextInput::make('phone_no')
                            ->tel()
                            ->maxLength(30),
                        TextInput::make('email')
                            ->email()
                            ->maxLength(80),
                        Select::make('employee_id')
                            ->label('Link to Employee')
                            ->relationship('employee', 'first_name')
                            ->getOptionLabelFromRecordUsing(fn ($record) => "{$record->first_name} {$record->last_name} ({$record->employee_number})")
                            ->searchable()
                            ->preload(),
                    ])->columns(2),
            ]);
    }
}
