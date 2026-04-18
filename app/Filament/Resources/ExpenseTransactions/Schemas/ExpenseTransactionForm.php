<?php

namespace App\Filament\Resources\ExpenseTransactions\Schemas;

use App\Enums\AccountType;
use App\Models\DimensionValue;
use App\Models\Employee;
use App\Models\ExpenseCategory;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Schema;

class ExpenseTransactionForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Tabs::make('Transaction Details')
                    ->columnSpanFull()
                    ->tabs([
                        Tabs\Tab::make('Header Info')
                            ->icon('heroicon-o-document-text')
                            ->schema([
                                Grid::make(3)->schema([
                                    Select::make('document_type')
                                        ->options([
                                            'invoice' => 'Invoice',
                                            'credit_memo' => 'Credit Memo',
                                            'payment' => 'Payment',
                                            'refund' => 'Refund',
                                            'journal' => 'Journal Adjustment',
                                        ])
                                        ->required()
                                        ->native(false),
                                    TextInput::make('document_no')
                                        ->label('Document No.')
                                        ->required()
                                        ->unique(ignoreRecord: true),
                                    Select::make('status')
                                        ->options([
                                            'posted' => 'Posted',
                                            'reversed' => 'Reversed',
                                            'pending' => 'Pending',
                                        ])
                                        ->default('posted')
                                        ->required()
                                        ->native(false),
                                ]),
                                Grid::make(2)->schema([
                                    DatePicker::make('posting_date')
                                        ->label('Posting Date')
                                        ->required()
                                        ->native(false)
                                        ->default(now()),
                                    DatePicker::make('document_date')
                                        ->label('Document Date')
                                        ->native(false),
                                ]),
                                Textarea::make('description')
                                    ->rows(2)
                                    ->columnSpanFull(),
                            ]),

                        Tabs\Tab::make('Financials')
                            ->icon('heroicon-o-currency-dollar')
                            ->schema([
                                Grid::make(3)->schema([
                                    TextInput::make('amount')
                                        ->label('Amount')
                                        ->numeric()
                                        ->required()
                                        ->prefix('$')
                                        ->reactive()
                                        ->afterStateUpdated(function ($state, $get, $set) {
                                            $factor = (float) ($get('currency_factor') ?? 1);
                                            $set('amount_lcy', (float) $state * $factor);
                                        }),
                                    Select::make('currency_id')
                                        ->label('Currency')
                                        ->relationship('currency', 'code')
                                        ->searchable()
                                        ->preload()
                                        ->default(fn () => \App\Models\Currency::where('is_lcy', true)->first()?->id)
                                        ->required()
                                        ->reactive()
                                        ->afterStateUpdated(function ($state, $get, $set) {
                                            $currency = \App\Models\Currency::find($state);
                                            if ($currency) {
                                                $set('currency_factor', $currency->exchange_rate ?? 1);
                                                $amount = (float) ($get('amount') ?? 0);
                                                $set('amount_lcy', $amount * (float) ($currency->exchange_rate ?? 1));
                                            }
                                        }),
                                    TextInput::make('currency_factor')
                                        ->label('Exchange Rate')
                                        ->numeric()
                                        ->default(1)
                                        ->required()
                                        ->reactive()
                                        ->afterStateUpdated(function ($state, $get, $set) {
                                            $amount = (float) ($get('amount') ?? 0);
                                            $set('amount_lcy', $amount * (float) $state);
                                        }),

                                    TextInput::make('amount_lcy')
                                        ->label('Amount (LCY)')
                                        ->numeric()
                                        ->required()
                                        ->readOnly()
                                        ->prefix('$'),

                                    TextInput::make('vat_amount')
                                        ->label('VAT Amount')
                                        ->numeric()
                                        ->default(0)
                                        ->prefix('$'),

                                    Select::make('vat_bus_posting_group_id')
                                        ->label('VAT Bus. Posting Group')
                                        ->relationship('vatBusinessPostingGroup', 'code')
                                        ->searchable()
                                        ->preload(),
                                    Select::make('vat_prod_posting_group_id')
                                        ->label('VAT Prod. Posting Group')
                                        ->relationship('vatProductPostingGroup', 'code')
                                        ->searchable()
                                        ->preload(),
                                ]),
                            ]),

                        Tabs\Tab::make('Assignment')
                            ->icon('heroicon-o-user-group')
                            ->schema([
                                Grid::make(2)->schema([
                                    Select::make('account_type')
                                        ->label('Account Category')
                                        ->options(AccountType::class)
                                        ->required()
                                        ->native(false),
                                    Select::make('category_code')
                                        ->label('Expense Category')
                                        ->relationship('expenseCategory', 'description')
                                        ->required()
                                        ->searchable()
                                        ->preload()
                                        ->live()
                                        ->afterStateUpdated(function ($state, $set) {
                                            $category = ExpenseCategory::where('category_code', $state)->first();
                                            if ($category) {
                                                $set('gen_prod_posting_group_id', $category->gen_prod_posting_group_id);
                                                $set('vat_prod_posting_group_id', $category->vat_prod_posting_group_id);
                                                $set('expense_account_id', $category->expense_account_id);
                                            }
                                        }),
                                    Select::make('vendor_id')
                                        ->label('Vendor')
                                        ->relationship('vendor', 'vendor_name')
                                        ->searchable()
                                        ->preload(),
                                    Select::make('employee_id')
                                        ->relationship('employee', 'employee_number')
                                        ->getOptionLabelFromRecordUsing(fn (Employee $record) => "{$record->employee_number} - {$record->first_name} {$record->last_name}")
                                        ->searchable()
                                        ->preload(),
                                    Select::make('customer_id')
                                        ->relationship('customer', 'name')
                                        ->searchable(),
                                    Select::make('item_id')
                                        ->relationship('item', 'description')
                                        ->label('Inventory Item')
                                        ->searchable(),
                                    Select::make('category_id')
                                        ->relationship('productCategory', 'category_name')
                                        ->label('Product Category')
                                        ->searchable()
                                        ->preload(),
                                    Select::make('expense_type')
                                        ->options([
                                            'direct' => 'Direct',
                                            'indirect' => 'Indirect',
                                        ])
                                        ->native(false),
                                    Grid::make(2)->schema([
                                        Select::make('source_type')
                                            ->options([
                                                'VENDOR' => 'Vendor',
                                                'CUSTOMER' => 'Customer',
                                                'EMPLOYEE' => 'Employee',
                                                'BANK' => 'Bank Account',
                                                'FIXED_ASSET' => 'Fixed Asset',
                                            ])
                                            ->native(false),
                                        TextInput::make('source_no')
                                            ->label('Source No.'),
                                    ]),
                                ]),
                            ]),

                        Tabs\Tab::make('Accounting & Audit')
                            ->icon('heroicon-o-building-library')
                            ->schema([
                                Grid::make(2)->schema([
                                    Select::make('expense_account_id')
                                        ->relationship('expenseAccount', 'name')
                                        ->label('Posting G/L Account')
                                        ->required()
                                        ->searchable()
                                        ->preload(),
                                    Select::make('gl_entry_id')
                                        ->relationship('glEntry', 'id')
                                        ->disabled()
                                        ->label('G/L Register Entry'),

                                    Grid::make(2)->schema([
                                        Select::make('gen_bus_posting_group_id')
                                            ->label('Gen. Bus. Posting Group')
                                            ->relationship('generalBusinessPostingGroup', 'code')
                                            ->required()
                                            ->searchable()
                                            ->preload(),
                                        Select::make('gen_prod_posting_group_id')
                                            ->label('Gen. Prod. Posting Group')
                                            ->relationship('generalProductPostingGroup', 'code')
                                            ->required()
                                            ->searchable()
                                            ->preload(),
                                    ]),

                                    Select::make('dimension_set_id')
                                        ->label('Dimension Set')
                                        ->relationship('dimensionSet', 'id') // Ideally this would be a custom picker
                                        ->searchable()
                                        ->preload(),

                                    Grid::make(2)->schema([
                                        Select::make('shortcut_dimension_1_code')
                                            ->label('Department (Dim 1)')
                                            ->options(fn () => DimensionValue::whereHas('dimension', fn ($q) => $q->where('code', 'DEPARTMENT'))->pluck('name', 'code'))
                                            ->searchable()
                                            ->preload(),

                                        Select::make('shortcut_dimension_2_code')
                                            ->label('Project (Dim 2)')
                                            ->options(fn () => DimensionValue::whereHas('dimension', fn ($q) => $q->where('code', 'PROJECT'))->pluck('name', 'code'))
                                            ->searchable()
                                            ->preload(),
                                    ]),

                                    TextInput::make('invoice_no')
                                        ->label('Vendor Inv No.'),
                                    TextInput::make('purchase_order_no')
                                        ->label('P.O. No.'),
                                    TextInput::make('sales_order_no')
                                        ->label('S.O. No.'),
                                ]),
                            ]),
                    ]),
            ]);
    }
}
