<?php

namespace App\Filament\Resources\VendorLedgerEntries\Schemas;

use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class VendorLedgerEntryForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Document Details')
                    ->schema([
                        Grid::make(3)
                            ->schema([
                                TextInput::make('entry_number')
                                    ->label('Entry No.')
                                    ->required()
                                    ->numeric()
                                    ->disabled()
                                    ->dehydrated()
                                    ->default(0), // System generated

                                Select::make('vendor_id')
                                    ->label('Vendor')
                                    ->relationship('vendor', 'vendor_name')
                                    ->searchable()
                                    ->preload()
                                    ->required(),

                                Select::make('document_type')
                                    ->label('Document Type')
                                    ->options([
                                        'PURCHASE_INVOICE' => 'Purchase Invoice',
                                        'PAYMENT' => 'Payment',
                                        'BANK_TRANSFER' => 'Bank Transfer',
                                        'PURCHASE_CREDIT_MEMO' => 'Credit Memo',
                                        'ADJUSTMENT' => 'Adjustment',
                                    ])
                                    ->required()
                                    ->native(false),
                            ]),
                        Grid::make(3)
                            ->schema([
                                TextInput::make('document_number')
                                    ->label('Document No.')
                                    ->required()
                                    ->maxLength(50),

                                TextInput::make('external_document_number')
                                    ->label('Ext. Doc. No. / Vendor Ref')
                                    ->maxLength(50),

                                TextInput::make('description')
                                    ->required()
                                    ->maxLength(255)
                                    ->columnSpan(2),
                            ]),
                    ]),

                Section::make('Financials')
                    ->schema([
                        Grid::make(4)
                            ->schema([
                                DatePicker::make('posting_date')
                                    ->required()
                                    ->default(now())
                                    ->native(false)
                                    ->live(),

                                DatePicker::make('document_date')
                                    ->required()
                                    ->native(false),

                                DatePicker::make('due_date')
                                    ->native(false)
                                    ->label('Due Date'),

                                Select::make('currency_code')
                                    ->label('Currency')
                                    ->options(['NGN' => 'NGN (₦)', 'USD' => 'USD ($)', 'EUR' => 'EUR (€)', 'GBP' => 'GBP (£)'])
                                    ->required()
                                    ->default('NGN')
                                    ->native(false)
                                    ->live(),
                            ]),
                        Grid::make(4)
                            ->schema([
                                TextInput::make('debit_amount')
                                    ->label('Debit')
                                    ->numeric()
                                    ->prefix('₦')
                                    ->default(0)
                                    ->disabled()
                                    ->dehydrated(),

                                TextInput::make('credit_amount')
                                    ->label('Credit')
                                    ->numeric()
                                    ->prefix('₦')
                                    ->default(0)
                                    ->disabled()
                                    ->dehydrated(),

                                TextInput::make('amount')
                                    ->label('Net Amount')
                                    ->numeric()
                                    ->prefix('₦')
                                    ->disabled()
                                    ->dehydrated(),

                                TextInput::make('running_balance')
                                    ->label('Running Balance')
                                    ->numeric()
                                    ->prefix('₦')
                                    ->disabled()
                                    ->dehydrated(),
                            ]),
                        Grid::make(3)
                            ->schema([
                                TextInput::make('remaining_amount')
                                    ->label('Remaining Amount')
                                    ->numeric()
                                    ->prefix('₦')
                                    ->disabled()
                                    ->dehydrated(),

                                Toggle::make('open')
                                    ->label('Open Entry')
                                    ->inline(false)
                                    ->disabled()
                                    ->dehydrated(),

                                Toggle::make('fully_applied')
                                    ->label('Fully Applied')
                                    ->inline(false)
                                    ->disabled()
                                    ->dehydrated(),
                            ]),
                    ]),

                Section::make('Application & Discounts')
                    ->schema([
                        Grid::make(3)
                            ->schema([
                                TextInput::make('payment_terms_code')
                                    ->label('Payment Terms Code'),

                                TextInput::make('payment_discount_percent')
                                    ->label('Payment Discount %')
                                    ->numeric()
                                    ->suffix('%')
                                    ->step(0.01),

                                DatePicker::make('payment_discount_due_date')
                                    ->label('Pmt. Discount Date')
                                    ->native(false),
                            ]),
                        Grid::make(2)
                            ->schema([
                                TextInput::make('retainage_amount')
                                    ->label('Retainage Amount')
                                    ->numeric()
                                    ->prefix('₦'),

                                DatePicker::make('retainage_due_date')
                                    ->label('Retainage Due Date')
                                    ->native(false),
                            ]),
                        Textarea::make('applied_to_entries')
                            ->label('Applied To Entries (JSON)')
                            ->json()
                            ->disabled()
                            ->dehydrated()
                            ->columnSpanFull()
                            ->helperText('System tracked application data.'),
                    ])->visibleOn('edit'), // Mostly irrelevant for create, useful context for edit

                Section::make('Audit & Reversal')
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                Select::make('general_business_posting_group_id')
                                    ->label('Gen. Bus. Posting Group')
                                    ->relationship('generalBusinessPostingGroup', 'code')
                                    ->searchable()
                                    ->preload(),

                                Select::make('vendor_posting_group_id')
                                    ->label('Vendor Posting Group')
                                    ->relationship('vendorPostingGroup', 'code')
                                    ->searchable()
                                    ->preload(),
                            ]),
                        Grid::make(2)
                            ->schema([
                                Toggle::make('reversed')
                                    ->label('Reversed')
                                    ->inline(false)
                                    ->disabled()
                                    ->dehydrated(),

                                DateTimePicker::make('reversed_at')
                                    ->disabled(),
                            ]),
                        Textarea::make('comment')
                            ->columnSpanFull(),
                    ])->collapsible(),
            ]);
    }
}
