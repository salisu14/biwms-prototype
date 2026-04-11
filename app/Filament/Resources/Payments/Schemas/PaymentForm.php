<?php

namespace App\Filament\Resources\Payments\Schemas;

use App\Models\BankAccount;
use App\Models\Currency;
use App\Models\Customer;
use App\Models\Vendor;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Schema;

class PaymentForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Tabs::make('Payment Details')
                    ->tabs([
                        Tabs\Tab::make('General')
                            ->icon('heroicon-m-document-text')
                            ->schema([
                                Grid::make(3)->schema([
                                    TextInput::make('payment_number')
                                        ->label('Payment No.')
                                        ->required()
                                        ->unique(ignoreRecord: true),
                                    DatePicker::make('payment_date')
                                        ->default(now())
                                        ->required(),
                                    DatePicker::make('posting_date')
                                        ->default(now())
                                        ->required(),
                                ]),
                                Grid::make(3)->schema([
                                    Select::make('payment_direction')
                                        ->options([
                                            'RECEIPT' => 'Receipt (Inbound)',
                                            'DISBURSEMENT' => 'Disbursement (Outbound)',
                                        ])
                                        ->required()
                                        ->native(false),
                                    TextInput::make('payment_method')
                                        ->placeholder('e.g., EFT, Check, Cash')
                                        ->required(),
                                    TextInput::make('external_reference')
                                        ->label('External Reference')
                                        ->placeholder('e.g., Check #, Wire ID'),
                                ]),
                            ]),

                        Tabs\Tab::make('Counterparty')
                            ->icon('heroicon-m-user-group')
                            ->schema([
                                Grid::make(2)->schema([
                                    Select::make('party_type')
                                        ->options([
                                            'CUSTOMER' => 'Customer',
                                            'VENDOR' => 'Vendor',
                                        ])
                                        ->required()
                                        ->live()
                                        ->native(false),
                                    TextInput::make('party_name')
                                        ->label('Display Name')
                                        ->required(),
                                    Select::make('party_id')
                                        ->label(fn ($get) => $get('party_type') === 'VENDOR' ? 'Vendor Account' : 'Customer Account')
                                        ->options(fn ($get) => match ($get('party_type')) {
                                            'CUSTOMER' => Customer::pluck('name', 'id'),
                                            'VENDOR' => Vendor::pluck('vendor_name', 'id'),
                                            default => [],
                                        })
                                        ->searchable()
                                        ->preload()
                                        ->live()
                                        ->afterStateUpdated(function ($state, $set, $get) {
                                            if ($get('party_type') === 'CUSTOMER') {
                                                $set('party_name', Customer::find($state)?->name);
                                            } else {
                                                $set('party_name', Vendor::find($state)?->vendor_name);
                                            }
                                        })
                                        ->hidden(fn ($get) => ! $get('party_type'))
                                        ->required(),
                                ]),
                                Section::make('Counterparty Bank Details')
                                    ->collapsible()
                                    ->collapsed()
                                    ->schema([
                                        Grid::make(3)->schema([
                                            Select::make('counterparty_bank_lookup')
                                                ->label('Search Known Banks')
                                                ->options(BankAccount::pluck('bank_name', 'id'))
                                                ->searchable()
                                                ->dehydrated(false)
                                                ->live()
                                                ->afterStateUpdated(function ($state, $set) {
                                                    $bank = BankAccount::find($state);
                                                    if ($bank) {
                                                        $set('counterparty_bank_name', $bank->bank_name);
                                                        $set('counterparty_account_number', $bank->account_number);
                                                        $set('counterparty_routing_number', $bank->routing_number);
                                                    }
                                                }),
                                            TextInput::make('counterparty_bank_name')
                                                ->label('Bank Name')
                                                ->required(),
                                            TextInput::make('counterparty_account_number')
                                                ->label('Account No.')
                                                ->required(),
                                            TextInput::make('counterparty_routing_number')
                                                ->label('Routing No.'),
                                        ]),
                                    ]),
                            ]),

                        Tabs\Tab::make('Financials')
                            ->icon('heroicon-m-banknotes')
                            ->schema([
                                Grid::make(2)->schema([
                                    Section::make('Currency & Rates')
                                        ->schema([
                                            Select::make('currency_id')
                                                ->label('Transaction Currency')
                                                ->relationship('currency', 'code')
                                                ->searchable()
                                                ->preload()
                                                ->default(fn () => Currency::where('is_lcy', true)->first()?->id)
                                                ->required()
                                                ->live()
                                                ->afterStateUpdated(function ($state, $set, $get) {
                                                    $currency = Currency::find($state);
                                                    if ($currency) {
                                                        $rate = $currency->getExchangeRate();
                                                        $set('currency_factor', $rate);
                                                        $set('currency_code', $currency->code);

                                                        // Update LCY amount
                                                        $amount = $get('payment_amount') ?? 0;
                                                        $set('payment_amount_lcy', round($amount * $rate, 4));
                                                    }
                                                }),
                                            TextInput::make('currency_code')
                                                ->disabled()
                                                ->dehydrated()
                                                ->label('Currency Code'),
                                            TextInput::make('currency_factor')
                                                ->label('Exchange Rate (Factor)')
                                                ->numeric()
                                                ->default(1)
                                                ->required()
                                                ->live()
                                                ->afterStateUpdated(function ($state, $set, $get) {
                                                    $amount = $get('payment_amount') ?? 0;
                                                    $set('payment_amount_lcy', round($amount * $state, 4));
                                                }),
                                        ])->columnSpan(1),
                                    Section::make('Primary Amounts')
                                        ->schema([
                                            TextInput::make('payment_amount')
                                                ->label('Total Document Amount')
                                                ->numeric()
                                                ->required()
                                                ->live(onBlur: true)
                                                ->afterStateUpdated(function ($state, $set, $get) {
                                                    $rate = $get('currency_factor') ?? 1;
                                                    $set('payment_amount_lcy', round($state * $rate, 4));
                                                    $set('unapplied_amount', $state - ($get('applied_amount') ?? 0));
                                                }),
                                            TextInput::make('payment_amount_lcy')
                                                ->label('Amount (LCY Value)')
                                                ->numeric()
                                                ->helperText('Value in local currency (e.g., NGN)')
                                                ->required()
                                                ->readOnly(),
                                        ])->columnSpan(1),
                                ]),
                                Grid::make(3)->schema([
                                    TextInput::make('applied_amount')
                                        ->label('Applied to Docs')
                                        ->numeric()
                                        ->disabled()
                                        ->dehydrated(),
                                    TextInput::make('unapplied_amount')
                                        ->label('Remaining Balance')
                                        ->numeric()
                                        ->extraInputAttributes(['class' => 'font-bold text-primary-600'])
                                        ->disabled()
                                        ->dehydrated(),
                                    TextInput::make('discount_taken')
                                        ->label('Discounts')
                                        ->numeric()
                                        ->prefix('$'),
                                ]),
                                Grid::make(2)->schema([
                                    TextInput::make('transaction_fee')
                                        ->label('Bank Fees')
                                        ->numeric()
                                        ->prefix('$'),
                                    TextInput::make('transaction_fee_lcy')
                                        ->label('Bank Fees (LCY)')
                                        ->numeric()
                                        ->prefix('$'),
                                ]),
                            ]),

                        Tabs\Tab::make('Bank & Posting')
                            ->icon('heroicon-m-building-library')
                            ->schema([
                                Grid::make(2)->schema([
                                    Select::make('bank_account_id')
                                        ->label('Internal Bank Account')
                                        ->relationship('bankAccount', 'account_name')
                                        ->required(),
                                    Select::make('general_business_posting_group_id')
                                        ->label('Bus. Posting Group')
                                        ->relationship('generalBusinessPostingGroup', 'code'),
                                ]),
                                Grid::make(3)->schema([
                                    TextInput::make('check_number')->label('Check No.'),
                                    DatePicker::make('check_date'),
                                    DatePicker::make('clearing_date')->label('Value/Cleared Date'),
                                ]),
                            ]),

                        Tabs\Tab::make('Status & Audit')
                            ->icon('heroicon-m-shield-check')
                            ->schema([
                                Grid::make(3)->schema([
                                    TextInput::make('status')
                                        ->disabled()
                                        ->dehydrated(),
                                    Toggle::make('reconciled')
                                        ->disabled(),
                                    DateTimePicker::make('reconciled_at')
                                        ->disabled(),
                                ]),
                                Grid::make(2)->schema([
                                    Select::make('created_by')
                                        ->relationship('creator', 'name')
                                        ->disabled(),
                                    Select::make('posted_by')
                                        ->relationship('poster', 'name')
                                        ->disabled(),
                                ]),
                                Textarea::make('memo')
                                    ->label('Internal Memo')
                                    ->columnSpanFull(),
                                Textarea::make('void_reason')
                                    ->visible(fn ($record) => $record?->status === 'VOIDED')
                                    ->disabled()
                                    ->columnSpanFull(),
                            ]),
                    ])->columnSpanFull(),
            ]);
    }
}
