<?php

namespace App\Filament\Resources\PurchaseQuotes\Schemas;

use App\Enums\PurchaseQuoteStatus;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Schema;

class PurchaseQuoteForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Tabs::make('Quote Details')
                    ->tabs([
                        Tab::make('General')
                            ->icon('heroicon-m-document-text')
                            ->schema([
                                Grid::make(3)->schema([
                                    TextInput::make('document_no')->required()->unique(ignoreRecord: true),
                                    Select::make('status')
                                        ->options(PurchaseQuoteStatus::class)
                                        ->default(PurchaseQuoteStatus::OPEN)
                                        ->required()
                                        ->native(false),
                                    TextInput::make('vendor_quote_no')->label('Vendor Quote #'),
                                ]),
                                Grid::make(2)->schema([
                                    Select::make('vendor_id')
                                        ->relationship('vendor', 'vendor_name') // Changed from 'id' to 'name' for UX
                                        ->searchable()
                                        ->preload()
                                        ->required()
                                        ->reactive(),
                                    Select::make('contact_id')
                                        ->relationship('contact', 'full_name')
                                        ->searchable(),
                                    Select::make('buyer_id')
                                        ->relationship('buyer', 'name')
                                        ->default(auth()->id()),
                                ]),
                            ]),

                        Tab::make('Dates & Logistics')
                            ->icon('heroicon-m-calendar')
                            ->schema([
                                Grid::make(3)->schema([
                                    DatePicker::make('document_date')->required()->default(now()),
                                    DatePicker::make('posting_date'),
                                    DatePicker::make('due_date'),
                                    DatePicker::make('requested_receipt_date'),
                                    DatePicker::make('promised_receipt_date'),
                                    TextInput::make('location_code'),
                                ]),
                            ]),

                        Tab::make('Financials')
                            ->icon('heroicon-m-banknotes')
                            ->schema([
                                Grid::make(3)->schema([
                                    Select::make('currency_code')
                                        ->options(['USD' => 'USD', 'EUR' => 'EUR', 'GBP' => 'GBP']) // Or relationship
                                        ->default('USD'),
                                    TextInput::make('currency_factor')->numeric()->default(1),
                                    TextInput::make('payment_terms_code'),
                                    TextInput::make('amount')->disabled()->prefix('$'), // Read-only as model calculates this
                                    TextInput::make('vat_amount')->disabled()->prefix('$'),
                                    TextInput::make('amount_including_vat')
                                        ->label('Total Amount')
                                        ->disabled()
                                        ->prefix('$'),
                                ]),
                            ]),
                    ])->columnSpanFull(),

                Section::make('Notes')
                    ->collapsible()
                    ->schema([
                        Textarea::make('vendor_note')->rows(3),
                        Textarea::make('internal_note')->rows(3),
                    ]),
            ]);
    }
}
