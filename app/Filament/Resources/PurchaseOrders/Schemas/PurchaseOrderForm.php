<?php

namespace App\Filament\Resources\PurchaseOrders\Schemas;

use App\Enums\PurchaseOrderStatus;
use App\Enums\PurchaseOrderType;
use App\Models\PurchaseOrder;
use App\Models\Vendor;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class PurchaseOrderForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Order Header')
                    ->description('Define the type, vendor, and general order information.')
                    ->schema([
                        Grid::make(3)->schema([
                            Select::make('order_type')
                                ->label('Order Type')
                                ->options(PurchaseOrderType::options())
                                ->default('purchase_order')
                                ->required()
                                ->live()
                                ->afterStateUpdated(fn ($state, callable $set) => $set('order_number_preview', PurchaseOrderType::from($state)?->seriesCode().'-AUTO')
                                ),

                            TextInput::make('order_number')
                                ->label('Order Number')
//                                ->required()
                                ->unique(ignoreRecord: true)
                                ->helperText('Leave blank to auto-generate based on Series.')
                                ->disabled(fn (?PurchaseOrder $record) => $record !== null)
                                ->dehydrated(),

                            Select::make('vendor_id')
                                ->label('Vendor')
                                ->relationship('vendor', 'vendor_name')
                                ->searchable()
                                ->preload()
                                ->required()
                                ->live()
                                ->afterStateUpdated(function ($state, callable $set) {
                                    if ($state) {
                                        $vendor = Vendor::find($state);
                                        $set('vendor_name', $vendor?->vendor_name ?? '');
                                        $set('payment_terms', $vendor?->payment_terms ?? '');
                                        $set('currency_code', $vendor?->currency ?? 'USD');
                                    } else {
                                        $set('vendor_name', '');
                                        $set('payment_terms', '');
                                        $set('currency_code', 'USD');
                                    }
                                }),

                            Select::make('currency_code')
                                ->label('Currency')
                                ->options([
                                    'USD' => 'USD - US Dollar',
                                    'EUR' => 'EUR - Euro',
                                    'GBP' => 'GBP - British Pound',
                                    'NGN' => 'NGN - Nigerian Naira',
                                ])
                                ->default('USD')
                                ->searchable()
                                ->required(),

                            Select::make('location_id')
                                ->label('Ship To Location')
                                ->relationship('location', 'name')
                                ->searchable()
                                ->preload()
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
                        Grid::make(4)->schema([
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
                        Grid::make(3)->schema([
                            TextInput::make('total_amount')
                                ->label('Total Excl. VAT')
                                ->required()
                                ->numeric()
                                ->prefix('$')
                                ->default(0)
                                ->disabled(fn ($record) => $record && $record->id !== null)
                                ->extraInputAttributes(['class' => 'text-xl font-semibold']),

                            TextInput::make('total_vat')
                                ->label('Total VAT')
                                ->required()
                                ->numeric()
                                ->prefix('$')
                                ->default(0)
                                ->disabled(fn ($record) => $record && $record->id !== null)
                                ->extraInputAttributes(['class' => 'text-xl font-semibold text-warning-600']),

                            TextInput::make('grand_total')
                                ->label('Grand Total')
                                ->required()
                                ->numeric()
                                ->prefix('$')
                                ->default(0)
                                ->disabled(fn ($record) => $record && $record->id !== null)
                                ->extraInputAttributes(['class' => 'text-2xl font-black text-primary-600']),
                        ]),

                        TextInput::make('total_summary')
                            ->label('Consolidated Total')
                            ->prefix('$')
                            ->readOnly()
                            ->dehydrated(false) // 🔥 important: don't save to DB
                            ->formatStateUsing(function ($state, $get) {
                                $amount = (float) ($get('total_amount') ?? 0);
                                $vat = (float) ($get('total_vat') ?? 0);
                                $total = $amount + $vat;

                                return number_format($total, 4);
                            })
                            ->reactive(),
                    ]),

                Section::make('Approval Information')
                    ->schema([
                        Grid::make(2)->schema([
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
