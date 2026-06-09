<?php

namespace App\Filament\Resources\BankAccountLedgerEntries\Schemas;

use App\Enums\BankAccountLedgerEntryType;
use App\Enums\CheckType;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class BankAccountLedgerEntryForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Entry Details')
                    ->schema([
                        Grid::make(3)
                            ->schema([
                                TextInput::make('entry_number')
                                    ->required()
                                    ->maxLength(50),
                                Select::make('bank_account_id')
                                    ->relationship('bankAccount', 'bank_name') // Display name, not ID
                                    ->searchable()
                                    ->preload()
                                    ->required(),
                                TextInput::make('bank_account_no')
                                    ->label('Bank Acc No.')
                                    ->maxLength(30),
                            ]),
                        Grid::make(3)
                            ->schema([
                                DatePicker::make('posting_date')->required()->native(false),
                                DatePicker::make('document_date')->native(false),
                                DatePicker::make('due_date')->native(false),
                            ]),
                        Grid::make(3)
                            ->schema([
                                TextInput::make('document_type')->maxLength(50),
                                TextInput::make('document_no')->required()->maxLength(50),
                                TextInput::make('external_document_no')->label('Ext. Doc No.')->maxLength(50),
                            ]),
                        Textarea::make('description')->required()->columnSpanFull()->maxLength(255),
                        Textarea::make('description_2')->columnSpanFull()->maxLength(255),
                    ])->columns(1),

                Section::make('Amounts & Currency')
                    ->schema([
                        Grid::make(3)
                            ->schema([
                                Select::make('entry_type')
                                    ->options(BankAccountLedgerEntryType::class)
                                    ->required()
                                    ->live()
                                    ->afterStateUpdated(function ($state, callable $set, callable $get) {
                                        // Auto-calculate debit/credit in form based on entry type
                                        $amount = $get('amount') ?? 0;
                                        if (BankAccountLedgerEntryType::tryFrom($state)?->isDebit()) {
                                            $set('debit_amount', abs($amount));
                                            $set('credit_amount', 0);
                                        } elseif (BankAccountLedgerEntryType::tryFrom($state)?->isCredit()) {
                                            $set('debit_amount', 0);
                                            $set('credit_amount', abs($amount));
                                        }
                                    }),
                                TextInput::make('amount')
                                    ->required()
                                    ->numeric()
                                    ->prefix('₦')
                                    ->live()
                                    ->afterStateUpdated(function ($state, callable $set, callable $get) {
                                        $type = $get('entry_type');
                                        if (BankAccountLedgerEntryType::tryFrom($type)?->isDebit()) {
                                            $set('debit_amount', abs($state));
                                            $set('credit_amount', 0);
                                        } elseif (BankAccountLedgerEntryType::tryFrom($type)?->isCredit()) {
                                            $set('debit_amount', 0);
                                            $set('credit_amount', abs($state));
                                        }
                                    }),
                                TextInput::make('currency_code')->label('Currency')->default('NGN'),
                            ]),
                        Grid::make(4)
                            ->schema([
                                TextInput::make('debit_amount')->required()->numeric()->prefix('₦')->disabled()->dehydrated(),
                                TextInput::make('credit_amount')->required()->numeric()->prefix('₦')->disabled()->dehydrated(),
                                TextInput::make('amount_lcy')->label('Amount (LCY)')->required()->numeric()->prefix('₦')->disabled()->dehydrated(),
                                TextInput::make('currency_factor')->required()->numeric()->default(1)->step(0.000001),
                            ]),
                    ]),

                Section::make('Check Details')
                    ->schema([
                        Grid::make(3)
                            ->schema([
                                Select::make('check_type')->options(CheckType::class),
                                TextInput::make('check_no')->maxLength(50),
                                DatePicker::make('check_date')->native(false),
                            ]),
                    ])->visible(fn(callable $get) => $get('entry_type') === BankAccountLedgerEntryType::CHECK->value),

                Section::make('References & Dimensions')
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                Select::make('gl_entry_id')->relationship('glEntry', 'entry_number')->searchable(),
                                Select::make('vendor_ledger_entry_id')->relationship('vendorLedgerEntry', 'entry_number')->searchable(),
                                Select::make('customer_ledger_entry_id')->relationship('customerLedgerEntry', 'entry_number')->searchable(),
                                Select::make('transfer_entry_id')->relationship('transferEntry', 'entry_number')->searchable(),
                            ]),
                        Grid::make(3)
                            ->schema([
                                TextInput::make('source_type')->maxLength(50),
                                TextInput::make('source_id')->numeric(),
                                TextInput::make('source_no')->maxLength(50),
                            ]),
                        Grid::make(2)
                            ->schema([
                                TextInput::make('shortcut_dimension_1_code')->label('Dimension 1'),
                                TextInput::make('shortcut_dimension_2_code')->label('Dimension 2'),
                            ]),
                    ]),
            ]);
    }
}
