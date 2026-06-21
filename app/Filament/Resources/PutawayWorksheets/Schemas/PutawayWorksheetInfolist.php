<?php

namespace App\Filament\Resources\PutawayWorksheets\Schemas;

use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Schema;

class PutawayWorksheetInfolist
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
                        TextEntry::make('status')->badge()->color(fn ($state) => match ($state) {
                            'posted' => 'success',
                            'reversed' => 'danger',
                            default => 'warning',
                        }),
                        TextEntry::make('posting_date')->date(),
                        TextEntry::make('document_date')->date()->placeholder('-'),
                        TextEntry::make('postedBy.name')->label('Posted By')->placeholder('System'),
                    ]),

                Tabs::make('Information Details')
                    ->columnSpanFull()
                    ->tabs([
                        Tabs\Tab::make('Financials')
                            ->schema([
                                Grid::make(3)->schema([
                                    TextEntry::make('amount')->money()->color('primary'),
                                    TextEntry::make('currency_code')->label('Currency'),
                                    TextEntry::make('currency_factor')->label('Exch. Rate'),
                                    TextEntry::make('amount_lcy')->label('Total (LCY)')->money()->weight('bold'),
                                    TextEntry::make('vat_amount')->label('VAT')->money(),
                                    TextEntry::make('vat_bus_posting_group')->label('VAT Group'),
                                ]),
                            ]),

                        Tabs\Tab::make('Entities & Assignment')
                            ->schema([
                                Grid::make(3)->schema([
                                    TextEntry::make('vendor.vendor_name')->label('Vendor')->placeholder('N/A'),
                                    TextEntry::make('customer.name')->placeholder('N/A'),
                                    TextEntry::make('employee.first_name')->label('Employee')->placeholder('N/A'),
                                    TextEntry::make('item.description')->label('Inventory Item')->placeholder('N/A'),
                                    TextEntry::make('productCategory.name')->label('Product Category')->placeholder('N/A'),
                                    TextEntry::make('expense_type')->label('Expense Type')->badge()->color('gray'),
                                ]),
                            ]),

                        Tabs\Tab::make('Accounting & Audit')
                            ->schema([
                                Grid::make(3)->schema([
                                    TextEntry::make('expenseAccount.name')->label('Posting Account'),
                                    TextEntry::make('category_code')->label('Category Code'),
                                    TextEntry::make('glEntry.id')->label('G/L Entry ID'),
                                    TextEntry::make('invoice_no')->label('Vendor Inv No.'),
                                    TextEntry::make('purchase_order_no')->label('P.O. No.'),
                                    TextEntry::make('sales_order_no')->label('S.O. No.'),
                                    TextEntry::make('shortcut_dimension_1_code')->label('Dim 1'),
                                    TextEntry::make('shortcut_dimension_2_code')->label('Dim 2'),
                                    TextEntry::make('created_at')->dateTime(),
                                ]),
                                TextEntry::make('description')->columnSpanFull()->placeholder('No description provided.'),
                            ]),
                    ]),
            ]);
    }
}
