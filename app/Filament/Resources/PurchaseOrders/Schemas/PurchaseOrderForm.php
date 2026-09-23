<?php

namespace App\Filament\Resources\PurchaseOrders\Schemas;

use App\Enums\PurchaseOrderStatus;
use App\Enums\PurchaseOrderType;
use App\Filament\Traits\HasSystemGeneratedField;
use App\Models\Vendor;
use App\Services\Business\BusinessContextService;
use App\Support\CurrencyPresentation;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;

class PurchaseOrderForm
{
    use HasSystemGeneratedField;

    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Order Header')
                    ->description('Define the type, vendor, and general order information.')
                    ->schema([
                        Hidden::make('business_id')
                            ->default(fn (): ?int => app(BusinessContextService::class)->resolveId(request()->integer('business_id') ?: null))
                            ->dehydrated(),

                        Grid::make([
                            'default' => 1,
                            'md' => 2,
                            'xl' => 3,
                        ])->schema([
                            Select::make('order_type')
                                ->label('Order Type')
                                ->options(PurchaseOrderType::options())
                                ->default('purchase_order')
                                ->required()
                                ->live()
                                ->afterStateUpdated(function (mixed $state, callable $set): void {
                                    $orderType = $state instanceof PurchaseOrderType
                                        ? $state
                                        : PurchaseOrderType::tryFrom((string) $state);

                                    $set('order_number_preview', $orderType?->seriesCode() !== null ? $orderType->seriesCode().'-AUTO' : null);
                                }),

                            static::makeSystemGeneratedTextInput(
                                'order_number',
                                'Order Number',
                                'Generated automatically from the purchase order number series and cannot be changed.'
                            ),

                            Select::make('vendor_id')
                                ->label('Vendor')
                                ->relationship('vendor', 'vendor_name')
                                ->searchable()
                                ->optionsLimit(50)
                                ->required()
                                ->live()
                                ->afterStateUpdated(function ($state, callable $set) {
                                    if ($state) {
                                        $vendor = Vendor::find($state);
                                        $set('vendor_name', $vendor?->vendor_name ?? '');
                                        $set('payment_terms', $vendor?->payment_terms ?? '');
                                        $set('currency_code', $vendor?->currency ?? 'NGN');
                                    } else {
                                        $set('vendor_name', '');
                                        $set('payment_terms', '');
                                        $set('currency_code', 'NGN');
                                    }
                                }),

                            Select::make('currency_code')
                                ->label('Document Currency')
                                ->options([
                                    'USD' => 'USD - US Dollar',
                                    'EUR' => 'EUR - Euro',
                                    'GBP' => 'GBP - British Pound',
                                    'NGN' => 'NGN - Nigerian Naira (LCY)',
                                ])
                                ->default('NGN')
                                ->searchable()
                                ->live()
                                ->required(),

                            TextInput::make('currency_factor')
                                ->label(fn (Get $get): string => 'Exchange Rate (NGN per 1 '.($get('currency_code') ?: 'NGN').')')
                                ->numeric()
                                ->minValue(0)
                                ->step(0.000001)
                                ->default(1)
                                ->required(fn (Get $get): bool => ($get('currency_code') ?: 'NGN') !== 'NGN')
                                ->visible(fn (Get $get): bool => ($get('currency_code') ?: 'NGN') !== 'NGN')
                                ->live(onBlur: true)
                                ->helperText('LCY (NGN) = document amount × rate. NGN documents use 1.'),

                            Select::make('location_id')
                                ->label('Ship To Location')
                                ->relationship('location', 'name')
                                ->searchable()
                                ->optionsLimit(50)
                                ->required(),

                            DatePicker::make('order_date')
                                ->label('Order Date')
                                ->required()
                                ->default(now())
                                ->native(false),

                            Select::make('status')
                                ->label('Status')
                                ->options(PurchaseOrderStatus::options())
                                ->default('PENDING')
                                ->required()
                                ->disabled(fn ($record) => $record && ! $record->canEdit),
                        ]),

                        TextInput::make('vendor_name')
                            ->label('Vendor Name (Reference)')
                            ->required()
                            ->disabled()
                            ->dehydrated(true),
                    ]),

                Section::make('Dates & Terms')
                    ->schema([
                        Grid::make([
                            'default' => 1,
                            'md' => 2,
                            'xl' => 4,
                        ])->schema([
                            DatePicker::make('due_date')
                                ->label('Due Date')
                                ->native(false),

                            DatePicker::make('delivery_date')
                                ->label('Expected Delivery')
                                ->native(false),

                            DatePicker::make('posting_date')
                                ->label('Posting Date')
                                ->native(false),

                            TextInput::make('payment_terms')
                                ->label('Payment Terms')
                                ->maxLength(50),
                        ]),
                    ]),

                Section::make('Financials')
                    ->description('Summary of order totals. Values are often calculated from individual line items.')
                    ->schema([
                        Grid::make([
                            'default' => 1,
                            'md' => 3,
                        ])->schema([
                            TextInput::make('total_amount')
                                ->label('Total Excl. VAT')
                                ->required()
                                ->numeric()
                                ->prefix(fn (Get $get): string => CurrencyPresentation::symbol($get('currency_code')))
                                ->default(0)
                                ->disabled(fn ($record) => $record && $record->id !== null)
                                ->extraInputAttributes(['class' => 'text-xl font-semibold']),

                            TextInput::make('total_vat')
                                ->label('Total VAT')
                                ->required()
                                ->numeric()
                                ->prefix(fn (Get $get): string => CurrencyPresentation::symbol($get('currency_code')))
                                ->default(0)
                                ->disabled(fn ($record) => $record && $record->id !== null)
                                ->extraInputAttributes(['class' => 'text-xl font-semibold text-warning-600']),

                            TextInput::make('grand_total')
                                ->label('Grand Total')
                                ->required()
                                ->numeric()
                                ->prefix(fn (Get $get): string => CurrencyPresentation::symbol($get('currency_code')))
                                ->default(0)
                                ->disabled(fn ($record) => $record && $record->id !== null)
                                ->extraInputAttributes(['class' => 'text-2xl font-black text-primary-600']),
                        ]),

                        TextInput::make('total_summary')
                            ->label('Consolidated Total')
                            ->prefix(fn (Get $get): string => CurrencyPresentation::symbol($get('currency_code')))
                            ->readOnly()
                            ->dehydrated(false) // 🔥 important: don't save to DB
                            ->formatStateUsing(function ($state, $get) {
                                $amount = (float) ($get('total_amount') ?? 0);
                                $vat = (float) ($get('total_vat') ?? 0);
                                $total = $amount + $vat;

                                return number_format($total, 4);
                            })
                            ->reactive(),

                        Grid::make([
                            'default' => 1,
                            'md' => 3,
                        ])->schema([
                            Placeholder::make('total_amount_lcy_preview')
                                ->label('Total Excl. VAT (LCY / NGN)')
                                ->content(fn (Get $get): string => 'NGN '.number_format(
                                    (float) ($get('total_amount') ?? 0) * (float) ($get('currency_factor') ?? 1), 2
                                )),

                            Placeholder::make('total_vat_lcy_preview')
                                ->label('Total VAT (LCY / NGN)')
                                ->content(fn (Get $get): string => 'NGN '.number_format(
                                    (float) ($get('total_vat') ?? 0) * (float) ($get('currency_factor') ?? 1), 2
                                )),

                            Placeholder::make('grand_total_lcy_preview')
                                ->label('Grand Total (LCY / NGN)')
                                ->content(fn (Get $get): string => 'NGN '.number_format(
                                    ((float) ($get('total_amount') ?? 0) + (float) ($get('total_vat') ?? 0)) * (float) ($get('currency_factor') ?? 1), 2
                                ))
                                ->extraAttributes(['class' => 'font-bold']),
                        ]),
                    ]),

                Section::make('Approval Information')
                    ->schema([
                        Grid::make([
                            'default' => 1,
                            'md' => 2,
                        ])->schema([
                            Select::make('approved_by')
                                ->label('Approved By')
                                ->relationship('approver', 'name')
                                ->searchable()
                                ->preload()
                                ->visible(fn ($record) => $record && $record->status !== 'PENDING'),

                            DateTimePicker::make('approved_at')
                                ->label('Approved At')
                                ->seconds(false)
                                ->disabled()
                                ->visible(fn ($record) => $record && ! is_null($record->approved_at)),
                        ]),
                    ])
                    ->collapsible()
                    ->visible(fn ($record) => $record !== null),

                Section::make('Notes')
                    ->schema([
                        Textarea::make('comment')
                            ->label('Internal Comments')
                            ->rows(3)
                            ->columnSpanFull()
                            ->placeholder('Enter any internal instructions or vendor notes here...'),
                    ]),

                Hidden::make('created_by')
                    ->default(auth()->id()),
            ]);
    }
}
