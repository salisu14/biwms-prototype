<?php

namespace App\Filament\Resources\BankAccounts\Schemas;

use App\Models\BankAccount;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Schema;

class BankAccountForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Tabs::make('Bank Account Configuration')
                    ->tabs([
                        Tabs\Tab::make('General')
                            ->icon('heroicon-m-building-library')
                            ->schema([
                                Grid::make(3)->schema([
                                    TextInput::make('account_code')
                                        ->label('Account Code')
                                        ->required()
                                        ->unique(ignoreRecord: true)
                                        ->maxLength(50)
                                        // Lock the field if the record already exists in the database
                                        ->disabled(fn (?BankAccount $record) => $record !== null)
                                        // Ensure the value is still sent to the database during creation
                                        ->dehydrated()
                                        ->extraInputAttributes(['style' => 'text-transform: uppercase'])
                                        ->helperText('The code cannot be changed once the Asset is created.'),

                                    TextInput::make('account_name')
                                        ->label('Display Name')
                                        ->required()
                                        ->maxLength(255),
                                    Select::make('account_type')
                                        ->options([
                                            'CHECKING' => 'Checking',
                                            'SAVINGS' => 'Savings',
                                            'CREDIT' => 'Credit Card',
                                            'INVESTMENT' => 'Investment',
                                        ])
                                        ->default('CHECKING')
                                        ->required(),
                                ]),
                                Grid::make(2)->schema([
                                    Section::make('Status & Permissions')
                                        ->schema([
                                            Toggle::make('active')
                                                ->label('Active')
                                                ->default(true)
                                                ->onColor('success'),
                                            Grid::make(2)->schema([
                                                Toggle::make('allow_payments')
                                                    ->label('Allow Payments (AP)'),
                                                Toggle::make('allow_receipts')
                                                    ->label('Allow Receipts (AR)'),
                                            ]),
                                        ])->compact()->inlineLabel(),
                                    Section::make('Bank Identification')
                                        ->schema([
                                            TextInput::make('bank_name')
                                                ->label('Financial Institution')
                                                ->required(),
                                            TextInput::make('bank_branch')
                                                ->label('Branch Details'),
                                        ])->compact(),
                                ]),
                            ]),

                        Tabs\Tab::make('Account Details')
                            ->icon('heroicon-m-credit-card')
                            ->schema([
                                Grid::make(2)->schema([
                                    TextInput::make('account_number')
                                        ->label('Bank Account Number')
                                        ->required()
                                        ->password()
                                        ->revealable(),
                                    TextInput::make('routing_number')
                                        ->label('Routing / Transit Number'),
                                ]),
                                Grid::make(2)->schema([
                                    TextInput::make('iban')
                                        ->label('IBAN')
                                        ->placeholder('International Bank Account Number'),
                                    TextInput::make('swift_code')
                                        ->label('SWIFT / BIC Code'),
                                ]),
                            ]),

                        Tabs\Tab::make('Financials')
                            ->icon('heroicon-m-banknotes')
                            ->schema([
                                Grid::make(2)->schema([
                                    Select::make('gl_account_id')
                                        ->label('Linked G/L Account')
                                        ->relationship('glAccount', 'name')
                                        ->getOptionLabelFromRecordUsing(fn ($record) => "{$record->account_number} - {$record->name}")
                                        ->searchable()
                                        ->preload()
                                        ->required(),
                                    Select::make('currency_id')
                                        ->label('Currency')
                                        ->relationship('currency', 'code')
                                        ->searchable()
                                        ->preload()
                                        ->required(),
                                ]),
                                Section::make('Live Balances')
                                    ->description('These balances are synchronized with the General Ledger.')
                                    ->schema([
                                        Grid::make(2)->schema([
                                            TextInput::make('current_balance')
                                                ->numeric()
                                                ->step(0.0001)
                                                ->disabled()
                                                ->dehydrated()
                                                ->prefix(fn ($record) => $record?->currency?->symbol ?? '$'),
                                            TextInput::make('available_balance')
                                                ->numeric()
                                                ->step(0.0001)
                                                ->disabled()
                                                ->dehydrated()
                                                ->prefix(fn ($record) => $record?->currency?->symbol ?? '$'),
                                        ]),
                                    ])->compact(),
                            ]),

                        Tabs\Tab::make('Reconciliation')
                            ->icon('heroicon-m-arrow-path')
                            ->schema([
                                Grid::make(2)->schema([
                                    DatePicker::make('last_reconciliation_date')
                                        ->label('Last Reconciled On'),
                                    TextInput::make('last_reconciliation_balance')
                                        ->label('Balance at Last Recon')
                                        ->numeric()
                                        ->prefix(fn ($record) => $record?->currency?->symbol ?? '$'),
                                ]),
                            ]),

                        Tabs\Tab::make('Check Printing')
                            ->icon('heroicon-m-printer')
                            ->schema([
                                Grid::make(2)->schema([
                                    TextInput::make('next_check_number')
                                        ->label('Next Check No.')
                                        ->numeric(),
                                    TextInput::make('check_form_id')
                                        ->label('Check Layout ID'),
                                ]),
                            ]),
                    ])->columnSpanFull(),
            ]);
    }
}
