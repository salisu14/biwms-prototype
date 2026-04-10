<?php

namespace App\Filament\Resources\PurchaseCreditMemos\Schemas;

use App\Models\Item;
use App\Models\PurchaseInvoice;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;

class PurchaseCreditMemoForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Group::make()
                    ->schema([
                        Section::make('General Information')
                            ->schema([
                                TextInput::make('document_number')
                                    ->required()
                                    ->disabled()
                                    ->placeholder('Auto-generated'),

                                Select::make('vendor_id')
                                    ->relationship('vendor', 'name')
                                    ->searchable()
                                    ->preload()
                                    ->required()
                                    ->live()
                                    ->afterStateHydrated(function ($state, Set $set) {
                                        if ($state) {
                                             $vendor = \App\Models\Vendor::find($state);
                                             if ($vendor) {
                                                 $set('vendor_name', $vendor->name);
                                             }
                                        }
                                    })
                                    ->afterStateUpdated(function ($state, Set $set) {
                                        if ($state) {
                                             $vendor = \App\Models\Vendor::find($state);
                                             if ($vendor) {
                                                 $set('vendor_name', $vendor->name);
                                             }
                                        }
                                    }),
                                
                                TextInput::make('vendor_name')
                                    ->required()
                                    ->disabled(),

                                Select::make('corrects_invoice_id')
                                    ->label('Link to Invoice')
                                    ->relationship('correctedInvoice', 'document_number')
                                    ->searchable()
                                    ->preload()
                                    ->live()
                                    ->afterStateUpdated(function ($state, Set $set) {
                                        if ($state) {
                                            $invoice = PurchaseInvoice::find($state);
                                            if ($invoice) {
                                                $set('vendor_id', $invoice->vendor_id);
                                                $set('vendor_name', $invoice->vendor_name);
                                                $set('corrects_invoice_number', $invoice->document_number);
                                            }
                                        }
                                    }),
                                
                                TextInput::make('corrects_invoice_number')
                                    ->hidden(),
                            ])->columns(2),

                        Section::make('Credit Items')
                            ->schema([
                                Repeater::make('lines')
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
                                                    $set('unit_cost', $item?->unit_cost ?? 0);
                                                    $set('unit_of_measure_code', $item?->uom_code ?? 'EA');
                                                    $set('item_code', $item?->item_number);
                                                    $set('general_product_posting_group_id', $item?->general_product_posting_group_id);
                                                }
                                            })
                                            ->columnSpan(4),

                                        TextInput::make('quantity')
                                            ->numeric()
                                            ->default(1)
                                            ->required()
                                            ->live(onBlur: true)
                                            ->columnSpan(2),

                                        TextInput::make('unit_cost')
                                            ->label('Unit Cost')
                                            ->numeric()
                                            ->required()
                                            ->live(onBlur: true)
                                            ->columnSpan(3),

                                        TextInput::make('tax_percent')
                                            ->label('Tax %')
                                            ->numeric()
                                            ->default(0)
                                            ->live(onBlur: true)
                                            ->columnSpan(1),

                                        TextInput::make('grand_total')
                                            ->label('Line Total')
                                            ->numeric()
                                            ->readOnly()
                                            ->placeholder(function (Get $get) {
                                                $qty = (float)($get('quantity') ?? 0);
                                                $cost = (float)($get('unit_cost') ?? 0);
                                                $tax = (float)($get('tax_percent') ?? 0);
                                                $net = $qty * $cost;
                                                return number_format($net + ($net * ($tax / 100)), 2);
                                            })
                                            ->columnSpan(2),
                                        
                                        TextInput::make('item_code')->hidden(),
                                        TextInput::make('general_product_posting_group_id')->hidden(),
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
                                
                                Placeholder::make('rejection_reason')
                                    ->label('Rejection Reason')
                                    ->content(fn ($record) => $record?->rejection_reason)
                                    ->visible(fn ($record) => $record?->status === \App\Enums\ApprovalStatus::REJECTED),

                                DatePicker::make('posting_date')
                                    ->default(now())
                                    ->required(),

                                Select::make('location_id')
                                    ->relationship('location', 'name')
                                    ->preload()
                                    ->searchable(),
                            ]),

                        Section::make('Financial Totals')
                            ->schema([
                                TextInput::make('grand_total')
                                    ->label('Grand Total')
                                    ->numeric()
                                    ->readOnly()
                                    ->placeholder(function (Get $get) {
                                        $lines = collect($get('lines'));
                                        $total = $lines->reduce(function($carry, $line) {
                                            $net = (float)($line['quantity'] ?? 0) * (float)($line['unit_cost'] ?? 0);
                                            $tax = $net * ((float)($line['tax_percent'] ?? 0) / 100);
                                            return $carry + ($net + $tax);
                                        }, 0);
                                        return number_format($total, 2);
                                    }),
                                Select::make('currency_code')
                                    ->options(['USD' => 'USD', 'NGN' => 'Naira'])
                                    ->default('USD'),
                            ]),
                    ])->columnSpan(['lg' => 1]),
            ])->columns(3);
    }
}
