<?php

namespace App\Filament\Resources\VendorInvoices\Schemas;

use App\Filament\Traits\HasSystemGeneratedField;
use App\Models\VendorInvoice;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Schema;

class VendorInvoiceForm
{
    use HasSystemGeneratedField;

    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Tabs::make('Vendor Invoice')
                    ->tabs([
                        Tab::make('General')
                            ->icon('heroicon-o-document-text')
                            ->schema([
                                Section::make('Document Information')
                                    ->schema([
                                        Grid::make(3)->schema([
                                            static::makeSystemGeneratedTextInput(
                                                'document_number',
                                                'Invoice No.',
                                                'Generated automatically from the vendor invoice number series and cannot be changed.'
                                            ),
                                            Select::make('vendor_id')
                                                ->label('Vendor')
                                                ->relationship('vendor', 'vendor_name')
                                                ->searchable()
                                                ->preload()
                                                ->required()
                                                ->disabled(fn (?VendorInvoice $record) => $record?->posted),
                                            TextInput::make('vendor_invoice_no')
                                                ->label('Vendor Invoice No.')
                                                ->required()
                                                ->disabled(fn (?VendorInvoice $record) => $record?->posted),
                                        ]),
                                        Grid::make(3)->schema([
                                            DatePicker::make('vendor_invoice_date')
                                                ->label('Vendor Invoice Date')
                                                ->required()
                                                ->native(false)
                                                ->disabled(fn (?VendorInvoice $record) => $record?->posted),
                                            Select::make('document_type')
                                                ->options(['INVOICE' => 'Invoice', 'CREDIT_MEMO' => 'Credit Memo'])
                                                ->required()
                                                ->default('INVOICE')
                                                ->native(false)
                                                ->disabled(fn (?VendorInvoice $record) => $record?->posted),
                                            Select::make('status')
                                                ->options(['OPEN' => 'Open', 'APPROVED' => 'Approved', 'POSTED' => 'Posted', 'PAID' => 'Paid'])
                                                ->required()
                                                ->default('OPEN')
                                                ->native(false)
                                                ->disabled(fn (?VendorInvoice $record) => $record?->posted),
                                        ]),
                                    ]),
                                Section::make('Source & References')
                                    ->schema([
                                        Grid::make(3)->schema([
                                            Select::make('source_document_type')
                                                ->label('Source Type')
                                                ->options(['PURCHASE_ORDER' => 'Purchase Order', 'PURCHASE_RECEIPT' => 'Purchase Receipt', 'BLANKET_ORDER' => 'Blanket Order'])
                                                ->native(false)
                                                ->disabled(fn (?VendorInvoice $record) => $record?->posted),
                                            TextInput::make('source_document_no')->label('Source Doc No.')->disabled(fn (?VendorInvoice $record) => $record?->posted),
                                            TextInput::make('external_document_no')->label('Ext. Doc No.')->disabled(fn (?VendorInvoice $record) => $record?->posted),
                                        ]),
                                    ]),
                            ]),
                        Tab::make('Financials & Dates')
                            ->icon('heroicon-o-currency-dollar')
                            ->schema([
                                Section::make('Amounts')
                                    ->schema([
                                        Grid::make(4)->schema([
                                            TextInput::make('amount')
                                                ->label('Subtotal')
                                                ->numeric()
                                                ->prefix('₦')
                                                ->disabled()
                                                ->dehydrated(),
                                            TextInput::make('discount_amount')
                                                ->label('Discount')
                                                ->numeric()
                                                ->prefix('₦')
                                                ->disabled()
                                                ->dehydrated(),
                                            TextInput::make('tax_amount')
                                                ->label('Tax')
                                                ->numeric()
                                                ->prefix('₦')
                                                ->disabled()
                                                ->dehydrated(),
                                            TextInput::make('amount_including_tax')
                                                ->label('Total')
                                                ->numeric()
                                                ->prefix('₦')
                                                ->disabled()
                                                ->dehydrated(),
                                            //                                                ->weight('bold'),
                                        ]),
                                        Grid::make(4)->schema([
                                            Select::make('currency_code')
                                                ->options(['NGN' => 'NGN', 'USD' => 'USD', 'EUR' => 'EUR'])
                                                ->required()
                                                ->default('NGN')
                                                ->live()
                                                ->disabled(fn (?VendorInvoice $record) => $record?->posted),
                                            TextInput::make('exchange_rate')->numeric()->required()->default(1)->disabled(fn (?VendorInvoice $record) => $record?->posted),
                                            TextInput::make('amount_lcy')->label('Amount (LCY)')->numeric()->prefix('₦')->disabled()->dehydrated(),
                                            TextInput::make('remaining_amount')->numeric()->prefix('₦')->disabled()->dehydrated(),
                                        ]),
                                    ]),
                                Section::make('Dates & Payment')
                                    ->schema([
                                        Grid::make(4)->schema([
                                            DatePicker::make('posting_date')->required()->native(false)->disabled(fn (?VendorInvoice $record) => $record?->posted),
                                            DatePicker::make('due_date')->required()->native(false)->disabled(fn (?VendorInvoice $record) => $record?->posted),
                                            DatePicker::make('receipt_date')->native(false)->disabled(fn (?VendorInvoice $record) => $record?->posted),
                                            DatePicker::make('last_payment_date')->native(false)->disabled(),
                                        ]),
                                        Grid::make(3)->schema([
                                            TextInput::make('payment_terms_code')->disabled(fn (?VendorInvoice $record) => $record?->posted),
                                            TextInput::make('payment_method_code')->disabled(fn (?VendorInvoice $record) => $record?->posted),
                                            Select::make('payable_gl_account_id')
                                                ->label('Payable Account')
                                                ->relationship('payableAccount', 'name')
                                                ->searchable()
                                                ->preload()
                                                ->disabled(fn (?VendorInvoice $record) => $record?->posted),
                                        ]),
                                    ]),
                            ]),
                        Tab::make('Dimensions & CapEx')
                            ->icon('heroicon-o-square-3-stack-3d')
                            ->schema([
                                Section::make('Dimensions')
                                    ->schema([
                                        Grid::make(3)->schema([
                                            TextInput::make('shortcut_dimension_1_code')->label('Dimension 1'),
                                            TextInput::make('shortcut_dimension_2_code')->label('Dimension 2'),
                                            TextInput::make('dimension_set_id')->label('Set ID'),
                                        ]),
                                    ]),
                                Section::make('Capital Expenditure')
                                    ->schema([
                                        Grid::make(2)->schema([
                                            Select::make('capex_project_id')
                                                ->label('CapEx Project')
                                                ->relationship('capExProject', 'description')
                                                ->searchable()
                                                ->preload(),
                                            Toggle::make('capitalized')
                                                ->inline(false)
                                                ->disabled(),
                                        ]),
                                        Select::make('expense_gl_account_id')
                                            ->label('Expense Account')
                                            ->relationship('expenseAccount', 'name')
                                            ->searchable()
                                            ->preload(),
                                    ]),
                            ]),
                        Tab::make('Audit & Notes')
                            ->icon('heroicon-o-clipboard-document-check')
                            ->schema([
                                Section::make('Approval & Posting')
                                    ->schema([
                                        Grid::make(3)->schema([
                                            Select::make('requested_by')->label('Requested By')->relationship('requester', 'name')->disabled(),
                                            Select::make('approved_by')->label('Approved By')->relationship('approver', 'name')->disabled(),
                                            DateTimePicker::make('approved_at')->disabled(),
                                        ]),
                                        Grid::make(3)->schema([
                                            Toggle::make('posted')->inline(false)->disabled(),
                                            DateTimePicker::make('posted_at')->disabled(),
                                            Select::make('posted_by')->label('Posted By')->relationship('postedByUser', 'name')->disabled(),
                                        ]),
                                    ]),
                                Section::make('Notes')
                                    ->schema([
                                        Textarea::make('description')->columnSpanFull(),
                                        Textarea::make('internal_notes')->columnSpanFull(),
                                    ]),
                            ]),
                    ])->columnSpanFull(),
            ]);
    }
}
