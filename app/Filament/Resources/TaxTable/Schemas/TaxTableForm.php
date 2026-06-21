<?php

namespace App\Filament\Resources\TaxTable\Schemas;

use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class TaxTableForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Table Identification')
                    ->description('General identification and effective dating for this tax schedule.')
                    ->columns(2)
                    ->schema([
                        TextInput::make('name')
                            ->label('Table Name')
                            ->required()
                            ->maxLength(255)
                            ->placeholder('e.g., Annual Income Tax 2026')
                            ->columnSpanFull(),

                        TextInput::make('jurisdiction')
                            ->label('Jurisdiction')
                            ->required()
                            ->maxLength(50)
                            ->placeholder('e.g., Federal, State'),

                        DatePicker::make('effective_date')
                            ->label('Effective Date')
                            ->required()
                            ->native(false),
                    ]),

                Section::make('Location Mapping')
                    ->description('Geographic scope for this tax table.')
                    ->columns(2)
                    ->schema([
                        TextInput::make('country_code')
                            ->label('Country Code')
                            ->placeholder('e.g., US, NG')
                            ->maxLength(10),

                        TextInput::make('state_code')
                            ->label('State / Province Code')
                            ->placeholder('e.g., NY, LAG')
                            ->maxLength(10),
                    ]),
            ]);
    }
}
