<?php

namespace App\Filament\Resources\PostedPurchaseCreditMemos\Schemas;

use App\Models\Vendor;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class PostedPurchaseCreditMemoForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Grid::make(3)->schema([
                    Section::make('General Information')
                        ->schema([
                            TextInput::make('document_number')
                                ->label('Credit Memo No.')
                                ->required()
                                ->unique(ignoreRecord: true),
                            TextInput::make('external_document_number')
                                ->label('External Doc No.'),
                            TextInput::make('vendor_invoice_number')
                                ->label('Vendor Invoice No.'),
                            Select::make('vendor_id')
                                ->relationship('vendor', 'vendor_name')
                                ->searchable()
                                ->preload()
                                ->required()
                                ->reactive()
                                ->afterStateUpdated(fn ($state, callable $set) => self::updateVendorData($state, $set)),
                            TextInput::make('vendor_name')
                                ->required(),
                            Select::make('corrects_invoice_id')
                                ->label('Corrects Invoice')
                                ->relationship('correctedInvoice', 'document_number')
                                ->searchable(),
                        ])->columnSpan(2),

                    Section::make('Status & Posting')
                        ->schema([
                            Placeholder::make('posted_status')
                                ->label('Status')
                                ->content(fn ($record) => $record?->posted ? '✅ Posted' : '⏳ Draft'),
                            DateTimePicker::make('posted_at')
                                ->disabled(),
                            Select::make('posted_by')
                                ->relationship('poster', 'name')
                                ->disabled(),
                            Select::make('reason_code')
                                ->label('Reason Code')
                                ->relationship('reasonCode', 'description')
                                ->getOptionLabelFromRecordUsing(fn ($record): string => "{$record->code} - {$record->description}")
                                ->searchable()
                                ->preload(),
                            Select::make('location_code')
                                ->label('Location')
                                ->relationship('location', 'name')
                                ->getOptionLabelFromRecordUsing(fn ($record): string => "{$record->code} - {$record->name}")
                                ->searchable()
                                ->preload(),
                        ])->columnSpan(1),
                ]),

                Grid::make(3)->schema([
                    Section::make('Vendor Address')
                        ->schema([
                            Textarea::make('vendor_address')->rows(2),
                            Grid::make(2)->schema([
                                TextInput::make('vendor_city'),
                                TextInput::make('vendor_post_code'),
                                TextInput::make('vendor_country'),
                                TextInput::make('vendor_tax_registration_number'),
                            ]),
                        ])->columnSpan(2),

                    Section::make('Dates')
                        ->schema([
                            DatePicker::make('posting_date')->required(),
                            DatePicker::make('document_date')->required(),
                            DatePicker::make('due_date'),
                        ])->columnSpan(1),
                ]),

                Section::make('Financials')
                    ->schema([
                        Grid::make(4)->schema([
                            TextInput::make('currency_code')->default('USD'),
                            TextInput::make('currency_factor')->numeric()->default(1),
                            TextInput::make('payment_terms_code'),
                            TextInput::make('warehouse_receipt_number'),
                        ]),
                        Grid::make(4)->schema([
                            TextInput::make('subtotal')->numeric()->prefix('$')->disabled(),
                            TextInput::make('discount_amount')->numeric()->prefix('$')->disabled(),
                            TextInput::make('tax_amount')->numeric()->prefix('$')->disabled(),
                            TextInput::make('grand_total')
                                ->numeric()
                                ->prefix('$')
                                ->label('Grand Total')
                                ->extraInputAttributes(['class' => 'font-bold text-lg'])
                                ->disabled(),
                        ]),
                    ]),

                Section::make('Notes')
                    ->collapsible()
                    ->schema([
                        Textarea::make('description')->columnSpanFull(),
                    ]),
            ]);
    }

    protected static function updateVendorData($state, callable $set): void
    {
        if (! $state) {
            return;
        }
        $vendor = Vendor::find($state);
        if (! $vendor) {
            return;
        }

        $set('vendor_name', $vendor->vendor_name);
        $set('vendor_address', $vendor->address);
        $set('vendor_city', $vendor->city);
        $set('vendor_post_code', $vendor->postal_code);
        $set('vendor_country', $vendor->country);
        $set('vendor_tax_registration_number', $vendor->tax_id);
        $set('currency_code', $vendor->currency);
        $set('payment_terms_code', $vendor->payment_terms_code);
        $set('vendor_posting_group_id', $vendor->vendor_posting_group_id);
        $set('general_business_posting_group_id', $vendor->general_business_posting_group_id);
    }
}
