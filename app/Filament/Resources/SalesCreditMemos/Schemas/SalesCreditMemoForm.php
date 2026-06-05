<?php

namespace App\Filament\Resources\SalesCreditMemos\Schemas;

use App\Filament\Traits\HasSystemGeneratedField;
use App\Models\Item;
use App\Models\SalesInvoice;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;

class SalesCreditMemoForm
{
    use HasSystemGeneratedField;

    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Group::make()
                    ->schema([
                        Section::make('General Information')
                            ->schema([
                                static::makeSystemGeneratedTextInput(
                                    'memo_number',
                                    'Memo Number',
                                    'Generated automatically from the sales credit memo number series and cannot be changed.'
                                )->prefix('#'),

                                Select::make('customer_id')
                                    ->relationship('customer', 'name')
                                    ->searchable()
                                    ->preload()
                                    ->required()
                                    // Make it live so it updates visually when set via JS
                                    ->live()
                                    ->disabled(fn ($record) => $record?->isPosted()),

                                // 2. The Invoice Select with Auto-fill Logic
                                Select::make('sales_invoice_id')
                                    ->label('Link to Invoice')
                                    ->relationship('invoice', 'invoice_number')
                                    ->searchable()
                                    ->preload()
                                    ->placeholder('Optional: Select original invoice')
                                    ->disabled(fn ($record) => $record?->isPosted())
                                    ->live()
                                    ->afterStateUpdated(function ($state, Set $set) {
                                        if ($state) {
                                            $invoice = SalesInvoice::find($state);
                                            if ($invoice && $invoice->customer_id) {
                                                // Automatically set the customer_id based on the invoice
                                                $set('customer_id', $invoice->customer_id);
                                            }
                                        }
                                    }),
                            ])->columns(2),

                        Section::make('Credit Items')
                            ->description('List the items being credited')
                            ->schema([
                                Repeater::make('items')
                                    ->relationship()
                                    ->live()
                                    ->schema([
                                        Select::make('item_id')
                                            ->relationship('item', 'description')
                                            ->searchable()
                                            ->preload()
                                            ->required()
                                            ->live()
                                            ->afterStateUpdated(function ($state, Set $set) {
                                                if ($state) {
                                                    $item = Item::find($state);
                                                    $set('unit_price', $item?->unit_price ?? 0);
                                                    $set('unit_of_measure_code', $item?->uom_code ?? 'PCS');
                                                }
                                            })
                                            ->columnSpan(4),

                                        TextInput::make('quantity')
                                            ->numeric()
                                            ->default(1)
                                            ->required()
                                            ->live(onBlur: true)
                                            ->columnSpan(2),

                                        TextInput::make('unit_price')
                                            ->label('Unit Price')
                                            ->numeric()
                                            ->required()
                                            ->live(onBlur: true)
                                            ->columnSpan(3),

                                        TextInput::make('vat_percent')
                                            ->label('VAT %')
                                            ->numeric()
                                            ->default(0)
                                            ->live(onBlur: true)
                                            ->columnSpan(1),

                                        TextInput::make('amount_including_vat')
                                            ->label('Line Total (Gross)')
                                            ->numeric()
                                            ->readOnly()
                                            ->placeholder(function (Get $get) {
                                                $qty = (float) ($get('quantity') ?? 0);
                                                $price = (float) ($get('unit_price') ?? 0);
                                                $vat = (float) ($get('vat_percent') ?? 0);
                                                $net = $qty * $price;

                                                return number_format($net + ($net * ($vat / 100)), 2);
                                            })
                                            ->columnSpan(2),
                                    ])
                                    ->columns(12)
                                    ->reorderable(false),
                            ]),
                    ])->columnSpan(['lg' => 2]),

                Group::make()
                    ->schema([
                        Section::make('Status & Dates')
                            ->schema([
                                Placeholder::make('status')
                                    ->content(fn ($record) => $record?->status?->getLabel() ?? 'Draft'),

                                DatePicker::make('effective_date')
                                    ->default(now())
                                    ->required(),

                                Textarea::make('reason')
                                    ->placeholder('Reason for credit memo...')
                                    ->rows(3),
                            ]),

                        Section::make('Financial Totals')
                            ->schema([
                                TextInput::make('total_amount')
                                    ->label('Total (Incl. VAT)')
                                    ->numeric()
                                    ->prefix('₦')
                                    ->readOnly()
                                    ->placeholder(function (Get $get) {
                                        $items = collect($get('items'));
                                        $grandTotal = $items->reduce(function ($carry, $item) {
                                            $net = (float) ($item['quantity'] ?? 0) * (float) ($item['unit_price'] ?? 0);
                                            $vat = $net * ((float) ($item['vat_percent'] ?? 0) / 100);

                                            return $carry + ($net + $vat);
                                        }, 0);

                                        return number_format($grandTotal, 2);
                                    }),
                                Select::make('currency_code')
                                    ->options(['NGN' => 'Naira', 'USD' => 'USD', 'EUR' => 'EUR'])
                                    ->default('NGN'),
                            ]),
                    ])->columnSpan(['lg' => 1]),
            ])->columns(3);
    }
}
