<?php

namespace App\Filament\Resources\AccountSchedules\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class AccountScheduleForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('General Information')
                    ->description('Define the primary identification for this financial report layout.')
                    ->columns(2)
                    ->schema([
                        TextInput::make('name')
                            ->label('Schedule Name')
                            ->required()
                            ->unique(ignoreRecord: true)
                            ->maxLength(50)
                            ->placeholder('e.g., BALANCE_SHEET')
                            ->extraInputAttributes(['style' => 'text-transform: uppercase']),

                        TextInput::make('description')
                            ->label('Description')
                            ->maxLength(255)
                            ->placeholder('e.g., Standard Corporate Balance Sheet'),
                    ]),
            ]);
    }
}
