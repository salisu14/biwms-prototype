<?php

namespace App\Filament\Resources\ExpenseTransactions\Schemas;

use App\Models\ExpenseTransaction;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Schema;

class ExpenseTransactionInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('General Summary')
                    ->columns(3)
                    ->schema([
                        TextEntry::make('document_no')->label('Doc No.')->weight('bold'),
                        TextEntry::make('document_type')->badge()->color('gray'),
                        TextEntry::make('status')->badge()->color(fn ($state) => $state === 'posted' ? 'success' : 'warning'),
                        TextEntry::make('posting_date')->date(),
                        TextEntry::make('document_date')->date()->placeholder('-'),
                        TextEntry::make('posted_by')->label('Author ID'),
                    ]),

                Tabs::make('Information Details')
                    ->columnSpanFull()
                    ->tabs([
                        Tabs\Tab::make('Financials')
                            ->schema([
                                Grid::make(3)->schema([
                                    TextEntry::make('amount')->money('NGN')->color('primary'),
                                    TextEntry::make('currency_code')->label('Currency'),
                                    TextEntry::make('currency_factor')->label('Exch. Rate'),
                                    TextEntry::make('amount_lcy')->label('Total (LCY)')->money('NGN')->weight('bold'),
                                    TextEntry::make('vat_amount')->label('VAT')->money('NGN'),
                                    TextEntry::make('vat_bus_posting_group')->label('VAT Group'),
                                ]),
                            ]),

                        Tabs\Tab::make('Entities')
                            ->schema([
                                Grid::make(3)->schema([
                                    TextEntry::make('vendor.vendor_name')->placeholder('N/A'),
                                    TextEntry::make('customer.name')->placeholder('N/A'),
                                    TextEntry::make('employee.first_name')->label('Employee')->placeholder('N/A'),
                                    TextEntry::make('item.description')->label('Inventory Item')->placeholder('N/A'),
                                    TextEntry::make('expenseAccount.name')->label('Posting Account'),
                                    TextEntry::make('category_code')->label('Expense Code'),
                                ]),
                            ]),

                        Tabs\Tab::make('Tracking & Audit')
                            ->schema([
                                Grid::make(3)->schema([
                                    TextEntry::make('invoice_no')->label('Inv. No'),
                                    TextEntry::make('purchase_order_no')->label('P.O.'),
                                    TextEntry::make('glEntry.id')->label('G/L Entry ID'),
                                    TextEntry::make('shortcut_dimension_1_code')->label('Dim 1'),
                                    TextEntry::make('shortcut_dimension_2_code')->label('Dim 2'),
                                    TextEntry::make('created_at')->dateTime(),
                                ]),
                                TextEntry::make('description')->columnSpanFull(),
                            ]),
                    ]),
            ]);
    }
}
