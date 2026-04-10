<?php

namespace App\Filament\Resources\CurrencyAdjustmentLedgers\Schemas;

use App\Enums\CurrencyAdjustmentType;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class CurrencyAdjustmentLedgerForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Grid::make(3)->schema([
                    Section::make('Entry Identification')
                        ->schema([
                            Grid::make(2)->schema([
                                Select::make('currency_id')
                                    ->relationship('currency', 'code')
                                    ->required()
                                    ->disabled(),
                                Select::make('adjustment_type')
                                    ->options(CurrencyAdjustmentType::class)
                                    ->required()
                                    ->disabled(),
                            ]),
                            Grid::make(2)->schema([
                                TextInput::make('document_type')->required(),
                                TextInput::make('document_no')->label('Document No.')->required(),
                            ]),
                            DatePicker::make('posting_date')->required(),
                        ])->columnSpan(2),

                    Section::make('Audit')
                        ->schema([
                            Select::make('created_by')
                                ->relationship('createdBy', 'name')
                                ->disabled(),
                            TextInput::make('created_at')
                                ->label('Entry Date')
                                ->disabled()
                                ->placeholder(fn($record) => $record?->created_at?->toDateTimeString()),
                        ])->columnSpan(1),
                ]),

                Section::make('Financial Details')
                    ->schema([
                        Grid::make(2)->schema([
                            Section::make('Exchange Rates')
                                ->schema([
                                    TextInput::make('original_exch_rate')
                                        ->label('Original Rate')
                                        ->numeric()
                                        ->step(0.000001)
                                        ->disabled(),
                                    TextInput::make('new_exch_rate')
                                        ->label('Adjustment Rate')
                                        ->numeric()
                                        ->step(0.000001)
                                        ->disabled(),
                                ])->columnSpan(1),

                            Section::make('Valuations')
                                ->schema([
                                    TextInput::make('original_amount')
                                        ->numeric()
                                        ->disabled(),
                                    TextInput::make('adjusted_amount')
                                        ->numeric()
                                        ->disabled(),
                                    TextInput::make('adjustment_amount')
                                        ->label('G/L Impact')
                                        ->numeric()
                                        ->extraInputAttributes(['class' => 'font-bold'])
                                        ->disabled(),
                                ])->columnSpan(1),
                        ]),

                        Select::make('adjustment_account_id')
                            ->label('G/L Adjustment Account')
                            ->relationship('adjustmentAccount', 'name')
                            ->getOptionLabelFromRecordUsing(fn($record) => "{$record->code} - {$record->name}")
                            ->required()
                            ->columnSpanFull(),
                    ]),

                Section::make('Source Ledger References')
                    ->description('Links to the original sub-ledger entries.')
                    ->collapsed()
                    ->schema([
                        Grid::make(2)->schema([
                            Select::make('vendor_ledger_entry_id')
                                ->label('Vendor Ledger Entry')
                                ->relationship('vendorLedgerEntry', 'id')
                                ->disabled(),
                            Select::make('customer_ledger_entry_id')
                                ->label('Customer Ledger Entry')
                                ->relationship('customerLedgerEntry', 'id')
                                ->disabled(),
                            Select::make('bank_account_ledger_entry_id')
                                ->label('Bank Account Ledger')
                                ->relationship('bankAccountLedgerEntry', 'id')
                                ->disabled(),
                            Select::make('gl_entry_id')
                                ->label('Related G/L Entry')
                                ->relationship('glEntry', 'id')
                                ->disabled(),
                        ]),
                    ]),

                Section::make('Description')
                    ->schema([
                        Textarea::make('description')->rows(2)->columnSpanFull(),
                    ]),
            ])
            ->disabled(); // Since it's a ledger, it should be read-only
    }
}
