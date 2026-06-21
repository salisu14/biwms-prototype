<?php

namespace App\Filament\Resources\SalespersonPurchasers\Schemas;

use App\Models\SalespersonPurchaser;
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
                            ->label('code')
                            ->required()
                            ->unique(ignoreRecord: true)
                            ->maxLength(20)
                            // Lock the field if the record already exists in the database
                            ->disabled(fn (?SalespersonPurchaser $record) => $record !== null)
                            // Ensure the value is still sent to the database during creation
                            ->dehydrated()
                            ->extraInputAttributes(['style' => 'text-transform: uppercase'])
                            ->helperText('The code cannot be changed once the Sales person purchaser is created.'),

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
