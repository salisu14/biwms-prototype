<?php

namespace App\Filament\Resources\PurchaseQuotes\Schemas;

use App\Enums\PurchaseQuoteStatus;
use App\Filament\Traits\HasSystemGeneratedField;
use App\Models\Currency;
use App\Models\PaymentTerm;
use App\Models\Vendor;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;

class PurchaseQuoteForm
{
    use HasSystemGeneratedField;

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
                                    static::makeSystemGeneratedTextInput(
                                        'document_no',
                                        'Document No.',
                                        'Generated automatically from the purchase quote number series and cannot be changed.'
                                    ),
                                    Select::make('status')
                                        ->options(PurchaseQuoteStatus::class)
                                        ->default(PurchaseQuoteStatus::OPEN)
                                        ->required()
                                        ->native(false),
                                    TextInput::make('vendor_quote_no')->label('Vendor Quote #'),
                                ]),
                                Grid::make(2)->schema([
                                    Select::make('vendor_id')
                                        ->relationship('vendor', 'vendor_name')
                                        ->searchable()
                                        ->preload()
                                        ->required()
                                        ->live()
                                        ->afterStateUpdated(function ($state, Set $set): void {
                                            if (! $state) {
                                                return;
                                            }

                                            $vendor = Vendor::find($state);
                                            if (! $vendor) {
                                                return;
                                            }

                                            if ($vendor->currency) {
                                                $set('currency_code', $vendor->currency);
                                            }
                                            if ($vendor->payment_terms_code) {
                                                $set('payment_terms_code', $vendor->payment_terms_code);
                                                $term = PaymentTerm::query()->where('code', $vendor->payment_terms_code)->first();
                                                $documentDate = now();
                                                if ($term) {
                                                    $set('due_date', $term->calculateDueDate($documentDate)->format('Y-m-d'));
                                                }
                                            }
                                        }),
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
                                        ->options(fn () => Currency::query()->where('is_active', true)->orderBy('code')->pluck('code', 'code'))
                                        ->searchable()
                                        ->default('NGN'),
                                    TextInput::make('currency_factor')->numeric()->default(1),
                                    Select::make('payment_terms_code')
                                        ->options(fn () => PaymentTerm::query()->active()->orderBy('code')->pluck('description', 'code'))
                                        ->searchable()
                                        ->preload()
                                        ->live()
                                        ->afterStateUpdated(function ($state, Set $set, Get $get): void {
                                            if (! $state) {
                                                return;
                                            }

                                            $term = PaymentTerm::query()->where('code', $state)->first();
                                            if (! $term) {
                                                return;
                                            }

                                            $documentDate = $get('document_date') ? new \DateTime((string) $get('document_date')) : now();
                                            $set('due_date', $term->calculateDueDate($documentDate)->format('Y-m-d'));
                                        }),
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
