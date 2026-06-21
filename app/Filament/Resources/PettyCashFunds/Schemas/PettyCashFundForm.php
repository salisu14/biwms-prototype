<?php

namespace App\Filament\Resources\PettyCashFunds\Schemas;

use App\Models\ChartOfAccount;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class PettyCashFundForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Fund Details')
                    ->description('Identify the petty cash fund and assign a custodian.')
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                TextInput::make('code')
                                    ->label('Fund Code')
                                    ->required()
                                    ->unique(ignoreRecord: true)
                                    ->maxLength(20)
                                    ->extraInputAttributes(['style' => 'text-transform: uppercase']),
                                TextInput::make('name')
                                    ->label('Fund Name')
                                    ->required()
                                    ->maxLength(100),
                                TextInput::make('location')
                                    ->label('Location')
                                    ->maxLength(100)
                                    ->placeholder('e.g., Head Office Reception'),
                                Select::make('custodian_id')
                                    ->label('Custodian')
                                    ->relationship('custodian', 'name')
                                    ->searchable()
                                    ->preload()
                                    ->required()
                                    ->helperText('The user responsible for managing this cash box.'),
                            ]),
                    ]),

                Section::make('Financial Setup')
                    ->description('Define the imprest amount and currency. The current balance is managed by the system.')
                    ->schema([
                        Grid::make(3)
                            ->schema([
                                Select::make('currency')
                                    ->label('Currency')
                                    ->options(['NGN' => 'NGN (₦)', 'USD' => 'USD ($)', 'EUR' => 'EUR (€)', 'GBP' => 'GBP (£)'])
                                    ->required()
                                    ->default('NGN')
                                    ->native(false)
                                    ->live(),
                                TextInput::make('imprest_amount')
                                    ->label('Imprest Amount (Cap)')
                                    ->required()
                                    ->numeric()
                                    ->prefix(fn ($get) => match ($get('currency')) { 'USD' => '$', 'EUR' => '€', 'GBP' => '£', default => '₦' })
                                    ->minValue(0)
                                    ->step(0.01)
                                    ->live(onBlur: true)
                                    ->afterStateUpdated(function ($state, $set, $context) {
                                        // Auto-fill current balance with imprest amount on creation
                                        if ($context === 'create') {
                                            $set('current_balance', $state);
                                        }
                                    }),
                                TextInput::make('current_balance')
                                    ->label('Current Balance')
                                    ->numeric()
                                    ->required()
                                    ->default(0)
                                    ->prefix(fn ($get) => match ($get('currency')) { 'USD' => '$', 'EUR' => '€', 'GBP' => '£', default => '₦' })
                                    ->disabled(fn (string $context) => $context === 'edit') // Editable on create, read-only on edit
                                    ->dehydrated(true) // FIX: Always save the value to the database
                                    ->helperText('Auto-filled with Imprest Amount on creation. Managed by transactions afterward.'),
                            ]),

                        Select::make('chart_of_account_id')
                            ->label('Cash/Bank Account')
                            ->options(ChartOfAccount::where('structural_type', \App\Enums\AccountStructuralType::POSTING)->pluck('name', 'id'))
                            ->searchable()
                            ->required()
                            ->helperText('The G/L Account representing this physical cash fund.'),
                    ]),

                Section::make('Status & Notes')
                    ->schema([
                        Toggle::make('is_active')
                            ->label('Active Fund')
                            ->default(true)
                            ->inline(false)
                            ->helperText('Inactive funds cannot process new vouchers.'),
                        Textarea::make('notes')
                            ->columnSpanFull()
                            ->maxLength(65535),
                    ]),
            ]);
    }
}
