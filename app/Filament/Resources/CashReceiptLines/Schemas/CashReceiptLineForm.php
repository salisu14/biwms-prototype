<?php

namespace App\Filament\Resources\CashReceiptLines\Schemas;

use App\Models\Customer;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class CashReceiptLineForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Journal Reference')
                    ->columns(2)
                    ->schema([
                        Select::make('journal_line_id')
                            ->label('Journal Line')
                            ->relationship('journalLine', 'document_no')
                            ->searchable()
                            ->preload()
                            ->required()
                            ->helperText('Select the base journal line (document date, G/L account, dimensions).'),
                    ]),

                Section::make('Customer Payment')
                    ->columns(2)
                    ->schema([
                        Select::make('customer_id')
                            ->label('Customer')
                            ->relationship('customer', 'name')
                            ->required()
                            ->searchable()
                            ->preload()
                            ->getOptionLabelFromRecordUsing(fn (Customer $record) => "{$record->no} - {$record->name}")
                            ->live()
                            ->afterStateUpdated(fn ($state, $set) => $set('customer_no', optional(Customer::find($state))->no)),

                        TextInput::make('customer_no')
                            ->label('Customer No.')
                            ->readOnly()
                            ->maxLength(50),

                        TextInput::make('amount_received')
                            ->label('Amount Received')
                            ->required()
                            ->numeric()
                            ->prefix('$'),

                        TextInput::make('amount_received_lcy')
                            ->label('Amount (LCY)')
                            ->numeric()
                            ->prefix('$')
                            ->helperText('In local currency.'),

                        TextInput::make('remaining_amount')
                            ->label('Unapplied Amount')
                            ->numeric()
                            ->prefix('$')
                            ->readOnly()
                            ->helperText('Auto-calculated after applying entries.'),
                    ]),

                Section::make('Apply to Document')
                    ->columns(2)
                    ->schema([
                        Select::make('applies_to_doc_type')
                            ->label('Applies-to Doc. Type')
                            ->options([
                                'Invoice' => 'Invoice',
                                'Credit Memo' => 'Credit Memo',
                                'Payment' => 'Payment',
                                'Refund' => 'Refund',
                            ])
                            ->native(false)
                            ->helperText('Leave blank to apply on-account.'),

                        TextInput::make('applies_to_doc_no')
                            ->label('Applies-to Doc. No.')
                            ->maxLength(50),

                        TextInput::make('applies_to_amount')
                            ->label('Applied Amount')
                            ->numeric()
                            ->prefix('$'),
                    ]),

                Section::make('Bank & Payment Method')
                    ->columns(2)
                    ->schema([
                        Select::make('bank_account_id')
                            ->label('Bank Account')
                            ->relationship('bankAccount', 'account_name')
                            ->required()
                            ->searchable()
                            ->preload(),

                        Select::make('payment_method_code')
                            ->label('Payment Method')
                            ->options([
                                'Cash' => 'Cash',
                                'Check' => 'Check',
                                'Bank Transfer' => 'Bank Transfer',
                                'Credit Card' => 'Credit Card',
                                'Electronic' => 'Electronic',
                            ])
                            ->native(false)
                            ->live(),

                        TextInput::make('check_no')
                            ->label('Check No.')
                            ->maxLength(50)
                            ->visible(fn ($get) => $get('payment_method_code') === 'Check'),

                        DatePicker::make('check_date')
                            ->label('Check Date')
                            ->native(false)
                            ->visible(fn ($get) => $get('payment_method_code') === 'Check'),

                        Toggle::make('calculate_vat')
                            ->label('Calculate VAT')
                            ->default(false)
                            ->inline(false),

                        Toggle::make('exported_to_payment_jnl')
                            ->label('Exported to Payment Journal')
                            ->default(false)
                            ->inline(false)
                            ->readOnly(),
                    ]),
            ]);
    }
}
