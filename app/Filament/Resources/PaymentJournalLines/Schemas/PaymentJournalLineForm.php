<?php

namespace App\Filament\Resources\PaymentJournalLines\Schemas;

use App\Models\Vendor;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class PaymentJournalLineForm
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
                            ->required(),
                    ]),

                Section::make('Vendor Payment')
                    ->columns(2)
                    ->schema([
                        Select::make('vendor_id')
                            ->label('Vendor')
                            ->relationship('vendor', 'name')
                            ->required()
                            ->searchable()
                            ->preload()
                            ->getOptionLabelFromRecordUsing(fn (Vendor $record) => "{$record->no} - {$record->name}")
                            ->live()
                            ->afterStateUpdated(fn ($state, $set) => $set('vendor_no', optional(Vendor::find($state))->no)),

                        TextInput::make('vendor_no')
                            ->label('Vendor No.')
                            ->readOnly()
                            ->maxLength(50),

                        TextInput::make('amount_paid')
                            ->label('Amount to Pay')
                            ->required()
                            ->numeric()
                            ->prefix('$'),

                        TextInput::make('amount_paid_lcy')
                            ->label('Amount (LCY)')
                            ->numeric()
                            ->prefix('$'),

                        DatePicker::make('due_date')
                            ->label('Due Date')
                            ->native(false)
                            ->helperText('Invoice due date for prioritisation.'),

                        TextInput::make('remaining_amount')
                            ->label('Unapplied Amount')
                            ->numeric()
                            ->prefix('$')
                            ->readOnly(),
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
                            ->native(false),

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

                        Toggle::make('exported_to_payment_jnl')
                            ->label('Exported to Bank File')
                            ->default(false)
                            ->inline(false),

                        Toggle::make('payment_processed')
                            ->label('Payment Processed')
                            ->default(false)
                            ->inline(false),
                    ]),
            ]);
    }
}
