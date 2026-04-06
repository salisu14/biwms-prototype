<?php

namespace App\Filament\Resources\PriceChangeTemplates\Schemas;

use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;

class PriceChangeTemplateForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('General Information')
                    ->columns(2)
                    ->schema([
                        TextInput::make('name')
                            ->required()
                            ->maxLength(255)
                            ->columnSpanFull(),

                        Select::make('status')
                            ->options([
                                'draft' => 'Draft',
                                'approved' => 'Approved',
                                'applied' => 'Applied',
                            ])
                            ->default('draft')
                            ->required()
                            ->native(false),

                        TextInput::make('rounding')
                            ->label('Rounding Precision')
                            ->helperText('Number of decimal places (e.g., 2 for 0.00)')
                            ->numeric()
                            ->default(2),
                    ]),

                Section::make('Calculation Logic')
                    ->columns(3)
                    ->schema([
                        Select::make('base')
                            ->label('Base Price')
                            ->options([
                                'cost' => 'Current Cost',
                                'price' => 'Current Retail Price',
                            ])
                            ->required()
                            ->native(false),

                        Select::make('adjustment_type')
                            ->label('Price Logic')
                            ->options([
                                'increase' => 'Increase (%)',
                                'decrease' => 'Decrease (%)',
                                'fixed' => 'Fixed Price (Set Value)',
                            ])
                            ->required()
                            ->live()
                            ->native(false)
                            ->hint(fn ($state) => match ($state) {
                                'increase' => 'Adds a percentage to the current base price.',
                                'decrease' => 'Subtracts a percentage from the current base price.',
                                'fixed' => 'Ignores current price and sets a specific new amount.',
                                default => null,
                            })
                            ->afterStateUpdated(fn (Set $set) => $set('value', null)),

                        TextInput::make('value')
                            ->label(fn ($get) => $get('adjustment_type') === 'percentage' ? 'Percentage Value' : 'Fixed Amount')
                            ->required()
                            ->numeric()
                            ->prefix(fn ($get) => $get('adjustment_type') === 'percentage' ? null : '₦')
                            ->suffix(fn ($get) => $get('adjustment_type') === 'percentage' ? '%' : null),
                    ]),

                Section::make('Validity Period')
                    ->columns(2)
                    ->schema([
                        DatePicker::make('effective_from')
                            ->label('Starts On')
                            ->native(false),
                        DatePicker::make('effective_to')
                            ->label('Ends On')
                            ->native(false)
                            ->after('effective_from'),
                    ]),
            ]);
    }
}
